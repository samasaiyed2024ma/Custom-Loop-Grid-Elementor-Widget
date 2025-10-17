<?php

$settings = $this->get_settings_for_display();
$template_id = $settings['template_id'];
$widget_id = $this->get_id();
$query_var = 'paged_' . $widget_id;

/**
 * POSTS PER PAGE
 */
$posts_per_page = $settings['posts_per_page'] ?? 6;


/**
 * ----- DETERMINE CURRENT PAGE NUMBER FOR PAGINATION -----
 * CHECK IF THE 'individual_pagination' setting is enable
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
 * DETERMINE QUERY VARIABLE TO USE FOR AJAX
 * 'individual_pagination' pagination ON -> use unique widget query var
 * 'individual_pagination' pagination OFF -> use global 'paged' query var
 */
if (!empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes') {
    $current_query_var = $query_var; // Widget specific query variable
} else {
    $current_query_var = 'paged'; // Global wordpress pagination var
}


/**
 * ----- DETERMINE CURRENT PAGE NUMBER -----
 * Use the selected query variable to get the current page
 * max(1, ...) ensures page numner is always at least 1
 */
$paged = max(1, get_query_var($current_query_var, 1));


/**
 * ----- BASE WP_QUERY ARGUMENTS -----
 */
$args = [
    'post_type' => $settings['post_type'] ?? 'post',
    'posts_per_page' => $posts_per_page,
    'orderby' => $settings['order_by'] ?? 'date',
    'order'          => $settings['order_type'] ?? 'DESC',
    'paged'          => $paged,
];


/**
 * ----- HANDLE POST OFFSET -----
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
 * ----- HANDLE 'attachment' POST TYPE -----
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
 * ----- AVOID DUPLICATE POSTS -----
 */
if (!empty($settings['avoid_duplicates']) && $settings['avoid_duplicates'] == 'yes' && !empty(self::$displayed_post_ids)) {
    // Exclude these posts from the current query
    // 'post__not_in' is a WordPress query argument that prevents posts
    // with the specified IDs from appearing in the results
    // (self::$displayed_post_ids) stores all post IDs that have already been output.
    $args['post__not_in'] = $this->displayed_post_ids;
}


/**
 * ----- TAXONOMY FILTER -----
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
 * ----- META KEY FILTER -----
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
 * -----EXCLUDE BY MANUAL SELECTION -----
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
 * ----- EXECUTE QUERY -----
 */
$query = new WP_Query($args);

echo '<div id="custom-loop' . $widget_id . '" class="custom-loop-grid-wrapper">';

if ($query->have_posts()) {
    echo '<div class="custom-loop-grid">';
    while ($query->have_posts()) {
        $query->the_post();

        if ($template_id) {
            \Elementor\Plugin::instance()->db->switch_to_post(get_the_ID());
            echo \Elementor\plugin::instance()->frontend->get_builder_content_for_display($template_id);
            \Elementor\Plugin::instance()->db->restore_current_post();
        }
    }

    echo '</div>';
}

wp_reset_postdata();
echo '</div>';

/**
 * ----- PAGINATION -----
 */
if (!empty($settings['pagination_type']) && $settings['pagination_type'] !== 'none' && $query->max_num_pages > 1) {

    // Collect displayed post IDs for AJAX exclusion
    $current_page_displayed_ids = implode(',', (array) $this->displayed_post_ids);

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
                >Load More</a>';
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
                ></div>';

                // Spinner placeholder
                echo '<div class="infinite-scroll-spinner" style="display:none; text-align:center; margin:20px 0;">
                    <span class="spinner">Loading...</span>
                </div>';
            }
            break;
    }

    echo '</div>'; // .loop-pagination
}


// // Pagination
// if (!empty($settings['pagination']) && $settings['pagination'] !== 'none' && $query->max_num_pages > 1) {


// // Collect all IDs displayed on the current page for exclusion in the next request
// $current_page_displayed_ids = implode(',', self::$displayed_post_ids);

// // Serialize the full settings object
// $serialize_settings = wp_json_encode($settings);

// echo '<div class="loop-pagination"
    // data-current-page="' . esc_attr($paged) . '"
    // data-total-pages="' . esc_attr($query->max_num_pages) . '"
    // data-next-page="' . esc_attr($paged + 1) . '"
    // data-posts-per-page="' . esc_attr($posts_per_page) . '"
    // data-post-type="' . esc_attr($settings['post_type']) . '"
    // data-template-id="' . esc_attr($template_id) . '"
    // data-query-var="' . esc_attr($current_query_var) . '"
    // data-widget-settings="' . esc_attr($serialize_settings) . '"
    // data-displayed-ids="' . esc_attr($current_page_displayed_ids) . '"
    //>';

