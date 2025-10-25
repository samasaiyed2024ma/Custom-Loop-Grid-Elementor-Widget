jQuery(function ($){
    let isLoading = false;

    function loadMorePosts($button){
        if(isLoading) return;

        isLoading = true;

        const nextPage = parseInt($button.data('next-page'));
        const postPerPage = $button.data('posts-per-page');
        const postType = $button.data('post-type');
        const templateId = $button.data('template-id');
        const queryVar = $button.data('query-var');
        const widgetSettings = $button.data('widget-settings');
        const displayedIds = $button.data('displayed-ids').toString().split(',').map(Number);
        const $spinner = $('.infinite-scroll-spinner');

        // $button.text('Loading...'); // text for button
        if($button.data('pagination-type') === 'load_more_infinite_scroll'){
            $spinner.show();
        }


        $.ajax({
            url: load_more.ajaxurl,
            type: 'POST',
            data: {
                action: 'custom_load_more_posts',
                page: nextPage,
                post_per_page: postPerPage,
                post_type: postType,
                template_id: templateId,
                query_var: queryVar,
                widget_settings: widgetSettings,
                displayed_ids: displayedIds,
            },
            success: function (response) {
                if (response.success && response.data.html) {

                    const $newPosts = $(response.data.html).hide();

                    // Append to grid
                    $('.custom-loop-grid').append($newPosts);

                    $newPosts.fadeIn(600);

                    const totalPages = response.data.max_num_pages;

                    if (nextPage >= totalPages) {
                        $button.remove();
                        $spinner.hide();
                    } else {
                        $button.data('next-page', nextPage + 1);
                        $button.data('displayed-ids', displayedIds.concat(response.data.new_ids).join(','));
                        $spinner.hide();
                        isLoading = false;
                    }
                } else {
                    $button.remove();
                    $spinner.hide();
                    isLoading = false;
                }

                isLoading = false;
                $spinner.hide();
            },
            error: function () {
                if($button.data('pagination-type') === 'load_more_on_click'){
                    $button.text('Error, try again');   
                }
                $spinner.hide();
                isLoading = false;
            },
        });
    }

    // Load More Button Click
    $(document).on('click', '.load-more-btn', function(e){
        e.preventDefault();

        const $button = $(this);
        if($button.data('pagination-type') === 'load_more_on_click'){
            loadMorePosts($button);
        }
    });

    // Infinite Scroll
    $(window).on('scroll',  function(){
        const $scroll =  $('.infinite-scroll').first(); ;
        if(!$scroll.length || isLoading) return;

        if($scroll.data('pagination-type') !== 'load_more_infinite_scroll') return;

        const scrollTop = $(window).scrollTop();
        const windowHeight = $(window).height();
        const scrollOffset = $scroll.offset().top;

        if(scrollTop + windowHeight >= scrollOffset - 200){
            loadMorePosts($scroll);
        }
    });

});