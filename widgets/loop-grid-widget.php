<?php

namespace MyCustomWidgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Core\Base\Document;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use ElementorPro\Modules\LoopBuilder\Documents\Loop as LoopDocument;
use ElementorPro\Modules\QueryControl\Controls\Template_Query;
use ElementorPro\Modules\QueryControl\Module;
use WP_Query;

if (!defined('ABSPATH')) exit;

class Media_Loop_Grid extends Widget_Base
{
    public static $displayed_post_ids = [];

    public function get_name()
    {
        return 'my_custom_loop_grid';
    }


    public function get_title()
    {
        return esc_html__('Custom Loop Grid');
    }


    public function get_icon()
    {
        return 'eicon-posts-grid';
    }


    public function get_categories()
    {
        return ['general'];
    }


    public function get_keywords()
    {
        return ['media', 'attachment', 'grid', 'image', 'pdf', 'document', 'gallery', 'cached', 'loop', 'posts', 'query', 'dynamic', 'items'];
    }

    /**
     * Get style dependencies
     * Retrieve the list of style dependencies the widget requires
     */
    public function get_style_depends(): array
    {
        return ['widget-loop-grid'];
    }

    protected function _register_controls()
    {
        /**
         * --- CONTENT TAB ---
         * --- TAMPLATE SECTION ---
         */
        $this->start_controls_section(
            'template',
            [
                'label' => esc_html__('Template'),
            ]
        );

        /**
         * --- TEMPLATE SELECTION ---
         */
        $this->add_control(
            'template_id',
            [
                'label' => esc_html__('Choose a template'),
                'type' => Template_Query::CONTROL_ID,
                'label_block' => true,
                'autocomplete' => [
                    'object' => Module::QUERY_OBJECT_LIBRARY_TEMPLATE,
                    'query' => [
                        'post_status' => Document::STATUS_PUBLISH,
                        'meta_query' => [
                            [
                                'key' => Document::TYPE_META_KEY,
                                'value' => LoopDocument::get_type(),
                                'compare' => 'IN',
                            ],
                        ],
                    ],
                ],
                'actions' => [
                    'new' => [
                        'visible' => true,
                        'document_config' => [
                            'type' => LoopDocument::get_type(),
                        ],
                    ],
                    'edit' => [
                        'visible' => true,
                    ]
                ],
                'frontend_available' => true,
            ]
        );

        $this->end_controls_section();

        /**
         * --- LAYOUT SECTION ---
         */
        $this->start_controls_section(
            'layout',
            [
                'label' => esc_html__('Layout'),
            ],
        );

        // --- COLUMNS ---
        $this->add_responsive_control(
            'columns',
            [
                'label' => esc_html__('Columns'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'frontend_available' => true,
                'selectors' => [
                    '{{WRAPPER}} .custom-loop-grid' => 'display: grid; grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ],
        );

        // --- POST PER PAGE ---
        $this->add_control(
            'posts_per_page',
            [
                'label' => esc_html__('Posts Per Page'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'min' => 1,
            ]
        );

        // --- MASONARY ---
        $this->add_control(
            'masonary',
            [
                'label' => esc_html__('Masonary'),
                'type' => Controls_Manager::SWITCHER,
                'label_off' => esc_html__('Off'),
                'label_on' => esc_html__('On'),
                'render_type' => 'ui',
                'frontend_available' => true,
            ],
        );

        // --- EQUAL HEIGHT ---
        $this->add_control(
            'equal_height',
            [
                'label' => esc_html__('Equal height'),
                'type' => Controls_Manager::SWITCHER,
                'label_off' => esc_html__('Off'),
                'label_on' => esc_html__('On'),
                'selectors' => [
                    '{{WRAPPER}} .custom-loop-grid' => 'grid-auto-rows: 1fr',
                ],
            ],
        );

        $this->end_controls_section();

        /**
         * --- QUERY SECTION ---
         */
        $this->start_controls_section(
            'query',
            [
                'label' => esc_html__('Query'),
            ],
        );

        // -- POST TYPE (SOURCE) ---
        $this->add_control(
            'post_type',
            [
                'label' => esc_html__('Source'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_post_type(),
                'default' => 'post'
            ]
        );

        // -- MEDIA TYPE ---
        $this->add_control(
            'media_type',
            [
                'label' => esc_html__('Media type'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'options' => [
                    'all' => esc_html__('All media type'),
                    'image' => esc_html__('Images (JPEG, PNG, GIF)'),
                    'application/pdf' => esc_html__('PDF Documents'),
                    'audio' => esc_html__('Audio Files'),
                    'video' => esc_html__('Video Files'),
                ],
                'condition' => [
                    'post_type' => 'attachment',
                ]
            ]
        );

        // -- INCLUDE/EXCLUDE TABS ---
        $this->start_controls_tabs('include/exclude');

        // -- INCLUDE TAB ---
        $this->start_controls_tab(
            'include_tab',
            [
                'label' => esc_html__('Include'),
            ]
        );

        // -- INCLUDE BY SELECTION ---
        $this->add_control(
            'include_by',
            [
                'label' => esc_html__('Include By'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'options' => [
                    'taxonomy' => esc_html__('Taxonomy'),
                    'meta_key' => esc_html__('Meta_key'),
                ],
            ],
        );

        // -- INCLUDE BY TAXONOMY ---
        $this->add_control(
            'include_taxonomy',
            [
                'label' => esc_html__('Select taxonomy'),
                'type' => Controls_Manager::SELECT,
                'options' => get_taxonomies(['public' => true], 'names'),
                'condition' => [
                    'include_by' => 'taxonomy',
                ],
            ],
        );

        // -- INCLUDE BY META KEY ---
        $this->add_control(
            'include_meta_key',
            [
                'label' => esc_html__('Meta key'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_meta_keys(),
                'condition' => [
                    'include_by' => 'meta_key',
                ]
            ],
        );

        // -- INCLUDE BY META VALUE ---
        $this->add_control(
            'include_meta_value',
            [
                'label' => esc_html__('Meta value'),
                'type' => Controls_Manager::TEXT,
                'condition' => [
                    'include_by' => 'meta_key',
                    'meta_key!' => '',
                ],
            ]
        );

        $this->end_controls_tab();

        // -- EXCLUDE TAB ---
        $this->start_controls_tab(
            'exclude_tab',
            [
                'label' => esc_html__('Exclude'),
            ]
        );

        // -- EXCLUDE BY SELECTION ---
        $this->add_control(
            'exclude_by',
            [
                'label' => esc_html__('Exclude By'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'options' => [
                    'taxonomy' => esc_html__('Taxonomy'),
                    'meta_key' => esc_html__('Meta key'),
                    'manual_selection' => esc_html__('Manual selection'),
                ],
            ],
        );

        // -- EXCLUDE BY TAXONOMY ---
        $this->add_control(
            'exclude_taxonomy',
            [
                'label' => esc_html__('Select taxonomy'),
                'type' => Controls_Manager::SELECT,
                'options' => get_taxonomies(['public' => true], 'names'),
                'condition' => [
                    'exclude_by' => 'taxonomy',
                ],
            ],
        );

        // -- EXCLUDE BY META KEY ---
        $this->add_control(
            'exclude_meta_key',
            [
                'label' => esc_html__('Meta key'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_meta_keys(),
                'condition' => [
                    'exclude_by' => 'meta_key',
                ]
            ],
        );

        // -- EXCLUDE BY META VALUE ---
        $this->add_control(
            'exclude_meta_value',
            [
                'label' => esc_html__('Meta value'),
                'type' => Controls_Manager::TEXT,
                'condition' => [
                    'exclude_by' => 'meta_key',
                    'meta_key!' => '',
                ],
            ]
        );

        // --- EXCLUDE BY MANUAL SELECTION ---
        $this->add_control(
            'exclude_by_post_title',
            [
                'label' => esc_html__('Write post type'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'condition' => [
                    'exclude_by' => 'manual_selection',
                ],
            ],
        );

        // --- AVOID DUPLICATE ---
        $this->add_control(
            'avoid_duplicates',
            [
                'label' => esc_html__('Avoid Duplicates'),
                'type' => Controls_Manager::SWITCHER,
                'description' => esc_html__('Set to Yes to avoid duplicate posts from showing up. This only effects the frontend.'),
            ],
        );

        // --- OFFSET ---
        $this->add_control(
            'offset',
            [
                'label' => esc_html__('Offset'),
                'type' => Controls_Manager::NUMBER,
                'default' => '0',
                'description' => esc_html__("Use this setting to skip over posts (e.g. '2' to skip over 2 posts)."),
            ],
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        // --- DIVIDER BEFORE DATE CONTROLS ---
        $this->add_control(
            'divider_1',
            [
                'type' => Controls_Manager::DIVIDER,
            ],
        );

        // --- DATE ---
        $this->add_control(
            'date',
            [
                'label' => esc_html__('Date'),
                'type' => Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all' => esc_html__('All'),
                    'past_day' => esc_html__('Past Day'),
                    'past_week' => esc_html__('Past Week'),
                    'past_month' => esc_html__('Past Month'),
                    'past_quarter' => esc_html__('Past Quarter'),
                    'past_year' => esc_html__('Past Year'),
                    'custom' => esc_html__('Custom'),
                ],
            ],
        );

        // --- BEFORE DATE ---
        $this->add_control(
            'before_date',
            [
                'label' => esc_html__('Before'),
                'type' => Controls_Manager::DATE_TIME,
                'placeholder' => esc_html__('Choose'),
                'label_block' => false,
                'condition' => [
                    'date' => 'custom',
                ],
                'description' => esc_html__('Setting a ‘Before’ date will show all the posts published until the chosen date (inclusive).'),
            ],
        );

        // --- AFTER DATE ---
        $this->add_control(
            'after_date',
            [
                'label' => esc_html__('After'),
                'type' => Controls_Manager::DATE_TIME,
                'placeholder' => esc_html__('Choose'),
                'label_block' => false,
                'condition' => [
                    'date' => 'custom',
                ],
                'description' => esc_html__('Setting an ‘After’ date will show all the posts published since the chosen date (inclusive).'),
            ],
        );

        // --- ORDER BY ---
        $this->add_control(
            'order_by',
            [
                'label' => esc_html__('Order By'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => esc_html__('Date'),
                    'title' => esc_html__('Title'),
                    'random' => esc_html__('Random'),
                    'ID' => esc_html__('Post ID'),
                    'meta_value' => esc_html__('Meta value'),
                ],
            ],
        );

        // --- ORDER TYPE ---
        $this->add_control(
            'order_type',
            [
                'label' => esc_html__('Order'),
                'type' => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'ASC' => esc_html__('ASC'),
                    'DESC' => esc_html__('DESC'),
                ],
                'condition' => [
                    'order_by!' => 'random',
                ]
            ],
        );

        $this->end_controls_section();

        /**
         * --- PAGINATION SECTION ---
         */
        $this->start_controls_section(
            'pagination_section',
            [
                'label' => esc_html__('Pagination'),
            ],
        );

        // --- PAGINATION TYPE ---
        $this->add_control(
            'pagination_type',
            [
                'label' => esc_html__('Pagination'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    '' => esc_html__('None'),
                    'numbers' => esc_html__('Numbers'),
                    'prev_next' => esc_html__('Previous/Next'),
                    'numbers_and_prev_next' => esc_html__('Numbers + Prev/Next'),
                    'load_more_on_click' => esc_html__('Load on Click'),
                    'load_more_infinite_scroll' => esc_html__('Infinite Scroll'),
                ],
            ],
        );

        // --- PAGINATION PAGE LIMIT ---
        $this->add_control(
            'pagination_page_limit',
            [
                'label' => esc_html__('Page Limit'),
                'default' => '5',
                'condition' => [
                    'pagination_type!' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll',
                        '',
                    ],
                ],
            ],
        );

        // --- PAGINATION NUMBER SHORTEN ---
        $this->add_control(
            'pagination_numbers_shorten',
            [
                'label' => esc_html__('Shorten'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'numbers_and_prev_next',
                    ],
                ],
            ],
        );

        // --- PREVIOUS LABEL ---
        $this->add_control(
            'pagination_prev_label',
            [
                'label' => esc_html__('Previous Label'),
                'dynamic' => [
                    'active' => true,
                ],
                'default' => esc_html__('&laquo; Previous'),
                'condition' => [
                    'pagination_type' => [
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
            ]
        );

        // --- NEXT LABEL ---
        $this->add_control(
            'pagination_next_label',
            [
                'label' => esc_html__('Next Label'),
                'dynamic' => [
                    'active' => true,
                ],
                'default' => esc_html__('Next &raquo;'),
                'condition' => [
                    'pagination_type' => [
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
            ]
        );

        // --- ALIGN PAGINATION ---
        $this->add_control(
            'pagination_align',
            [
                'label' => esc_html__('Alignment'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'center',
                'selectors' => [
                    '{{WAPPER}} .loop-pagination' => 'text-align: {{VALUE}}',
                ],
                'condition' => [
                    'pagination_type!' => 'load_more_infinite_scroll',
                ],
            ],
        );

        // --- PAGINATION LOAD TYPE ---
        $this->add_control(
            'pagination_load_type',
            [
                'label' => esc_html__('Load Type'),
                'type' => Controls_Manager::SELECT,
                'default' => 'page_reload',
                'options' => [
                    'page_reload' => esc_html__('Page Reload'),
                    'ajax' => esc_html__('AJAX'),
                ],
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
                'separator' => 'before',
                'frontend_available' => true,
            ],
        );

        // --- AUTO SCROLL ---
        $this->add_control(
            'auto_scroll',
            [
                'label' => esc_html__('Autoscroll'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
                'condition' => [
                    'pagination_load_type' => 'ajax',
                ],
                'frontend_available' => true,
            ]
        );

        // --- AUTO SCROLL OFFSET ---
        $this->add_control(
            'auto_scroll_offset',
            [
                'label' => esc_html__('Autoscroll Offset'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'selectors' => [
                    '{{WRAPPER}}' => '--auto-scroll-offset: {{VALUE}};',
                ],
                'condition' => [
                    'pagination_load_type' => 'ajax',
                    'auto_scroll' => 'yes',
                ],
            ],
        );

        // --- INDIVIDUAL PAGINATION ---
        $this->add_control(
            'individual_pagination',
            [
                'label' => esc_html__('Individual Pagination'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('On'),
                'label_off' => esc_html__('Off'),
                'default' => '',
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
                'separator' => 'before',
            ],
        );

        // --- PAGINATION HANDLE MESSAGE ---
        $this->add_control(
            'pagination_handle_message',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => esc_html__('For multiple Posts Widgets on the same page, toggle this on to control the pagination for each individually. Note: It affects the page\'s URL structure.'),
                'content_classes' => 'custom-pagination-individual-desription',
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
            ],
        );

        // --- LOAD MORE SPINNER ---
        $this->add_control(
            'load-more-spinner',
            [
                'label' => esc_html__('Spinner'),
                'type' => Controls_Manager::ICONS,
                'fa4compatibility' => 'icon',
                'default' => [
                    'value' => 'fas fa-spinner',
                    'library' => 'fa-solid',
                ],
                'exclude_inline_options' => ['svg'],
                'recommended' => [
                    'fa-solid' => [
                        'spinner',
                        'cog',
                        'sync',
                        'sync-alt',
                        'asterisk',
                        'circle-notch',
                    ],
                ],
                'skin' => 'inline',
                'label_block' => false,
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll',
                    ],
                ],
                'frontend_available' => true,
            ],
        );

        // --- LOAD MORE BUTTON HEADING ---
        $this->add_control(
            'load_more_button_heading',
            [
                'label' => esc_html__('Button'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'pagination_type' => 'load_more_on_click',
                ],
            ],
        );

        // --- LOAD MORE BUTTON CONTROL ---
        $this->add_control(
            'load_more_button_text',
            [
                'label' => esc_html__('Button Text'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Load More'),
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],

                'dynamic' => [
                    'active' => true,
                ],
            ],
        );

        // --- LOAD MORE BUTTON ICON ---
        $this->add_control(
            'load_more_button_icon',
            [

                'label' => esc_html__('Icon'),
                'type' => Controls_Manager::ICONS,
                'fa4compatibility' => 'icon',
                'exclude_inline_options' => ['svg'],
                'skin' => 'inline',
                'label_block' => false,
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
                'frontend_available' => true,
            ],
        );

        // --- NO MORE POSTS MESSAGE ---
        $this->add_control(
            'no_more_posts_message_heading',
            [
                'label' => esc_html__('No More Posts Message'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll',
                    ],
                ],
                'dynamic' => [
                    'active' => true,
                ]
            ],
        );

        // --- NO MORE POSTS MESSAGE ALIGNMENT ---
        $this->add_responsive_control(
            'no_more_posts_message_alignment',
            [
                'label' => esc_html__('Alignment'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right'),
                        'icon' => 'eicon-text-align-right',
                    ],
                    'justify' => [
                        'title' => esc_html__('Justified'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}}' => '--load-more-message-alignment: {{VALUE}};',
                ],
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll',
                    ],
                ],
            ],
        );

        // --- NO MORE POSTS MESSAGE SWITCHER ---
        $this->add_control(
            'no_more_posts_message_switcher',
            [
                'label' => esc_html__('Custom Message'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll',
                    ],
                ],
            ],
        );

        // --- NO MORE POSTS CUSTOM MESSAGE ---
        $this->add_control(
            'no_more_posts_custom_message',
            [
                'label' => esc_html__('No more posts message'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('No more posts to show'),
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll',
                    ],
                    'no_more_posts_message_switcher' => 'yes',
                ],
                'label_block' => true,
                'dynamic' => [
                    'active' => true,
                ],
            ],
        );

        $this->end_controls_section();

        /**
         * --- STYLE TAB --- 
         * --- LAYOUT SECTION ---
         */
        $this->start_controls_section(
            'style_section',
            [
                'label' => esc_html__('Layout'),
                'tab' => Controls_Manager::TAB_STYLE,
            ],
        );

        // --- COLUMN GAPS ---
        $this->add_responsive_control(
            'column_gaps',
            [
                'label' => esc_html__('Gap between columns'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em', 'rem'],
                'default' => [
                    'size' => '20',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .custom-loop-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
                ],
            ],
        );

        // --- ROW GAPS ---
        $this->add_responsive_control(
            'row_gaps',
            [
                'label' => esc_html__('Gap between rows'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em', 'rem'],
                'default' => [
                    'size' => '20',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .custom-loop-grid' => 'row-gap: {{SIZE}}{{UNIT}};',
                ],
            ],
        );

        $this->end_controls_section();

        /**
         * --- PAGINATION SECTION ---
         */
        $this->start_controls_section(
            'pagination_style',
            [
                'label' => esc_html__('Pagination'),
                'tab' => Controls_Manager::TAB_STYLE,
            ],
        );

        // --- PAGINATION TYPOGRAPHY ---
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'pagination_typography',
                'label' => esc_html__('Typography'),
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
                'selectors' => '{{WRAPPER}} ',
            ],
        );

        // --- PAGINATION TEXT COLORS ---
        $this->add_control(
            'pagination_color_heading',
            [
                'label' => esc_html__('Colors'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
            ],
        );

        // --- COLOR TABS ---
        $this->start_controls_tabs(
            'pagination_colors',
            [
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
            ]
        );

        // --- NORMAL COLOR TAB ---
        $this->start_controls_tab(
            'normal_color_tab',
            [
                'label' => esc_html__('Normal'),
            ],
        );

        $this->add_control(
            'normal_color',
            [
                'label' => esc_html__('Color'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .loop-pagination .page-numbers' => 'color: {{VALUE}}',
                ],
            ],
        );

        // --- END NORMAL COLOR TAB --- 
        $this->end_controls_tab();

        // --- HOVER COLOR TAB ---
        $this->start_controls_tab(
            'hover_color_tab',
            [
                'label' => esc_html__('Hover'),
            ],
        );

        $this->add_control(
            'hover_color',
            [
                'label' => esc_html__('Color'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .loop-pagination .page-numbers:hover' => 'color: {{VALUE}}',
                ],
            ],
        );

        // --- END HOVER COLOR TAB --- 
        $this->end_controls_tab();

        // --- ACTIVE COLOR TAB ---
        $this->start_controls_tab(
            'active_color_tab',
            [
                'label' => esc_html__('Active'),
            ],
        );

        $this->add_control(
            'active_color',
            [
                'label' => esc_html__('Color'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .loop-pagination .current' => 'color: {{VALUE}}',
                ],
            ],
        );

        // --- END ACTIVE COLOR TAB --- 
        $this->end_controls_tab();

        $this->end_controls_tabs();

        // --- PAGINATION SPACE BETWEEN ---
        $this->add_responsive_control(
            'pagination_space_between',
            [
                'label' => esc_html__('Space Between'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em', 'rem'],
                'default' => [
                    'size' => 5,
                    'unit' => 'px',
                ],
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .loop-pagination .page-numbers:not(:last-child)' => 'margin-right: {{SIZE}}{{UNIT}};',
                    // Optional: avoid double spacing on the last item
                ],
            ],
        );

        // --- PAGINATION SPACING ---
        $this->add_responsive_control(
            'pagination_spacing',
            [
                'label' => esc_html__('Spacing'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em', 'rem'],
                'default' => [
                    'size' => 10,
                    'unit' => 'px',
                ],
                'condition' => [
                    'pagination_type' => [
                        'numbers',
                        'prev_next',
                        'numbers_and_prev_next',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .loop-pagination .page-numbers' => 'display: inline-block; padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // --- BUTTON HEADING ---
        $this->add_control(
            'button_heading',
            [
                'label' => esc_html__('Button'),
                'type' => Controls_Manager::HEADING,
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
            ],
        );

        // --- BUTTON TYPOGRAPHY ---
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'label' => esc_html__('Typography'),
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
                'selector' => '{{WRAPPER}} .load-more-btn',
            ],
        );

        // --- BUTTON TEXT SHADOW ---
        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'button_text_shadow',
                'label' => esc_html__('Text Shadow'),
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
                'selector' => '{{WRAPPER}} .load-more-btn',
            ],
        );

        // --- START NORMAL/HOVER BUTTON STYLE TABS ---
        $this->start_controls_tabs(
            'button_style',
            [
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
            ],
        );

        // --- BUTTON NORMAT STYLE ---
        $this->start_controls_tab(
            'button_normal_style',
            [
                'label' => esc_html__('Normal'),
            ],
        );

        // --- BUTTON NORMAL COLOR ---
        $this->add_control(
            'button_normal_text_color',
            [
                'label' => esc_html__('Text Color'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn' => 'color: {{VALUE}};',
                ],
            ],
        );

        // --- BUTTON NORMAL BACKGROUND COLOR ---
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'button_normal_background_type',
                'label' => esc_html__('Background Type'),
                'types'    => ['classic', 'gradient'],
                'exclude'  => ['image'], // hides the image upload option
                'selector' => '{{WRAPPER}} .load-more-btn',
            ],
        );

        // --- BUTTON NORMAL BOX SHADOW ---
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_normal_box_shadow',
                'label' => esc_html__('Box Shadow'),
                'selector' => '{{WRAPPER}} .load-more-btn',
            ],
        );

        // --- END BUTTON NORMAL STYLE ---
        $this->end_controls_tab();

        // --- BUTTON HOVER STYLE ---
        $this->start_controls_tab(
            'button_hover_style',
            [
                'label' => esc_html__('Hover'),
            ],
        );

        // --- BUTTON HOVER COLOR ---
        $this->add_control(
            'button_hover_text_color',
            [
                'label' => esc_html__('Text Color'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn:hover' => 'color: {{VALUE}};',
                ],
            ],
        );

        // --- BUTTON HOVER BACKGROUND COLOR ---
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'button_hover_background_type',
                'label' => esc_html__('Background Type'),
                'types'    => ['classic', 'gradient'],
                'exclude'  => ['image'], // hides the image upload option
                'selector' => '{{WRAPPER}} .load-more-btn:hover',
            ],
        );

        // --- BUTTON HOVER BORDER COLOR ---
        $this->add_control(
            'button_hover_border_color',
            [
                'label' => esc_html__('Border Color'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn:hover' => 'border-color: {{VALUE}};',
                ]
            ]
        );

        // --- BUTTON HOVER BOX SHADOW ---
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_hover_box_shadow',
                'label' => esc_html__('Box Shadow'),
                'selector' => '{{WRAPPER}} .load-more-btn:hover',
            ],
        );

        // --- END BUTTON HOVER STYLE ---
        $this->end_controls_tab();

        // --- END NORMAL/HOVER BUTTON STYLE TABS ---
        $this->end_controls_tabs();

        // --- BUTTON BORDER TYPE ---
        $this->add_control(
            'button_border_type',
            [
                'label' => esc_html__('Border Type'),
                'type' => Controls_Manager::SELECT,
                'default' => 'default',
                'options' => [
                    'default' => esc_html__('Default'),
                    'none' => esc_html__('None'),
                    'solid' => esc_html__('Solid'),
                    'double' => esc_html__('Double'),
                    'dotted' => esc_html__('Dotted'),
                    'dashed' => esc_html__('Dashed'),
                    'groove' => esc_html__('Groove'),
                ],
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
                'separator' => 'before',
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn' => 'border-style: {{VALUE}};',
                ]
            ],
        );

        // --- BUTTON BORDER WIDTH ---
        $this->add_responsive_control(
            'button_border_width',
            [
                'label' => esc_html__('Border Width'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'unit' => 'px',
                ],
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                    'button_border_type!' => ['none', 'default'],
                ],
                'frontend_available' => true,
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ],
        );

