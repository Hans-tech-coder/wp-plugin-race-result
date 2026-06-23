<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core Plugin Class
 */
class WPRR_Core
{
    public function __construct()
    {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants()
    {
        if (!defined('WPRR_VERSION')) {
            define('WPRR_VERSION', '1.0.0');
        }
        if (!defined('WPRR_PATH')) {
            define('WPRR_PATH', plugin_dir_path(dirname(__FILE__)));
        }
        if (!defined('WPRR_URL')) {
            define('WPRR_URL', plugin_dir_url(dirname(__FILE__)));
        }
        if (!defined('WPRR_DEFAULT_WINNERS')) {
            define('WPRR_DEFAULT_WINNERS', 3);
        }
    }

    private function includes()
    {
        require_once WPRR_PATH . 'includes/class-wprr-db.php';
        require_once WPRR_PATH . 'includes/class-wprr-modal-renderer.php';
        require_once WPRR_PATH . 'includes/class-wprr-table-renderer.php';
        require_once WPRR_PATH . 'includes/class-wp-race-results-admin.php';
        require_once WPRR_PATH . 'includes/csv-import-handler.php';

        // Initialize Admin
        if (is_admin()) {
            $admin = new WP_Race_Results_Admin('wp-race-results', WPRR_VERSION);
            $admin->init();
        }

        // Initialize Elementor
        if (did_action('elementor/loaded')) {
            require_once WPRR_PATH . 'elementor/elementor-init.php';
        }
    }

    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);

        // AJAX Handlers
        add_action('wp_ajax_wprr_filter_results', [$this, 'ajax_filter_results']);
        add_action('wp_ajax_nopriv_wprr_filter_results', [$this, 'ajax_filter_results']);
    }

    public function enqueue_public_assets()
    {
        wp_enqueue_style(
            'wprr-public-style',
            WPRR_URL . 'assets/css/wp-race-results-public.css',
            [],
            WPRR_VERSION
        );

        wp_enqueue_script(
            'wprr-modal-js',
            WPRR_URL . 'assets/js/wprr-winners-modal.js',
            ['jquery'],
            WPRR_VERSION,
            true
        );

        wp_localize_script('wprr-modal-js', 'wprr_modal_ajax', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);

        // Enqueue Chart.js for Analysis Views
        wp_enqueue_script(
            'wprr-chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            null,
            true
        );

        // AJAX Table Filtering
        wp_enqueue_script(
            'wprr-ajax-table',
            WPRR_URL . 'assets/js/wprr-ajax-table.js',
            ['jquery', 'wprr-modal-js'],
            WPRR_VERSION,
            true
        );
    }

    /**
     * Register custom query vars.
     *
     * @param array $vars Existing query vars.
     * @return array Modified query vars.
     */
    public function register_query_vars($vars)
    {
        $vars[] = 'event_slug';
        $vars[] = 'wprr_distance';
        $vars[] = 'wprr_entry_id';
        return $vars;
    }

    /**
     * Add rewrite rules for pretty event URLs.
     */
    public function add_rewrite_rules()
    {
        $permalink_base = get_option('wprr_permalink_base', 'results');
        $master_page_id = get_option('wprr_master_page_id');

        if ($master_page_id && !empty($permalink_base)) {
            // Rule 1 (Analysis Deep Link): {base}/{event}/{distance}/entry/{id}
            add_rewrite_rule(
                '^' . $permalink_base . '/([^/]+)/([^/]+)/entry/([^/]+)/?$',
                'index.php?page_id=' . $master_page_id . '&event_slug=$matches[1]&wprr_distance=$matches[2]&wprr_entry_id=$matches[3]',
                'top'
            );

            // Rule 2 (Deep Link): {base}/{event_slug}/{distance}
            add_rewrite_rule(
                '^' . $permalink_base . '/([^/]+)/([^/]+)/?$',
                'index.php?page_id=' . $master_page_id . '&event_slug=$matches[1]&wprr_distance=$matches[2]',
                'top'
            );

            // Rule 3 (Overview): {base}/{event_slug}
            add_rewrite_rule(
                '^' . $permalink_base . '/([^/]+)/?$',
                'index.php?page_id=' . $master_page_id . '&event_slug=$matches[1]',
                'top'
            );
        }
    }

    /**
     * AJAX handler for filtering race results.
     */
    public function ajax_filter_results()
    {
        $page = isset($_POST['wprr_page']) ? max(1, absint($_POST['wprr_page'])) : 1;
        $distance = isset($_POST['wprr_distance']) ? sanitize_text_field($_POST['wprr_distance']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $gender = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '';
        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $settings = isset($_POST['widget_settings']) ? $_POST['widget_settings'] : [];

        // --- Resolve Distance Slug to Raw Name ---
        $available_distances = WPRR_DB::get_distances_for_event($event_id);
        if (!empty($distance) && !empty($available_distances)) {
            foreach ($available_distances as $dist) {
                if (sanitize_title($dist) === sanitize_title($distance)) {
                    $distance = $dist;
                    break;
                }
            }
        }

        // --- Calculate Limits ---
        $limit = isset($settings['rows_per_page']) ? max(1, absint($settings['rows_per_page'])) : 20;
        $offset = ($page - 1) * $limit;

        // --- Fetch Results ---
        $results = WPRR_DB::get_results_for_table($event_id, $distance, $gender, $search, $limit, $offset);
        $total_results = WPRR_DB::count_results_for_table($event_id, $distance, $gender, $search);
        $total_pages = ceil($total_results / $limit);

        // --- Reconstruct Base URL for Links ---
        $perm_base = get_option('wprr_permalink_base', 'results');
        $event_obj = WPRR_DB::get_event_by_id($event_id);
        $event_slug = ($event_obj && !empty($event_obj->slug)) ? $event_obj->slug : 'event';

        $dist_slug = sanitize_title($distance);
        $base_url = home_url('/' . $perm_base . '/' . $event_slug . '/' . $dist_slug . '/');

        // Build the Browser URL (Deep Link)
        $new_url = $base_url;
        $query_args = [];
        if (!empty($search))
            $query_args['search'] = $search;
        if (!empty($gender))
            $query_args['gender'] = $gender;
        if ($page > 1)
            $query_args['wprr_page'] = $page;

        if (!empty($query_args)) {
            $new_url = add_query_arg($query_args, $base_url);
        }

        // --- Render HTML ---
        if (!class_exists('WPRR_Table_Renderer')) {
            require_once WPRR_PATH . 'includes/class-wprr-table-renderer.php';
        }

        $html = WPRR_Table_Renderer::render_html($results, $base_url, $page, $total_pages, $search, $gender, $settings);

        wp_send_json_success([
            'html' => $html,
            'new_url' => $new_url
        ]);
    }
}
