<?php

namespace Kimhf\SageWoocommerceSupport;

/**
 * Make Sage compatible with WooCommerce. Add support for controllers and blade templates.
 */
class BladeTemplateLoader
{
    /**
     * The single instance of the class
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Blade templates
     *
     * @var array
     */
    private $blade_templates = [];

    /**
     * Data
     *
     * @var array
     */
    private $data = [];

    /**
     * Initialize the class
     *
     * @return void
     */
    private function __construct()
    {
        add_filter('wc_get_template', [ $this, 'wcGetTemplate' ], 10, 5);
        add_filter('wc_get_template_part', [ $this, 'wcGetTemplatePart' ], 10, 3);
        add_filter('woocommerce_template_loader_files', [ $this, 'woocommerceTemplateLoaderFiles' ], 10, 3);
    }

    /**
     * Filter woocommerce_template_loader_files located in get_template_loader_files().
     * Loads a template if found in any of the config view paths, and then returns a empty file to woocommerce.
     * Include $data added by controllers.
     *
     * @param array $search_files
     * @param string $default_file
     * @return array
     */
    public function woocommerceTemplateLoaderFiles(array $search_files, string $default_file) : array
    {
        $templates = [];
        $templates[] = 'woocommerce';

        if (is_page_template()) {
            $templates[] = \App\sage('blade')->normalizeViewPath(get_page_template_slug());
        }

        if (is_singular('product')) {
            $object       = get_queried_object();
            $name_decoded = urldecode($object->post_name);
            if ($name_decoded !== $object->post_name) {
                $templates[] = "single-product-{$name_decoded}";
            }
            $templates[] = "single-product-{$object->post_name}";
        }

        if (is_product_taxonomy()) {
            $object = get_queried_object();
            $templates[] = 'taxonomy-' . $object->taxonomy . '-' . $object->slug;
            $templates[] = 'taxonomy-' . $object->taxonomy;
            $templates[] = 'archive-product';
        }

        $templates[] = \App\sage('blade')->normalizeViewPath($default_file);

        $templates = array_unique($templates);

        // What subfolders should we search in. The folders only applies to the view.paths and not view.namespaces
        $folders = [
            '',
            WC()->template_path()
        ];

        $template = $this->getTemplateToLoad($templates, $folders);

        if ($template) {
            $this->data = collect(get_body_class())->reduce(function ($data, $class) use ($template) {
                return apply_filters("sage/template/{$class}/data", $data, $template);
            }, []);
            echo \App\template($template, $this->data);
            // Return the empty index.php in Sage to make WooCommerce happy
            return ['index.php'];
        }

        return $search_files;
    }

    /**
     * Filter wc_get_template_part located in wc_get_template_part().
     * Loads a template if found in any of the config view paths,
     * and then returns false to stop woocommerce from loading anything.
     *
     * Include $data added by controllers.
     *
     * @param string  $template The template file Woocommerce found and is ready to load.
     * @param mixed   $slug
     * @param string  $name (default: '')
     * @return mixed  Return false to shortcut the loading of the default template if we find one in the theme.
     */
    public function wcGetTemplatePart(string $template, string $slug, string $name)
    {
        // What template parts should we search for.
        $template_parts = [];
        if ($name) {
            $template_parts[] = "{$slug}-{$name}";
        }
        $template_parts[] = "{$slug}";

        // What subfolders should we search in. Only aplies to the view.paths and not view.namespaces
        $folders = [
            'partials/',
            WC()->template_path()
        ];

        $template_part = $this->getTemplateToLoad($template_parts, $folders);

        if ($template_part) {
            echo \App\template("{$template_part}", $this->getData());
            return false;
        }

        return $template;
    }

    /**
     * Filter wc_get_template located in wc_get_template().
     * Looks for a template to load in any of the config view paths.
     * Saves the result for loading in the woocommerce_before_template_part action.
     *
     * @param string $located
     * @param string $template_name
     * @param array  $args
     * @param string $template_path
     * @param string $default_path
     * @return string The file that woocommerce will include.
     */
    public function wcGetTemplate(string $located, string $template_name, array $args, string $template_path, string $default_path) : string
    {
        $templates = [];
        $templates[] = \App\sage('blade')->normalizeViewPath($template_name);

        $folders = [
            '',
            WC()->template_path()
        ];

        $template = $this->getTemplateToLoad($templates, $folders);

        if ($template) {
            $this->setBladeTemplate($template_name, $template);

            // This is to load the blade template as close as possible to include($located); in wc_get_template()
            add_action('woocommerce_before_template_part', [ $this, 'includeBladeTemplate' ], PHP_INT_MAX, 4);

            // Return a empty file to make WooCommerce happy
            return __DIR__ . '/dummyTemplate.php';
        }

        return $located;
    }