// $big = 999999999;
// $base = str_replace($big, '%#%', esc_url(get_pagenum_link($big)));
// $format = '';
// $shorten = (!empty($settings['shorten']) && $settings['shorten'] === 'yes');

// $pagination_args = [
// 'base' => $base,
// 'format' => $format,
// 'current' => $paged,
// 'total' => $query->max_num_pages,
// 'type' => 'plain',
// ];

// //Handle different pagination types
// switch ($settings['pagination']) {
// case 'numbers':
// $pagination_args['prev_text'] = '«';
// $pagination_args['next_text'] = '»';
// if ($shorten) {
// $pagination_args['mid_size'] = 0;
// $pagination_args['end_size'] = 1;
// } else {
// $pagination_args['mid_size'] = 2;
// $pagination_args['end_size'] = 2;
// }

// echo paginate_links($pagination_args);
// break;

// case 'previous_next':
// // Determine prev/next URLs
// $prev_link = $next_link = '';

// $pagination_args['prev_text'] = __('« Prev');
// $pagination_args['next_text'] = __('Next »');

// if ($paged > 1) {
// // Handle individual pagination query_var
// if (!empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes') {
// $prev_link = add_query_arg($current_query_var, $paged - 1, get_permalink());
// } else {
// $prev_link = get_pagenum_link($paged - 1);
// }
// }

// if ($paged < $query->max_num_pages) {
    // if (!empty($settings['individual_pagination']) && $settings['individual_pagination'] === 'yes') {
    // $next_link = add_query_arg($current_query_var, $paged + 1, get_permalink());
    // } else {
    // $next_link = get_pagenum_link($paged + 1);
    // }
    // }

    // echo '<div class="prev-next-pagination">';
        // if ($prev_link) {
        // echo '<a class="prev page-numbers" href="' . esc_url($prev_link) . '">' . $pagination_args['prev_text'] . '</a>';
        // }
        // if ($next_link) {
        // echo '<a class="next page-numbers" href="' . esc_url($next_link) . '">' . $pagination_args['next_text'] . '</a>';
        // }
        // echo '</div>';
    // break;

    // case 'number_previous_next':
    // $pagination_args['prev_text'] = __('« Prev');
    // $pagination_args['next_text'] = __('Next »');
    // if ($shorten) {
    // $pagination_args['mid_size'] = 0;
    // $pagination_args['end_size'] = 1;
    // } else {
    // $pagination_args['mid_size'] = 1;
    // $pagination_args['end_size'] = 2;
    // }
    // echo paginate_links($pagination_args);
    // break;

    // case 'load_on_click':
    // if ($query->max_num_pages > 1) {

    // echo '<a href="#" class="load-more-btn"
        // data-next-page="' . esc_attr($paged + 1) . '"
        // data-posts-per-page="' . esc_attr($posts_per_page) . '"
        // data-post-type="' . esc_attr($settings['post_type']) . '"
        // data-template-id="' . esc_attr($template_id) . '"
        // data-query-var="' . esc_attr($current_query_var) . '"
        // data-widget-settings="' . esc_attr($serialize_settings) . '"
        // data-displayed-ids="' . esc_attr($current_page_displayed_ids) . '"
        //> Load More </a>';
    // }
    // break;

    // case 'infinite_scroll':
    // if ($query->max_num_pages > 1) {

    // echo '<div class="infinite-scroll"
        // data-next-page="' . esc_attr($paged + 1) . '"
        // data-posts-per-page="' . esc_attr($posts_per_page) . '"
        // data-post-type="' . esc_attr($settings['post_type']) . '"
        // data-template-id="' . esc_attr($template_id) . '"
        // data-query-var="' . esc_attr($current_query_var) . '"
        // data-widget-settings="' . esc_attr($serialize_settings) . '"
        // data-displayed-ids="' . esc_attr($current_page_displayed_ids) . '"
        //> </div>';

    // // Spinner placeholder
    // echo '<div class="infinite-scroll-spinner" style="display:none; text-align:center; margin:20px 0;">
        // <span class="spinner">Loading...</span>
        // </div>';
    // }
    // break;
    // }

    // echo '</div>';
    // }

    // wp_reset_postdata();
    // } else {
    // echo 'No more posts.';
    // }

    // echo '</div>';