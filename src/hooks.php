<?php

namespace Kimhf\SageWoocommerceSupport;

if (!defined('ABSPATH')) {
    return;
}

/**
 * Declare WooCommerce support.
 *
 * @return void
 */
add_action('after_setup_theme', function () {
    add_theme_support('woocommerce');
});

/**
 * Load comments template.
 *
 * @param string $template template to load.
 * @return string
 */
add_filter('comments_template', function ($template) {
    if (get_post_type() === 'product') {
        $path             = WC()->template_path();
        $located_template = \App\locate_template("{$path}single-product-reviews");

        if ($located_template) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo \App\template($located_template, get_data());

            // Return a empty file to make WordPress happy
            $template = __DIR__ . '/views/empty.php';
        }
    }

    return $template;
}, 20);

/**
 * Filter woocommerce_template_loader_files located in get_template_loader_files().
 * Loads a template if found in any of the config view paths, and then returns a empty file to woocommerce.
 * Include $data added by controllers.
 *
 * @param array $search_files
 * @param string $default_file
 * @return array
 */
add_filter('woocommerce_template_loader_files', function (array $search_files, string $default_file): array {
    $templates = get_template_loader_files($default_file);
    $template  = \App\locate_template($templates);

    if (!$template) {
        $template = get_fallback_template();
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo \App\template($template, get_data($template));

    // Return the empty index.php in Sage to make WooCommerce happy
    return ['index.php'];
}, 10, 3);

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
add_filter('wc_get_template', function (string $located, string $template_name, array $args, string $template_path, string $default_path) : string {
    $path     = WC()->template_path();
    $template = \App\locate_template("{$path}{$template_name}");

    if ($template) {
        if (is_admin() && ! wp_doing_ajax() && function_exists('get_current_screen') && get_current_screen()->id === 'woocommerce_page_wc-status') {
            // Return the template so WooCommerce can read the template version for the system status screen.
            return $template;
        }

        // Output the template in the woocommerce_before_template_part action
        add_action_once('woocommerce_before_template_part', function (string $template_name, string $template_path, string $located, array $args) use ($template) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo \App\template($template, wp_parse_args($args, get_data()));
        }, PHP_INT_MAX, 4);

        // Return a empty file to make WooCommerce happy
        return get_stylesheet_directory() . '/index.php';
    }

    return $located;
}, 10, 5);

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
add_filter('wc_get_template_part', function (string $template, string $slug, string $name) {
    $path           = WC()->template_path();
    $template_parts = [];

    if ($name) {
        $template_parts[] = "{$path}{$slug}-{$name}";
    }

    $template_parts[] = "{$path}{$slug}";
    $template_part    = \App\locate_template($template_parts);

    if ($template_part) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo \App\template($template_part, get_data());
        return false;
    }

    return $template;
}, 10, 3);
