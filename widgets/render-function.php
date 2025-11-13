<?php

use Elementor\Core\Files\CSS\Post;

$settings = $this->get_settings_for_display();
$template_id = $settings['template_id'];
$widget_id = $this->get_id();
$query_var = 'paged_' . $widget_id;

/**
 * --- POSTS PER PAGE ---
 */
$posts_per_page = $settings['posts_per_page'] ?? 6;

/**
 * MASONRY
 */
$masonry_enabled = ($settings['masonry'] === 'yes');
$columns = !empty($settings['columns']) ? (int) $settings['columns'] : 3;

/**
 * --- DETERMINE CURRENT PAGE NUMBER FOR PAGINATION ---
 * Check if the 'individual_pagination' setting is enable
 */
if (!empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes') {
    // When individual pagination is ON:
    // Use a custom query var based on the widget ID (e.g. paged_{widget_id})
    // This allowseach widget to track its own page number indipendently
    $paged = max(1, get_query_var($query_var, 1));
} else {
    // When individual pagination is OFF:
    // Use the default wordpress 'paged' query var
    $paged = max(1, get_query_var('paged', 1));
}


/**
 * --- DETERMINE QUERY VARIABLE TO USE FOR AJAX ---
 * 'individual_pagination' pagination ON -> use unique widget query var
 * 'individual_pagination' pagination OFF -> use global 'paged' query var
 */
if (!empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes') {
    $current_query_var = $query_var; // Widget specific query variable
} else {
    $current_query_var = 'paged'; // Global wordpress pagination var
}


/**
 * --- DETERMINE CURRENT PAGE NUMBER ---
 * Use the selected query variable to get the current page
 * max(1, ...) ensures page numner is always at least 1
 */
$paged = max(1, get_query_var($current_query_var, 1));


/**
 * --- BASE WP_QUERY ARGUMENTS ---
 */
$args = [
    'post_type' => $settings['post_type'] ?? 'post',
    'posts_per_page' => $posts_per_page,
    'orderby' => $settings['order_by'] ?? 'date',
    'order'          => $settings['order_type'] ?? 'DESC',
    'paged'          => $paged,
];


/**
 * --- HANDLE POST OFFSET ---
 */
$initial_offset = $settings['offset'] ?? 0;
if ($initial_offset > 0) {
    if ($paged === 1) {
        // First page: just skip the initial offset posts
        $args['offset'] = $initial_offset;
    } else {
        // Subsequent pages: skip the initial offset + posts from previous pages
        // Formula: offset = initial_offset + ((current_page - 1) * posts_per_page)
        $args['offset'] = $initial_offset + (($paged - 1) * $posts_per_page);

        // Remove 'paged' because wordpress doesn't handle offset + paged correctly
        unset($args['paged']);
    }
}


/**
 * --- HANDLE 'attachment' POST TYPE ---
 */
if ($settings['post_type'] === 'attachment') {
    // Set post status to 'inherit' because media attachments use this status
    $args['post_status'] = 'inherit';

    $mime_type = $this->get_attachment_mime_type($settings);

    // If any MIME types were selected, add them to the query arguments
    if (!empty($mime_type)) {
        $args['post_mime_type'] = $mime_type;
    }
}


/**
 * --- AVOID DUPLICATE POSTS ---
 */
if (!empty($settings['avoid_duplicates']) && $settings['avoid_duplicates'] == 'yes' && !empty(self::$displayed_post_ids)) {
    // Exclude these posts from the current query
    // 'post__not_in' is a WordPress query argument that prevents posts
    // with the specified IDs from appearing in the results
    // (self::$displayed_post_ids) stores all post IDs that have already been output
    $args['post__not_in'] = self::$displayed_post_ids;
}


/**
 * --- TAXONOMY FILTER ---
 */
$tax_query = [];
$include_by = (array) ($settings['include_by']) ?? [];
$exclude_by = (array) ($settings['exclude_by']) ?? [];

// INCLUDE TAXONOMY 
if (in_array('taxonomy', $include_by) && !empty($settings['include_taxonomy'])) {
    $taxonomy = $settings['include_taxonomy'];
    $terms = $this->get_taxonomy_terms($taxonomy);

    if (!empty($terms)) {
        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => $terms,
            'operator' => 'IN',
        ];
    }
}

// EXCLUDE TAXONOMY 
if (in_array('taxonomy', $exclude_by) && !empty($settings['exclude_taxonomy'])) {
    $taxonomy = $settings['exclude_taxonomy'];
    $terms = $this->get_taxonomy_terms($taxonomy);

    if (!empty($terms)) {
        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => $terms,
            'operator' => 'NOT IN',
        ];
    }
}

if (!empty($tax_query)) {
    $args['tax_query'] = $tax_query;
}


/**
 * --- META KEY FILTER ---
 */
