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
    wp_register_script(
        'custom-load-more',
        plugin_dir_url(__FILE__) . 'assets/js/pagination.js',
        ['jquery'],
        '1.0', // Version number
        true   // Load in the footer (recommended)
    );

    // Enqueue the script. This ensures it loads on the front end.
    wp_enqueue_script('custom-load-more', plugin_dir_url(__FILE__) . 'assets/js/pagination.js', ['jquery'], '1.0', true);

    wp_localize_script('custom-load-more', 'load_more', ['ajaxurl' => admin_url('admin-ajax.php')]);

    // Enqueue style
    wp_enqueue_style('widget-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0');
}
add_action('wp_enqueue_scripts', 'my_custom_load_more_enqueue_scripts');

function custom_load_more_posts_ajax()
{
    ob_start();

    $paged = !empty($_POST['page']) ? intval($_POST['page']) : 1;
    $post_per_page = !empty($_POST['post_per_page']) ? intval($_POST['post_per_page']) : 3;
    $post_type = sanitize_text_field($_POST['post_type']);
    $template_id = intval($_POST['template_id']);
    $displayed_ids = !empty($_POST['displayed_ids']) ? array_map('intval', $_POST['displayed_ids']) : [];

    $widget_settings = !empty($_POST['widget_settings']) ? $_POST['widget_settings'] : [];


    // WP_Query args
    $args = [
        'post_type' => $post_type,
        'posts_per_page' => $post_per_page,
        'paged' => $paged,
        'post__not_in' => $displayed_ids,
    ];

    if ($post_type === 'attachment') {
        $args['post_status'] = 'inherit';
    }

    // error_log('AJAX Load More Query Args: ' . print_r($args, true));

    $query = new WP_Query($args);

    $new_ids = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $new_ids[] = get_the_ID();

            echo '<div class="custom-loop-item">';
            // Render using Elementor template
            if ($template_id) {
                \Elementor\Plugin::instance()->db->switch_to_post(get_the_ID());

                $html =  \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id);

                // Remove <style> tags to prevent layout issues
                $html =  preg_replace('#<style.*?</style>#is', '', $html);

                echo $html;

                \Elementor\Plugin::instance()->db->restore_current_post();
            }
            echo '</div>';
        }
    }

    wp_reset_postdata();

    wp_send_json_success([
        'html' => ob_get_clean(),
        'max_num_pages' => $query->max_num_pages,
        'new_ids' => $new_ids,
    ]);
}

add_action('wp_ajax_custom_load_more_posts', 'custom_load_more_posts_ajax');
add_action('wp_ajax_nopriv_custom_load_more_posts', 'custom_load_more_posts_ajax');
