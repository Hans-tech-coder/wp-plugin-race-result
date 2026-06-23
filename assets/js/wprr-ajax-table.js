jQuery(document).ready(function ($) {
    const container = $('#wprr-results-data-container');
    const filterForm = $('#wprr-filter-form');
    const distanceSelect = $('#wprr-distance-select');

    // 1. Handle Distance Change
    distanceSelect.on('change', function (e) {
        e.preventDefault();
        fetchResults(1); // Reset to page 1
    });

    // 2. Handle Filter Form Submit
    filterForm.on('submit', function (e) {
        e.preventDefault();
        fetchResults(1); // Reset to page 1
    });

    // 3. Handle Pagination Clicks
    $(document).on('click', '.wprr-pagination a', function (e) {
        e.preventDefault();
        const href = $(this).attr('href');
        let page = 1;

        if (href) {
            // Try to extract wprr_page from URL
            const urlParams = new URLSearchParams(href.split('?')[1]);
            if (urlParams.has('wprr_page')) {
                page = urlParams.get('wprr_page');
            } else if (href.includes('/page/')) {
                // Support for /page/2/ permalink structure if used
                const match = href.match(/\/page\/(\d+)/);
                if (match) page = match[1];
            }
        }

        // OPTIONAL: Only scroll on pagination clicks (usually expected), 
        // but for now, we remove it entirely as requested.
        fetchResults(page);
    });

    function fetchResults(page) {
        // UI Feedback
        container.css('opacity', '0.5');
        container.css('pointer-events', 'none');

        // Gather Data
        const distance = distanceSelect.val();
        const search = filterForm.find('input[name="search"]').val();
        const gender = filterForm.find('select[name="gender"]').val();
        const eventId = filterForm.find('input[name="event_id"]').val() || '';
        const settings = container.data('settings');

        $.ajax({
            url: wprr_modal_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wprr_filter_results',
                wprr_page: page,
                wprr_distance: distance,
                search: search,
                gender: gender,
                event_id: eventId,
                widget_settings: settings
            },
            success: function (response) {
                if (response.success) {
                    // Update Content
                    container.html(response.data.html);

                    // Update URL History
                    if (response.data.new_url) {
                        window.history.pushState({ path: response.data.new_url }, '', response.data.new_url);
                    }

                    // REMOVED: Smooth Scroll to Table Top
                    // The animation block was causing the screen jump/frustration.
                } else {
                    console.error('AJAX Error:', response);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Fail:', error);
            },
            complete: function () {
                container.css('opacity', '1');
                container.css('pointer-events', 'auto');
            }
        });
    }
});