    /**
     * At this point we want to include a blade template, woocommerce will only include a empty php file.
     *
     * @param string $template_name
     * @param string $template_path
     * @param string $located
     * @param array $args
     * @return void
     */
    public function includeBladeTemplate(string $template_name, string $template_path, string $located, array $args) : void
    {
        if ($blade_template_name = $this->getBladeTemplate($template_name)) {
            $data = $this->getData();

            $args = wp_parse_args($args, $data);

            echo \App\template($blade_template_name, $args);
        }
        remove_action('woocommerce_before_template_part', [ $this, 'includeBladeTemplate' ], PHP_INT_MAX, 4);
    }

    /**
     * Find out if any of the templates exsists in any of the config view paths.
     * First checks view.paths, then view.namespaces.
     *
     * @param array $templates
     * @param array $folders
     * @return string
     */
    public function getTemplateToLoad(array $templates, array $folders) : string
    {
        $folders = $this->trailingSlashMost($folders);

        // Build a array of template parts to search for.
        $prefixedTemplates = $this->addPrefixes($templates, $folders);

        $view_paths = \App\config('view.paths');
        $template = $this->searchTemplatePart($view_paths, $prefixedTemplates);

        if (! $template) {
            $view_namespaces = \App\config('view.namespaces');
            $template = $this->searchTemplatePart($view_namespaces, $templates);
        }

        return $template;
    }

    /**
     * Helper function to concatinate two arrays of strings.
     * One arrays strings will be prefixed to the strings in the other.
     * Return a array with the resulting strings.
     *
     * @param array $bases
     * @param array $prefixes
     * @return array
     */
    public function addPrefixes(array $bases, array $prefixes) : array
    {
        $prefixed = [];
        foreach ($bases as $base) {
            foreach ($prefixes as $prefix) {
                $prefixed[] = "{$prefix}{$base}";
            }
        }
        return $prefixed;
    }

    /**
     * Make sure all strings in a array have trailings slashes with the exception of empty strings.
     *
     * @param array $strings
     * @return array
     */
    public function trailingSlashMost(array $strings) : array
    {
        $strings = array_map(function ($string) {
            if (! $string) {
                return $string;
            }
            return trailingslashit($string);
        }, $strings);

        return $strings;
    }

    /**
     * Search for template part names in provided folders.
     * Return the first one we find, or false if no template part was found.
     *
     * @param array $paths The folder paths to look in.
     * @param array $names The file names to look for.
     * @return mixed Returns template part name if found. False if not found.
     */
    public function searchTemplatePart(array $paths, array $names)
    {
        if (empty($paths) || empty($names)) {
            return false;
        }

        $filetypes = [
            '.blade.php',
            '.php'
        ];

        $paths = array_unique($paths);
        $paths = array_map('trailingslashit', $paths);

        foreach ($paths as $namespace => $path) {
            foreach ($names as $name) {
                foreach ($filetypes as $filetype) {
                    if (file_exists("{$path}{$name}{$filetype}")) {
                        if (is_string($namespace)) {
                            $name = "{$namespace}::{$name}";
                        }

                        return $name;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Set blade version of template.
     *
     * @return void
     */
    public function setBladeTemplate(string $template_name, string $located) : void
    {
        $this->blade_templates[$template_name] = $located;
    }

    /**
     * Get blade version of template if set.
     *
     * @return string
     */
    public function getBladeTemplate(string $template_name) : string
    {
        if (isset($this->blade_templates[$template_name])) {
            return $this->blade_templates[$template_name];
        }

        return '';
    }

    /**
     * Get data.
     *
     * @return array
     */
    public function getData() : array
    {
        return $this->data;
    }

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of the class.
     */
    public static function getInstance() : BladeTemplateLoader
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
