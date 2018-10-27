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
        add_action('after_setup_theme', [ $this, 'declareWooCommerceSupport' ]);
        add_filter('wc_get_template', [ $this, 'wcGetTemplate' ], 10, 5);
        add_filter('wc_get_template_part', [ $this, 'wcGetTemplatePart' ], 10, 3);
        add_filter('woocommerce_template_loader_files', [ $this, 'woocommerceTemplateLoaderFiles' ], 10, 3);
    }

    /**
     * Declare WooCommerce support.
     *
     * @return void
     */
    public function declareWooCommerceSupport()
    {
        add_theme_support('woocommerce');
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
        $path = trailingslashit(WC()->template_path());

        $templates   = [];
        $templates[] = "{$path}woocommerce";

        if (is_page_template()) {
            $templates[] = get_page_template_slug();
        }

        if (is_singular('product')) {
            $object       = get_queried_object();
            $name_decoded = urldecode($object->post_name);
            if ($name_decoded !== $object->post_name) {
                $templates[] = "{$path}single-product-{$name_decoded}";
            }
            $templates[] = "{$path}single-product-{$object->post_name}";
        }

        if (is_product_taxonomy()) {
            $object      = get_queried_object();
            $templates[] = "{$path}taxonomy-{$object->taxonomy}-{$object->slug}";
            $templates[] = "{$path}taxonomy-{$object->taxonomy}";
            $templates[] = "{$path}archive-product";
        }

        $templates[] = "{$path}{$default_file}";

        $template = \App\locate_template($templates);

        if (!$template) {
            $template = FallbackTemplate::getEntry();
        }

        $this->data = collect(get_body_class())->reduce(function ($data, $class) use ($template) {
            return apply_filters("sage/template/{$class}/data", $data, $template);
        }, []);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo \App\template($template, $this->data);

        // Return the empty index.php in Sage to make WooCommerce happy
        return ['index.php'];
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
        $path = trailingslashit(WC()->template_path());

        $template_parts = [];
        if ($name) {
            $template_parts[] = "{$path}{$slug}-{$name}";
        }
        $template_parts[] = "{$path}{$slug}";

        $template_part = \App\locate_template($template_parts);

        if ($template_part) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo \App\template($template_part, $this->getData());
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
        $path = trailingslashit(WC()->template_path());

        $template = \App\locate_template("{$path}{$template_name}");

        if ($template) {
            if (is_admin() && function_exists('get_current_screen') && get_current_screen()->id === 'woocommerce_page_wc-status') {
                // Return the template so WooCommerce can read the template version for the system status screen.
                return $template;
            }

            $this->setBladeTemplate($template_name, $template);

            // This is to load the blade template as close as possible to include($located); in wc_get_template()
            add_action('woocommerce_before_template_part', [ $this, 'includeBladeTemplate' ], PHP_INT_MAX, 4);

            // Return a empty file to make WooCommerce happy
            return get_stylesheet_directory() . '/index.php';
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
        $blade_template_name = $this->getBladeTemplate($template_name);

        if ($blade_template_name) {
            $data = $this->getData();
            $args = wp_parse_args($args, $data);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo \App\template($blade_template_name, $args);
        }
        remove_action('woocommerce_before_template_part', [ $this, 'includeBladeTemplate' ], PHP_INT_MAX, 4);
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
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