        // --- BUTTON BORDER COLOR ---
        $this->add_control(
            'button_border_color',
            [
                'label' => esc_html__('Border Color'),
                'type' => Controls_Manager::COLOR,
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn' => 'border-color: {{VALUE}};',
                ]
            ]
        );

        // --- BUTTON BORTDER RADIUS ---
        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => esc_html__('Border Radius'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'unit' => 'px',
                ],
                'frontend_available' => true,
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ],
        );

        // --- BUTTON PADDING ---
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => esc_html__('Padding'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'unit' => 'px',
                ],
                'frontend_available' => true,
                'seperator' => 'before',
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ],
        );


        // --- BUTTON MARGIN ---
        $this->add_responsive_control(
            'button_margin',
            [
                'label' => esc_html__('Margin'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'unit' => 'px',
                ],
                'frontend_available' => true,
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .load-more-btn' => 'display: inline-block; margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // --- NO MORE POSTS MESSAGE ---
        $this->add_control(
            'no_more_posts_message_style_heading',
            [
                'label' => esc_html__('No More Posts Message'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll'
                    ],
                ],
            ]
        );

        // --- NO MORE POSTS MESSAGE TYPOGRAPHY---
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'no_more_posts_message_typography',
                'label' => esc_html__('Typography'),
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll'
                    ],
                ],
            ]
        );

        // --- NO MORE POSTS MESSAGE COLOR---
        $this->add_control(
            'no_more_posts_message_color',
            [
                'label' => esc_html__('Color'),
                'type' => Controls_Manager::COLOR,
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll'
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .no_more_posts_message' => 'color: {{VALUE}};',
                ],
            ],
        );

        // --- NO MORE POSTS MESSAGE SPINNER COLOR---
        $this->add_control(
            'spinner_color',
            [
                'label' => esc_html__('Spinner Color'),
                'type' => Controls_Manager::COLOR,
                'separator' => 'before',
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll'
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .spinner i' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .spinner svg' => 'fill: {{VALUE}}',
                ],
            ],
        );

        // --- NO MORE POSTS MESSAGE SPACING ---
        $this->add_responsive_control(
            'no_more_posts_message_spacing',
            [
                'label' => esc_html__('Spacing'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'default' => [
                    'unit' => 'px',
                ],
                'separator' => 'before',
                'condition' => [
                    'pagination_type' => [
                        'load_more_on_click',
                        'load_more_infinite_scroll'
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} '
                ],
            ],
        );


        $this->end_controls_section();
    }



    /**
     * --- Get all post types ---
     */
    private function get_post_type()
    {
        // Get all public post types
        $post_types = get_post_types(['public' => true], 'objects');
        $post_type_options = [];

        foreach ($post_types as $slug => $pt) {
            $post_type_options[$slug] = $pt->label;
        }

        return $post_type_options;
    }

    /**
     * --- Get all meta keys from the database ---
     */
    private function get_meta_keys()
    {
        // Access wordpress database object
        global $wpdb;

        // Fetch all unique meta keys
        $meta_keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta}");

        //Creates an associative array where keys = values
        $meta_options = array_combine($meta_keys, $meta_keys ?: []);

        return $meta_options;
    }

    /**
     * --- Get the allowed MIME types based on selected media types in widget settings ---
     */
    private function get_attachment_mime_type($settings)
    {
        // Get selected media types, default to 'all'
        $media_type = (array) ($settings['media_type'] ?? ['all']);

        // If 'all' is selected, return null (no filter)
        if (in_array('all', $media_type)) {
            return null;
        }

        // Map media types to actual MIME types
        $mime_map = [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'application/pdf' => ['application/pdf'],
            'audio' => ['audio/mpeg', 'audio/wav'],
            'video' => ['video/mp4', 'video/webm', 'video/ogg'],
        ];

        $mime_types = [];
        foreach ($media_type as $type) {
            if (isset($mime_map[$type])) {
                $mime_types = array_merge($mime_types, $mime_map[$type]);
            }
        }

        return !empty($mime_types) ? $mime_types : null;
    }

    /**
     * --- Retrieve all term slugs for a given taxonomy ---
     */
    private function get_taxonomy_terms($taxonomy)
    {
        // Fetch all terms for the specified taxonomy.
        $terms = get_terms(['taxonomy' => $taxonomy,  'hide_empty' => false]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        // Extract and return only the term slugs from the term objects
        // wp_list_pluck() creates an array of 'slug' values from the list of term objects
        return wp_list_pluck($terms, 'slug');
    }

    /**
     * Retrieve a post ID by its exact title.
     */
    private function get_post_by_title($title)
    {
        // Create a new WP_Query to search posts by title.
        $query = new \WP_Query([
            'post_type'      => get_post_types(), // or specify e.g. 'post'
            'title'          => $title, // this is not directly supported, so use 's' + exact match below
            'posts_per_page' => 1,
            'fields'         => 'ids',
            's'              => $title, // search by title/content
        ]);

        // Ensure exact title match
        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                if (strcasecmp(get_the_title($post_id), $title) === 0) {
                    return $post_id;
                }
            }
        }

        // Return null if no matching post title is found
        return null;
    }

    /**
     * Render the widget output on the frontend
     */
    protected function render()
    {
        include plugin_dir_path(__FILE__) . '/render-function.php';
    }
}
