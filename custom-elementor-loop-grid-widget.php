<?php

/**
 * Plugin Name: Custom Elementor Loop Grid Widget
 * Description: A custom Elementor widget with advanced pagination, filters, and loop grid functionality.
 * Version: 1.0.0
 * Author: Mervan Agency
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (! defined('ABSPATH')) {
    exit;
}

function register_my_custom_widgets($widgets_manager)
{
    require_once(__DIR__ . '/widgets/loop-grid-widget.php');

    // Add the namespace before the class name
    $widgets_manager->register(new \MyCustomWidgets\Media_Loop_Grid());
}

add_action('elementor/widgets/register', 'register_my_custom_widgets');

/**
 * Enqueue the JavaScript file for the Load More functionality.
 */
function my_custom_load_more_enqueue_scripts()
{

    // Register the script. Ensure 'jquery' is a dependency as your JS relies on it.
    // Adjust the second parameter if your JS file is not in the same directory as the main plugin file.
    wp_register_script(
        'custom-load-more',
        plugin_dir_url(__FILE__) . 'assets/js/pagination.js',
        ['jquery'],
        '1.0', // Version number
        true   // Load in the footer (recommended)
    );

    // Enqueue the script. This ensures it loads on the front end.
    wp_enqueue_script('custom-load-more');
}
add_action('wp_enqueue_scripts', 'my_custom_load_more_enqueue_scripts');