$meta_query = [];
$meta_query['relation'] = 'AND';

if (in_array('meta_key', $include_by) && !empty($settings['include_meta_key'])) {
    $meta_query[] = [
        'key'     => $settings['include_meta_key'],
        'value'   => $settings['include_meta_value'] ?? '',
        'compare' => 'LIKE',
    ];
}

// Exclude by Meta Key 
if (in_array('meta_key', $exclude_by) && !empty($settings['exclude_meta_key'])) {
    $meta_query[] = [
        'key'     => $settings['exclude_meta_key'],
        'value'   => $settings['exclude_meta_value'] ?? '',
        'compare' => 'NOT LIKE',
    ];
}

if (!empty($meta_query)) {
    $args['meta_query'] = $meta_query;
}


/**
 * ---EXCLUDE BY MANUAL SELECTION ---
 */
if (!empty($settings['exclude_by']) && in_array('manual_selection', $settings['exclude_by']) && !empty($settings['exclude_by_post_title'])) {
    // Sanitize user input
    $post_title = sanitize_text_field($settings['exclude_by_post_title']);

    // Get the post ID by its title
    $excluded_id = $this->get_post_by_title($post_title);

    if ($excluded_id) {
        $args['post__not_in'] = [$excluded_id];
    }
}


/**
 * --- EXECUTE QUERY ---
 */
$query = new WP_Query($args);

echo '<div id="custom-loop-' . $widget_id . '" class="custom-loop-grid-wrapper">';

// Ensure Elementor styles for this template are loaded
$upload_dir = wp_upload_dir();
$css_file_path = $upload_dir['basedir'] . '/elementor/css/post-' . $template_id . '.css';

if (file_exists($css_file_path)) {
    // Output inline <style> so it works even via AJAX or dynamic render
    $css_content = file_get_contents($css_file_path);
    if ($css_content) {
        echo '<style id="elementor-post-' . esc_attr($template_id) . '">' . $css_content . '</style>';
    }
}

echo '<div class="custom-loop-grid' . esc_attr($masonry_enabled ? ' masonry-enabled' : '') . '" data-columns="' . esc_attr($columns) . '" data-is-masonry="' . esc_attr($masonry_enabled ? 'true' : 'false') . '" style="--columns: <?php echo $columns; ?>;">';

// Check if the query returned any posts before rendering
if ($query->have_posts()) {

    // Loop through each post in the query
    while ($query->have_posts()) {
        $query->the_post();

        echo '<div class="custom-loop-item">';
        self::$displayed_post_ids[] = get_the_ID();
        if ($template_id) {
            // Temporarily switch Elementor's context to the current post
            // This ensures dynamic tags and post-specific data inside the template work correctly
            \Elementor\Plugin::instance()->db->switch_to_post(get_the_ID());

            // Render the selected Elementor template content for the current post
            // get_builder_content_for_display() returns the frontend HTML of the specified template
            $html = \Elementor\plugin::instance()->frontend->get_builder_content_for_display($template_id);

            $html = preg_replace('#<style.*?</style>#is', '', $html);

            echo $html;

            // Restore Elementor’s previous post context after rendering.
            \Elementor\Plugin::instance()->db->restore_current_post();
        }
        echo '</div>';
    }
}

echo '</div>'; // .custom-loop-grid

// Reset the global $post data after the custom query.
// This prevents conflicts with other queries or template parts that rely on the main loop.
wp_reset_postdata();
echo '</div>'; // .custom-loop-grid-wrapper

/**
 * --- PAGINATION ---
 */
