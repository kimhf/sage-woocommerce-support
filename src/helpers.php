<?php

namespace Kimhf\SageWoocommerceSupport;

/**
 * Register an action to run exactly one time.
 *
 * The arguments match that of add_action(), but this function will also register a second
 * callback designed to remove the first immediately after it runs.
 *
 * @see https://engineering.growella.com/one-time-callbacks-wordpress-plugin-api/
 *
 * @param string $hook       The action name.
 * @param callable $callback The callback function.
 * @param integer $priority  Optional. The priority at which the callback should be executed.
 *                           Default is 10.
 * @param integer $args      Optional. The number of arguments expected by the callback function.
 *                           Default is 1.
 * @return boolean           Like add_action(), this function always returns true.
 */
function add_action_once(string $hook, $callback, int $priority = 10, int $args = 1) : bool
{
    static $singulars;
    static $i = 0;
    $i++;
    $singulars[$i] = function () use ($hook, $callback, $priority, $args, &$singulars, $i) {
        remove_action($hook, $singulars[$i], $priority, $args);

        call_user_func_array($callback, func_get_args());

        unset($singulars[$i]);
    };

    return add_action($hook, $singulars[$i], $priority, $args);
}

/**
 * Add the blade namespace to the view finder, require the helpers, and return the entry template name.
 *
 * @return string
 */
function get_fallback_template() : string
{
    \App\sage('view.finder')->addNamespace('SWS', __DIR__ . '\views');
    return 'SWS::woocommerce';
}

/**
 * Get fallback view if not found in theme.
 *
 * @return string
 */
function get_blade_template($view) : string
{
    return \App\locate_template($view) ? $view : "SWS::{$view}";
}

/**
 * Get default blade layout.
 *
 * @return string
 */
function get_blade_layout() : string
{
    $search_layouts = [
        'layouts/woocommerce',
        'layouts/app',
    ];

    foreach ($search_layouts as $search_layout) {
        if (\App\locate_template($search_layout)) {
            return $search_layout;
        }
    }

    return 'SWS::layouts.fallback';
}

/**
 * Get contoller data.
 *
 * @return array
 */
function get_data($template = '') : array
{
    static $data = [];

    if (!$data && $template) {
        $data = collect(get_body_class())->reduce(function ($data, $class) use ($template) {
            return apply_filters("sage/template/{$class}/data", $data, $template);
        }, []);
    }

    return $data;
}

/**
 * Get an array of filenames to search for a given template.
 * This is based on the private method get_template_loader_files() in WC_Template_Loader.
 *
 * @param  string $default_file The default file name.
 * @return string[]
 */
function get_template_loader_files( $default_file )
{
    $templates   = [];
    $templates[] = 'woocommerce.php';

    if (is_page_template()) {
        $templates[] = get_page_template_slug();
    }

    if (is_singular('product')) {
        $object       = get_queried_object();
        $name_decoded = urldecode($object->post_name);
        if ($name_decoded !== $object->post_name) {
            $templates[] = "single-product-{$name_decoded}.php";
        }
        $templates[] = "single-product-{$object->post_name}.php";
    }

    if (is_product_taxonomy()) {
        $object      = get_queried_object();
        $templates[] = 'taxonomy-' . $object->taxonomy . '-' . $object->slug . '.php';
        $templates[] = WC()->template_path() . 'taxonomy-' . $object->taxonomy . '-' . $object->slug . '.php';
        $templates[] = 'taxonomy-' . $object->taxonomy . '.php';
        $templates[] = WC()->template_path() . 'taxonomy-' . $object->taxonomy . '.php';
    }

    $templates[] = $default_file;
    $templates[] = WC()->template_path() . $default_file;

    return array_unique($templates);
}
