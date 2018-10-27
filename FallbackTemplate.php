<?php

namespace Kimhf\SageWoocommerceSupport;

/**
 * Add support for fallback blade templates.
 */
class FallbackTemplate
{
    /**
     * Add the blade namespace to the view finder.
     *
     * @return void
     */
    public static function getEntry()
    {
        \App\sage('view.finder')->addNamespace('SWS', __DIR__ . '\views');

        return 'SWS::woocommerce';
    }

    /**
     * Get fallback view if not found in theme.
     *
     * @return mixed
     */
    public static function getBladeView($view) : string
    {
        $namespace = '';
        if (! \App\locate_template($view)) {
            $namespace = 'SWS::';
        }

        return $namespace . $view;
    }

    /**
     * Get default blade layout.
     *
     * @return string
     */
    public static function getBladeLayout() : string
    {
        $search_layouts = [
            "layouts/woocommerce",
            "layouts/app",
        ];

        foreach ($search_layouts as $search_layout) {
            if (\App\locate_template($search_layout)) {
                return $search_layout;
            }
        }

        return 'SWS::layouts.fallback';
    }
}