if (!empty($settings['pagination_type']) && $settings['pagination_type'] !== 'none' && $query->max_num_pages > 1) {

    // Collect displayed post IDs for AJAX exclusion
    //$current_page_displayed_ids = implode(',', (array) $this->displayed_post_ids);
    $current_page_displayed_ids = implode(',', self::$displayed_post_ids);


    // Serialize widget settings for JS
    $serialize_settings = wp_json_encode($settings);

    echo '<div class="loop-pagination" 
        data-current-page="' . esc_attr($paged) . '"
        data-total-pages="' . esc_attr($query->max_num_pages) . '"
        data-next-page="' . esc_attr($paged + 1) . '"
        data-posts-per-page="' . esc_attr($posts_per_page) . '"
        data-post-type="' . esc_attr($settings['post_type']) . '"
        data-template-id="' . esc_attr($template_id) . '"
        data-query-var="' . esc_attr($current_query_var) . '"
        data-widget-settings="' . esc_attr($serialize_settings) . '"
        data-displayed-ids="' . esc_attr($current_page_displayed_ids) . '"
    >';

    $shorten = (!empty($settings['pagination_numbers_shorten']) && $settings['pagination_numbers_shorten'] === 'yes');

    switch ($settings['pagination_type']) {
        case 'numbers':
            $pagination_args = [
                'current' => $paged,
                'total'   => $query->max_num_pages,
                'type'    => 'plain',
                'prev_text' => '«',
                'next_text' => '»',
            ];

            if ($shorten) {
                $pagination_args['mid_size'] = 0;
                $pagination_args['end_size'] = 1;
            } else {
                $pagination_args['mid_size'] = 2;
                $pagination_args['end_size'] = 2;
            }

            echo paginate_links($pagination_args);
            break;

        case 'prev_next':
            $prev_link = $next_link = '';

            if ($paged > 1) {
                $prev_link = !empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes'
                    ? add_query_arg($current_query_var, $paged - 1, get_permalink())
                    : get_pagenum_link($paged - 1);
            }

            if ($paged < $query->max_num_pages) {
                $next_link = !empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes'
                    ? add_query_arg($current_query_var, $paged + 1, get_permalink())
                    : get_pagenum_link($paged + 1);
            }

            echo '<div class="prev-next-pagination">';
            if ($prev_link) echo '<a class="prev page-numbers" href="' . esc_url($prev_link) . '">« Prev</a>';
            if ($next_link) echo '<a class="next page-numbers" href="' . esc_url($next_link) . '">Next »</a>';
            echo '</div>';
            break;

        case 'numbers_and_prev_next':
            // --- Previous Link ---
            if (in_array($settings['pagination_type'], ['prev_next', 'numbers_and_prev_next'], true) && $paged > 1) {
                $prev_link = !empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes'
                    ? add_query_arg($current_query_var, $paged - 1, get_permalink())
                    : get_pagenum_link($paged - 1);
                echo '<a class="prev page-numbers" href="' . esc_url($prev_link) . '">« Prev</a>';
            }

            // --- Numbers ---
            $pagination_args = [
                'current' => $paged,
                'total'   => $query->max_num_pages,
                'type'    => 'plain',
                'prev_text' => '',
                'next_text' => '',
            ];

            if ($shorten) {
                $pagination_args['mid_size'] = 0;
                $pagination_args['end_size'] = 1;
            } else {
                $pagination_args['mid_size'] = 2;
                $pagination_args['end_size'] = 2;
            }

            echo paginate_links($pagination_args);

            // --- Next Link ---
            if (in_array($settings['pagination_type'], ['prev_next', 'numbers_and_prev_next'], true) && $paged < $query->max_num_pages) {
                $next_link = !empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes'
                    ? add_query_arg($current_query_var, $paged + 1, get_permalink())
                    : get_pagenum_link($paged + 1);
                echo '<a class="next page-numbers" href="' . esc_url($next_link) . '">Next »</a>';
            }

            break;

        case 'load_more_on_click':
            if ($query->max_num_pages > 1) {
                echo '<a href="#" class="load-more-btn"
                    data-next-page="' . esc_attr($paged + 1) . '"
                    data-posts-per-page="' . esc_attr($posts_per_page) . '"
                    data-post-type="' . esc_attr($settings['post_type']) . '"
                    data-template-id="' . esc_attr($template_id) . '"
                    data-query-var="' . esc_attr($current_query_var) . '"
                    data-widget-settings="' . esc_attr($serialize_settings) . '"
                    data-displayed-ids="' . esc_attr($current_page_displayed_ids) . '"
                    data-pagination-type="' . esc_attr($settings['pagination_type']) . '"
                >Load More</a>';

                // --- Spinner below loop ---
                echo '<div class="custom-loop-spinner" style="display: none;">';
                \Elementor\Icons_Manager::render_icon($settings['spinner_icon'], ['aria-hidden' => 'true']);
                echo '<div>';
            }
            break;

        case 'load_more_infinite_scroll':
            if ($query->max_num_pages > 1) {
                echo '<div class="infinite-scroll"
                    data-next-page="' . esc_attr($paged + 1) . '"
                    data-posts-per-page="' . esc_attr($posts_per_page) . '"
                    data-post-type="' . esc_attr($settings['post_type']) . '"
                    data-template-id="' . esc_attr($template_id) . '"
                    data-query-var="' . esc_attr($current_query_var) . '"
                    data-widget-settings="' . esc_attr($serialize_settings) . '"
                    data-displayed-ids="' . esc_attr($current_page_displayed_ids) . '"
                    data-pagination-type="' . esc_attr($settings['pagination_type']) . '"
                ></div>';

                // Spinner placeholder
                //echo '<div class="infinite-scroll-spinner"></div>';
                // --- Spinner below loop ---
                echo '<div class="custom-loop-spinner" style="display: none;">';
                \Elementor\Icons_Manager::render_icon($settings['spinner_icon'], ['aria-hidden' => 'true']);
                echo '<div>';
            }
            break;
    }

    echo '</div>'; // .loop-pagination
}
