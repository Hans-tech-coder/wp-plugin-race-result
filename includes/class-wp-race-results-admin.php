<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    WP_Race_Results
 * @subpackage WP_Race_Results/includes
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WP_Race_Results
 * @subpackage WP_Race_Results/includes
 * @author     Your Name <email@example.com>
 */
class WP_Race_Results_Admin
{

    /**
     * Encryption key for internal use.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Hook suffix for the events page.
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $events_page_hook;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in WP_Race_Results_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The WP_Race_Results_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     * @param    string $hook The current admin page.
     */
    public function enqueue_scripts($hook)
    {

        // Only enqueue on our specific plugin pages
        if ($this->events_page_hook !== $hook && strpos($hook, 'wp_race_results') === false) {
            return;
        }

        // Ensure the media uploader scripts are loaded.
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');

        // Use plugin_dir_url to get the URL relative to this file's directory.
        $script_url = plugin_dir_url(__FILE__) . '../assets/js/wp-race-results-admin.js';
        $script_path = plugin_dir_path(__FILE__) . '../assets/js/wp-race-results-admin.js';
        $version = file_exists($script_path) ? filemtime($script_path) : $this->version;

        wp_enqueue_script($this->plugin_name, $script_url, array('jquery', 'wp-color-picker'), $version, false);

        // Enqueue scripts specifically for the import page
        if (isset($_GET['page']) && $_GET['page'] === 'wp_race_results_import') {
            wp_enqueue_script('sheetjs', 'https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js', array(), null, true);
            
            $mapper_url = plugin_dir_url(__FILE__) . '../assets/js/wprr-csv-mapper.js';
            $mapper_path = plugin_dir_path(__FILE__) . '../assets/js/wprr-csv-mapper.js';
            $mapper_version = file_exists($mapper_path) ? filemtime($mapper_path) : $this->version;
            wp_enqueue_script('wprr-csv-mapper', $mapper_url, array('jquery', 'sheetjs'), $mapper_version, true);
            
            // Pass ajax URL to script
            wp_localize_script('wprr-csv-mapper', 'wprr_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('wprr_import_nonce')
            ));
        }

    }

    /**
     * Registers the hooks for the admin area.
     *
     * @since    1.0.0
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_admin_settings'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX hook for importing mapped results
        add_action('wp_ajax_wprr_process_mapped_results', array($this, 'process_mapped_results_ajax'));
    }

    /**
     * Register Admin settings.
     *
     * @since 1.0.0
     */
    public function register_admin_settings()
    {
        register_setting('wprr_settings_group', 'wprr_master_page_id', ['type' => 'integer', 'sanitize_callback' => 'absint']);
        register_setting('wprr_settings_group', 'wprr_permalink_base', ['type' => 'string', 'sanitize_callback' => 'sanitize_title']);
        register_setting('wprr_settings_group', 'wprr_modal_header_bg');
        register_setting('wprr_settings_group', 'wprr_modal_text_color');

        // When settings are saved, flush rewrite rules
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            flush_rewrite_rules();
        }
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu()
    {

        // Add main menu page (Race Results).
        add_menu_page(
            'Race Results',
            'Race Results',
            'manage_options',
            'wp_race_results',
            array($this, 'display_plugin_setup_page'),
            'dashicons-performance',
            25
        );

        // Add submenu page (Events) - same slug as parent to replace the parent link title if needed, but here we want discrete items.
        // Actually, to make "Events" the first item, we usually point to the parent slug.
        // Let's add explicit "Events" submenu.
        $this->events_page_hook = add_submenu_page(
            'wp_race_results',
            'Race Events',
            'Events',
            'manage_options',
            'wp_race_results', // Links to the main page callback
            array($this, 'display_plugin_setup_page')
        );

        // Add submenu page (Results).
        add_submenu_page(
            'wp_race_results',
            'Race Results',
            'Results',
            'manage_options',
            'wp_race_results_results',
            array($this, 'display_plugin_results_page')
        );

        // Add submenu page (Import).
        add_submenu_page(
            'wp_race_results',
            'Import Race Results', // Page title
            'Import Results', // Menu title
            'manage_options',
            'wp_race_results_import',
            array($this, 'display_plugin_import_page')
        );

        // Add submenu page (Settings).
        add_submenu_page(
            'wp_race_results',
            'Settings', // Page title
            'Settings', // Menu title
            'manage_options',
            'wprr_settings',
            array($this, 'render_settings_page')
        );

    }

    /**
     * Render the setup page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page()
    {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';

        if ('new' === $action || 'edit' === $action) {
            $this->render_event_form();
        } else {
            $this->render_events_list();
        }
    }

    /**
     * Render the results page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_results_page()
    {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';

        if ('new' === $action || 'edit' === $action) {
            $this->render_result_form();
        } else {
            $this->render_results_list();
        }
    }

    /**
     * Handle form submissions for events.
     *
     * @since 1.0.0
     */
    public function handle_form_submissions()
    {
        // Check if we are handling an event save.
        if (isset($_POST['wp_race_save_event'])) {
            if (!isset($_POST['wp_race_event_nonce']) || !wp_verify_nonce($_POST['wp_race_event_nonce'], 'wp_race_save_event')) {
                wp_die('Security check failed');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'race_events';

            $event_name = sanitize_text_field($_POST['event_name']);
            $event_date = sanitize_text_field($_POST['event_date']);
            $location = sanitize_text_field($_POST['location']);
            $banner_image = sanitize_text_field($_POST['banner_image']);
            $event_logo = isset($_POST['event_logo']) ? esc_url_raw($_POST['event_logo']) : '';

            // Handle slug generation and uniqueness
            $posted_slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';
            if (empty($posted_slug)) {
                // Auto-generate from event name
                $slug = sanitize_title($event_name);
            } else {
                // Use provided slug
                $slug = sanitize_title($posted_slug);
            }

            // Ensure slug is unique
            $current_event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
            $original_slug = $slug;
            $counter = 1;
            while (true) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE slug = %s AND id != %d",
                    $slug,
                    $current_event_id
                ));
                if (!$existing) {
                    break; // Slug is unique
                }
                // Append number to make it unique
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }

            // Collect and encode social media links
            $social_links = array(
                'facebook' => isset($_POST['social_facebook']) ? esc_url_raw($_POST['social_facebook']) : '',
                'instagram' => isset($_POST['social_instagram']) ? esc_url_raw($_POST['social_instagram']) : '',
                'website' => isset($_POST['social_website']) ? esc_url_raw($_POST['social_website']) : '',
            );
            $social_media_links = json_encode($social_links);

            $data = array(
                'event_name' => $event_name,
                'slug' => $slug,
                'event_date' => $event_date,
                'location' => $location,
                'banner_image' => $banner_image,
                'event_logo' => $event_logo,
                'social_media_links' => $social_media_links,
                'distance_categories' => sanitize_text_field($_POST['distance_categories']),
            );

            if (!empty($_POST['event_id'])) {
                // Update existing event.
                $event_id = absint($_POST['event_id']);
                $wpdb->update($table_name, $data, array('id' => $event_id));
                $message = 'updated';
            } else {
                // Add new event.
                $wpdb->insert($table_name, $data);
                $event_id = $wpdb->insert_id;
                $message = 'created';
            }

            // Save Winner Rules
            if ($event_id && isset($_POST['winner_rules']) && is_array($_POST['winner_rules'])) {
                $rules_table = $wpdb->prefix . 'race_winner_rules';
                foreach ($_POST['winner_rules'] as $dist => $genders) {
                    $dist = sanitize_text_field($dist);
                    foreach ($genders as $gender => $count) {
                        $gender = sanitize_text_field($gender);
                        $count = absint($count);

                        $wpdb->query($wpdb->prepare(
                            "INSERT INTO $rules_table (event_id, distance, gender, declared_winners) 
                             VALUES (%d, %s, %s, %d) 
                             ON DUPLICATE KEY UPDATE declared_winners = %d",
                            $event_id,
                            $dist,
                            $gender,
                            $count,
                            $count
                        ));
                    }
                }
            }

            wp_redirect(admin_url('admin.php?page=wp_race_results&message=' . $message));
            exit;
        }

        // Check if we are handling an event delete.
        if (isset($_GET['action']) && 'delete' === $_GET['action'] && isset($_GET['event_id']) && isset($_GET['page']) && 'wp_race_results' === $_GET['page']) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_event_' . $_GET['event_id'])) {
                wp_die('Security check failed');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'race_events';
            $rules_table = $wpdb->prefix . 'race_winner_rules';
            $event_id = absint($_GET['event_id']);

            $wpdb->delete($table_name, array('id' => $event_id));
            $wpdb->delete($rules_table, array('event_id' => $event_id));

            wp_redirect(admin_url('admin.php?page=wp_race_results&message=deleted'));
            exit;
            wp_redirect(admin_url('admin.php?page=wp_race_results&message=deleted'));
            exit;
        }

        // Check if we are handling a bulk delete.
        if (isset($_POST['action']) && 'bulk-delete' === $_POST['action']) {
            if (!isset($_POST['wp_race_bulk_delete_nonce']) || !wp_verify_nonce($_POST['wp_race_bulk_delete_nonce'], 'wp_race_bulk_delete')) {
                wp_die('Security check failed');
            }

            if (!empty($_POST['result_ids']) && is_array($_POST['result_ids'])) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'race_results';
                $ids = array_map('absint', $_POST['result_ids']);
                $ids_string = implode(',', $ids);

                $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_string)");
                $count = count($ids);

                wp_redirect(admin_url('admin.php?page=wp_race_results_results&message=bulk_deleted&count=' . $count));
                exit;
            }
        }

        // Check if we are handling a result save.
        if (isset($_POST['wp_race_save_result'])) {
            if (!isset($_POST['wp_race_result_nonce']) || !wp_verify_nonce($_POST['wp_race_result_nonce'], 'wp_race_save_result')) {
                wp_die('Security check failed');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'race_results';

            $event_id = absint($_POST['event_id']);
            $bib_number = absint($_POST['bib_number']);
            $full_name = sanitize_text_field($_POST['full_name']);
            $gender = sanitize_text_field($_POST['gender']);
            $distance = sanitize_text_field($_POST['distance']);
            $gun_time = sanitize_text_field($_POST['gun_time']);
            $chip_time = sanitize_text_field($_POST['chip_time']);
            $rank_overall = absint($_POST['rank_overall']);
            $rank_gender = absint($_POST['rank_gender']);

            $data = array(
                'event_id' => $event_id,
                'bib_number' => $bib_number,
                'full_name' => $full_name,
                'gender' => $gender,
                'distance' => $distance,
                'gun_time' => $gun_time,
                'chip_time' => $chip_time,
                'rank_overall' => $rank_overall,
                'rank_gender' => $rank_gender,
            );

            if (!empty($_POST['result_id'])) {
                // Update existing result.
                $result_id = absint($_POST['result_id']);
                $wpdb->update($table_name, $data, array('id' => $result_id));
                $message = 'updated';
            } else {
                // Add new result.
                $wpdb->insert($table_name, $data);
                $message = 'created';
            }

            wp_redirect(admin_url('admin.php?page=wp_race_results_results&message=' . $message));
            exit;
        }

        // Check if we are handling a result delete.
        if (isset($_GET['action']) && 'delete' === $_GET['action'] && isset($_GET['result_id']) && isset($_GET['page']) && 'wp_race_results_results' === $_GET['page']) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_result_' . $_GET['result_id'])) {
                wp_die('Security check failed');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'race_results';
            $result_id = absint($_GET['result_id']);

            $wpdb->delete($table_name, array('id' => $result_id));

            wp_redirect(admin_url('admin.php?page=wp_race_results_results&message=deleted'));
            exit;
        }
    }

    /**
     * Render the events list table.
     *
     * @since 1.0.0
     */
    public function render_events_list()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'race_events';
        $events = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h2>
                <?php echo esc_html(get_admin_page_title()); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp_race_results&action=new')); ?>"
                    class="page-title-action">Add New Event</a>
            </h2>
            <?php if (!empty($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($_GET['message']); ?></p>
                </div>
            <?php endif; ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Event Name</th>
                        <th>Event Date</th>
                        <th>Location</th>
                        <th>Distances</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo absint($event->id); ?></td>
                                <td><?php echo esc_html($event->event_name); ?></td>
                                <td><?php echo esc_html($event->event_date); ?></td>
                                <td><?php echo esc_html($event->location); ?></td>
                                <td><?php echo esc_html($event->distance_categories); ?></td>
                                <td>
                                    <a
                                        href="<?php echo esc_url(admin_url('admin.php?page=wp_race_results&action=edit&event_id=' . $event->id)); ?>">Edit</a>
                                    |
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wp_race_results&action=delete&event_id=' . $event->id), 'delete_event_' . $event->id)); ?>"
                                        onclick="return confirm('Are you sure?');" class="delete">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No events found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the event form (Add/Edit).
     *
     * @since 1.0.0
     */
    public function render_event_form()
    {
        global $wpdb;
        $event = null;
        $heading = 'Add New Event';

        if (isset($_GET['event_id'])) {
            $event_id = absint($_GET['event_id']);
            $table_name = $wpdb->prefix . 'race_events';
            $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $event_id));
            if ($event) {
                $heading = 'Edit Event';
            }
        }
        ?>
        <div class="wrap">
            <h2><?php echo esc_html($heading); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wp_race_results')); ?>">
                <?php wp_nonce_field('wp_race_save_event', 'wp_race_event_nonce'); ?>
                <input type="hidden" name="wp_race_save_event" value="1">
                <?php if ($event): ?>
                    <input type="hidden" name="event_id" value="<?php echo absint($event->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="event_name">Event Name</label></th>
                        <td><input name="event_name" type="text" id="event_name"
                                value="<?php echo esc_attr($event ? $event->event_name : ''); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="slug">Event Slug</label></th>
                        <td>
                            <input name="slug" type="text" id="slug" value="<?php echo esc_attr($event ? $event->slug : ''); ?>"
                                class="regular-text" placeholder="Auto-generated from Event Name">
                            <p class="description">The URL-friendly version of the name (e.g., tarlac-marathon). Leave empty to
                                auto-generate from Event Name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="event_date">Event Date</label></th>
                        <td><input name="event_date" type="date" id="event_date"
                                value="<?php echo esc_attr($event ? $event->event_date : ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="location">Location</label></th>
                        <td><input name="location" type="text" id="location"
                                value="<?php echo esc_attr($event ? $event->location : ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="distance_categories">Distance Categories</label></th>
                        <td>
                            <input name="distance_categories" type="text" id="distance_categories"
                                value="<?php echo esc_attr($event ? $event->distance_categories : ''); ?>" class="regular-text"
                                placeholder="Example: 5KM,10KM,21KM">
                            <p class="description">Comma-separated list of race distances for this event.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="banner_image">Banner Image</label></th>
                        <td>
                            <input name="banner_image" type="hidden" id="banner_image"
                                value="<?php echo esc_attr($event ? $event->banner_image : ''); ?>">
                            <input type="button" id="upload_banner_image_button" class="button" value="Upload / Select Image">
                            <br>
                            <?php
                            $image_url = $event ? $event->banner_image : '';
                            $display = $image_url ? 'block' : 'none';
                            ?>
                            <img id="banner_image_preview" src="<?php echo esc_url($image_url); ?>"
                                style="max-width:300px; margin-top:10px; display:<?php echo esc_attr($display); ?>;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="event_logo">Event Logo</label></th>
                        <td>
                            <input name="event_logo" type="hidden" id="event_logo"
                                value="<?php echo esc_attr($event ? $event->event_logo : ''); ?>">
                            <input type="button" id="upload_event_logo_button" class="button" value="Upload / Select Logo">
                            <br>
                            <?php
                            $logo_url = $event ? $event->event_logo : '';
                            $logo_display = $logo_url ? 'block' : 'none';
                            ?>
                            <img id="event_logo_preview" src="<?php echo esc_url($logo_url); ?>"
                                style="max-width:300px; margin-top:10px; display:<?php echo esc_attr($logo_display); ?>;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Social Media Links</label></th>
                        <td>
                            <?php
                            // Decode social media links from JSON
                            $social_links = array('facebook' => '', 'instagram' => '', 'website' => '');
                            if ($event && !empty($event->social_media_links)) {
                                $decoded = json_decode($event->social_media_links, true);
                                if (is_array($decoded)) {
                                    $social_links = array_merge($social_links, $decoded);
                                }
                            }
                            ?>
                            <label for="social_facebook" style="display: block; margin-bottom: 8px;">
                                <strong>Facebook URL:</strong><br>
                                <input name="social_facebook" type="url" id="social_facebook"
                                    value="<?php echo esc_attr($social_links['facebook']); ?>" class="regular-text"
                                    placeholder="https://facebook.com/your-page">
                            </label>
                            <label for="social_instagram" style="display: block; margin-bottom: 8px;">
                                <strong>Instagram URL:</strong><br>
                                <input name="social_instagram" type="url" id="social_instagram"
                                    value="<?php echo esc_attr($social_links['instagram']); ?>" class="regular-text"
                                    placeholder="https://instagram.com/your-page">
                            </label>
                            <label for="social_website" style="display: block; margin-bottom: 8px;">
                                <strong>Website URL:</strong><br>
                                <input name="social_website" type="url" id="social_website"
                                    value="<?php echo esc_attr($social_links['website']); ?>" class="regular-text"
                                    placeholder="https://your-website.com">
                            </label>
                        </td>
                    </tr>
                </table>

                <?php if ($event): ?>
                    <h3>Winner Declaration</h3>
                    <table class="form-table">
                        <?php
                        $distances = !empty($event->distance_categories) ? explode(',', $event->distance_categories) : [];
                        $rules_table = $wpdb->prefix . 'race_winner_rules';

                        foreach ($distances as $distance):
                            $distance = trim($distance);
                            if (empty($distance))
                                continue;

                            // Fetch existing rules (Source of Truth)
                            $male_val = WPRR_DB::get_declared_winners($event->id, $distance, 'male');
                            $female_val = WPRR_DB::get_declared_winners($event->id, $distance, 'female');
                            ?>
                            <tr>
                                <th scope="row"><strong><?php echo esc_html($distance); ?></strong></th>
                                <td>
                                    <label>Male Winners: </label>
                                    <input type="number" name="winner_rules[<?php echo esc_attr($distance); ?>][male]"
                                        value="<?php echo $male_val; ?>" min="1" max="10" style="width: 60px;">
                                    &nbsp;&nbsp;&nbsp;
                                    <label>Female Winners: </label>
                                    <input type="number" name="winner_rules[<?php echo esc_attr($distance); ?>][female]"
                                        value="<?php echo $female_val; ?>" min="1" max="10" style="width: 60px;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($distances)): ?>
                            <tr>
                                <td colspan="2">
                                    <p class="description">Save the event with Distance Categories first to define winner rules.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                <?php endif; ?>

                <?php submit_button($event ? 'Update Event' : 'Add Event'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the results list table.
     *
     * @since 1.0.0
     */
    public function render_results_list()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'race_results';
        $table_events = $wpdb->prefix . 'race_events';

        // Fetch filter options
        // We fetch distance_categories as well to populate the dependent dropdown
        $filter_events = $wpdb->get_results("SELECT id, event_name, distance_categories FROM $table_events ORDER BY created_at DESC");

        $filter_distances = array();
        if (!empty($_GET['filter_event'])) {
            $selected_event_id = absint($_GET['filter_event']);
            foreach ($filter_events as $fe) {
                if ($fe->id == $selected_event_id && !empty($fe->distance_categories)) {
                    $cats = explode(',', $fe->distance_categories);
                    $filter_distances = array_map('trim', $cats);
                    break;
                }
            }
        }

        // Build Query
        $sql = "SELECT r.*, e.event_name 
                FROM $table_name AS r 
                LEFT JOIN $table_events AS e ON r.event_id = e.id 
                WHERE 1=1";

        $params = array();

        // Apply Event Filter
        if (!empty($_GET['filter_event'])) {
            $sql .= " AND r.event_id = %d";
            $params[] = absint($_GET['filter_event']);
        }

        // Apply Distance Filter
        if (!empty($_GET['filter_distance'])) {
            // Only apply if the distance is valid for the selected event (or if we want to allow legacy data access, but strict dependency suggests validating)
            // However, simply trusting the GET param is usually fine for filters. 
            // If the user selected a distance, we filter by it.
            $sql .= " AND r.distance = %s";
            $params[] = sanitize_text_field($_GET['filter_distance']);
        }

        // Apply Rank Filter
        if (!empty($_GET['filter_rank'])) {
            $sql .= " AND r.rank_overall <= %d";
            $params[] = absint($_GET['filter_rank']);
        }

        $sql .= " ORDER BY r.id DESC";

        if (!empty($params)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $results = $wpdb->get_results($sql);
        }
        ?>
        <div class="wrap">
            <h2>
                <?php echo esc_html(get_admin_page_title()); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp_race_results_results&action=new')); ?>"
                    class="page-title-action">Add New Result</a>
            </h2>
            <?php if (!empty($_GET['message'])): ?>
                <?php if ('bulk_deleted' === $_GET['message']): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php echo sprintf('Successfully imported %d results.', absint($_GET['count'])); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php echo esc_html($_GET['message']); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Filters -->
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>"
                style="margin-bottom: 20px; background: #fff; padding: 10px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <input type="hidden" name="page" value="wp_race_results_results">

                <div class="alignleft actions">
                    <select name="filter_event" onchange="this.form.submit()">
                        <option value="">All Events</option>
                        <?php foreach ($filter_events as $event): ?>
                            <option value="<?php echo absint($event->id); ?>" <?php selected(isset($_GET['filter_event']) ? $_GET['filter_event'] : '', $event->id); ?>>
                                <?php echo esc_html($event->event_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filter_distance">
                        <option value="">All Distances</option>
                        <?php foreach ($filter_distances as $dist): ?>
                            <option value="<?php echo esc_attr($dist); ?>" <?php selected(isset($_GET['filter_distance']) ? $_GET['filter_distance'] : '', $dist); ?>>
                                <?php echo esc_html($dist); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="filter_rank" style="margin-left:5px;">Max Rank:</label>
                    <input type="number" name="filter_rank" id="filter_rank"
                        value="<?php echo isset($_GET['filter_rank']) ? absint($_GET['filter_rank']) : ''; ?>"
                        style="width: 60px;" placeholder="Any">

                    <input type="submit" class="button" value="Filter">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp_race_results_results')); ?>"
                        class="button">Reset</a>
                </div>
                <br class="clear">
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wp_race_results_results')); ?>">
                <?php wp_nonce_field('wp_race_bulk_delete', 'wp_race_bulk_delete_nonce'); ?>

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1">Bulk Actions</option>
                            <option value="bulk-delete">Delete</option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="Apply">
                    </div>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo count($results); ?> items</span>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                                <input id="wprr-select-all" type="checkbox">
                            </td>
                            <th width="50">ID</th>
                            <th>Event</th>
                            <th>Bib</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Distance</th>
                            <th>Gun Time</th>
                            <th>Chip Time</th>
                            <th>Rank Over.</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($results)): ?>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="result_ids[]" value="<?php echo absint($result->id); ?>">
                                    </th>
                                    <td><?php echo absint($result->id); ?></td>
                                    <td><?php echo esc_html($result->event_name ? $result->event_name : 'Unknown Event'); ?></td>
                                    <td><?php echo absint($result->bib_number); ?></td>
                                    <td><?php echo esc_html($result->full_name); ?></td>
                                    <td><?php echo esc_html($result->gender); ?></td>
                                    <td><?php echo esc_html($result->distance); ?></td>
                                    <td><?php echo esc_html($result->gun_time); ?></td>
                                    <td><?php echo esc_html($result->chip_time); ?></td>
                                    <td><?php echo absint($result->rank_overall); ?></td>
                                    <td>
                                        <a
                                            href="<?php echo esc_url(admin_url('admin.php?page=wp_race_results_results&action=edit&result_id=' . $result->id)); ?>">Edit</a>
                                        |
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wp_race_results_results&action=delete&result_id=' . $result->id), 'delete_result_' . $result->id)); ?>"
                                            onclick="return confirm('Are you sure?');" class="delete">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11">No results found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }

    /**
     * Render the result form (Add/Edit).
     *
     * @since 1.0.0
     */
    public function render_result_form()
    {
        global $wpdb;
        $result = null;
        $heading = 'Add New Result';

        // Fetch events for dropdown
        $table_events = $wpdb->prefix . 'race_events';
        $events = $wpdb->get_results("SELECT id, event_name FROM $table_events ORDER BY created_at DESC");

        if (isset($_GET['result_id'])) {
            $result_id = absint($_GET['result_id']);
            $table_results = $wpdb->prefix . 'race_results';
            $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_results WHERE id = %d", $result_id));
            if ($result) {
                $heading = 'Edit Result';
            }
        }
        ?>
        <div class="wrap">
            <h2><?php echo esc_html($heading); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wp_race_results_results')); ?>">
                <?php wp_nonce_field('wp_race_save_result', 'wp_race_result_nonce'); ?>
                <input type="hidden" name="wp_race_save_result" value="1">
                <?php if ($result): ?>
                    <input type="hidden" name="result_id" value="<?php echo absint($result->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="event_id">Event</label></th>
                        <td>
                            <select name="event_id" id="event_id" required>
                                <option value="">Select Event</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo esc_attr($event->id); ?>" <?php selected($result ? $result->event_id : '', $event->id); ?>><?php echo esc_html($event->event_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bib_number">Bib Number</label></th>
                        <td><input name="bib_number" type="number" id="bib_number"
                                value="<?php echo esc_attr($result ? $result->bib_number : ''); ?>" class="regular-text"
                                required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="full_name">Full Name</label></th>
                        <td><input name="full_name" type="text" id="full_name"
                                value="<?php echo esc_attr($result ? $result->full_name : ''); ?>" class="regular-text"
                                required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gender">Gender</label></th>
                        <td>
                            <select name="gender" id="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php selected($result ? $result->gender : '', 'Male'); ?>>Male
                                </option>
                                <option value="Female" <?php selected($result ? $result->gender : '', 'Female'); ?>>Female
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="distance">Distance</label></th>
                        <td>
                            <select name="distance" id="distance">
                                <option value="5K" <?php selected($result ? $result->distance : '', '5K'); ?>>5K</option>
                                <option value="10K" <?php selected($result ? $result->distance : '', '10K'); ?>>10K
                                </option>
                                <option value="21K" <?php selected($result ? $result->distance : '', '21K'); ?>>21K
                                </option>
                                <option value="42K" <?php selected($result ? $result->distance : '', '42K'); ?>>42K
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gun_time">Gun Time (HH:MM:SS)</label></th>
                        <td><input name="gun_time" type="text" id="gun_time"
                                value="<?php echo esc_attr($result ? $result->gun_time : ''); ?>" class="regular-text"
                                placeholder="00:00:00"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="chip_time">Chip Time (HH:MM:SS)</label></th>
                        <td><input name="chip_time" type="text" id="chip_time"
                                value="<?php echo esc_attr($result ? $result->chip_time : ''); ?>" class="regular-text"
                                placeholder="00:00:00"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rank_overall">Rank Overall</label></th>
                        <td><input name="rank_overall" type="number" id="rank_overall"
                                value="<?php echo esc_attr($result ? $result->rank_overall : ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rank_gender">Rank Gender</label></th>
                        <td><input name="rank_gender" type="number" id="rank_gender"
                                value="<?php echo esc_attr($result ? $result->rank_gender : ''); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button($result ? 'Update Result' : 'Add Result'); ?>
            </form>
        </div>
        <?php
    }
    /**
     * Render the import page.
     *
     * @since 1.0.0
     */
    public function display_plugin_import_page()
    {
        global $wpdb;
        $table_events = $wpdb->prefix . 'race_events';
        $events = $wpdb->get_results("SELECT id, event_name, distance_categories FROM $table_events ORDER BY created_at DESC");
        
        // Pass events to JS
        $events_data = array();
        foreach ($events as $e) {
            $events_data[] = array(
                'id' => $e->id,
                'name' => $e->event_name,
                'categories' => !empty($e->distance_categories) ? array_map('trim', explode(',', $e->distance_categories)) : array()
            );
        }
        ?>
        <div class="wrap wprr-import-wrap">
            <h2>Modern Result Importer</h2>
            
            <div id="wprr-upload-container" class="card" style="max-width: 1000px; padding: 20px; margin-top: 20px;">
                <div id="wprr-upload-zone" style="border: 2px dashed #ccc; padding: 40px; text-align: center; cursor: pointer; border-radius: 5px;">
                    <h3>Drop your Excel or CSV file here</h3>
                    <p>Or click to browse</p>
                    <input type="file" id="wprr-csv-file" accept=".xlsx, .xls, .csv" style="display: none;">
                </div>
                
                <div id="wprr-mapping-section" style="display: none; margin-top: 30px;">
                    <h3>Map Your Columns</h3>
                    
                    <div style="margin-bottom: 20px;">
                        <label><strong>1. Select Event:</strong></label><br>
                        <select id="wprr-event-select" style="width: 100%; max-width: 400px; margin-top: 5px;">
                            <option value="">-- Choose an Event --</option>
                            <?php foreach ($events_data as $e): ?>
                                <option value="<?php echo esc_attr($e['id']); ?>"><?php echo esc_html($e['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="wprr-sheets-container"></div>
                    
                    <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                            <input type="checkbox" id="wprr-replace-data"> Replace existing results for the selected distances (checked) or append (unchecked)
                        </label>
                        <button id="wprr-process-btn" class="button button-primary button-large">Process & Upload Results</button>
                    </div>
                </div>
                
                <div id="wprr-messages" style="margin-top: 20px;"></div>
            </div>
            
            <script>
                window.wprrEventsData = <?php echo json_encode($events_data); ?>;
            </script>
        </div>
        
        <style>
            .wprr-sheet-panel { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
            .wprr-mapping-grid { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px; }
            .wprr-mapping-item { flex: 1; min-width: 150px; }
            .wprr-mapping-item select { width: 100%; }
        </style>
        <?php
    }

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h2>Race Results Settings</h2>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('wprr_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="wprr_master_page_id">Master Results Page</label></th>
                        <td>
                            <?php
                            $selected_page = get_option('wprr_master_page_id');
                            wp_dropdown_pages([
                                'name' => 'wprr_master_page_id',
                                'selected' => $selected_page,
                                'show_option_none' => '— Select a Page —',
                                'option_none_value' => '0',
                            ]);
                            ?>
                            <p class="description">This page will be used as the template for displaying race results.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wprr_permalink_base">URL Base</label></th>
                        <td>
                            <input type="text" id="wprr_permalink_base" name="wprr_permalink_base"
                                value="<?php echo esc_attr(get_option('wprr_permalink_base', 'results')); ?>"
                                class="regular-text" />
                            <p class="description">The base slug for your race results URLs. Example:
                                /<strong>results</strong>/event-name/</p>
                        </td>
                    </tr>
                </table>

                <h3>Modal Pop-up Appearance</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label>Header Background Color</label></th>
                        <td>
                            <input type="text" name="wprr_modal_header_bg"
                                value="<?php echo esc_attr(get_option('wprr_modal_header_bg', '#7A2e66')); ?>"
                                class="wprr-color-field" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label>Header Text Color</label></th>
                        <td>
                            <input type="text" name="wprr_modal_text_color"
                                value="<?php echo esc_attr(get_option('wprr_modal_text_color', '#ffffff')); ?>"
                                class="wprr-color-field" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    /**
     * AJAX handler for processing mapped CSV results
     *
     * @since 1.0.0
     */
    public function process_mapped_results_ajax()
    {
        check_ajax_referer('wprr_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to do this.');
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        if (!$event_id) {
            wp_send_json_error('Invalid event ID.');
        }

        $replace_data = isset($_POST['replace_data']) && $_POST['replace_data'] == '1';
        $results_json = isset($_POST['results']) ? wp_unslash($_POST['results']) : '';
        $results = json_decode($results_json, true);

        if (empty($results) || !is_array($results)) {
            wp_send_json_error('No valid results data provided.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'race_results';

        // Find all unique distances in the payload
        $affected_distances = array();
        foreach ($results as $r) {
            if (!empty($r['distance']) && !in_array($r['distance'], $affected_distances)) {
                $affected_distances[] = $r['distance'];
            }
        }

        // 1. Optional: Replace existing data for the uploaded distances
        if ($replace_data) {
            foreach ($affected_distances as $dist) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE event_id = %d AND distance = %s",
                    $event_id,
                    sanitize_text_field($dist)
                ));
            }
        }

        // 2. Insert new results
        $insert_count = 0;
        foreach ($results as $r) {
            // Minimal validation
            if (empty($r['bib_number']) || empty($r['full_name']) || empty($r['chip_time'])) {
                continue;
            }

            $inserted = $wpdb->insert($table_name, array(
                'event_id'     => $event_id,
                'distance'     => sanitize_text_field($r['distance']),
                'bib_number'   => sanitize_text_field($r['bib_number']),
                'full_name'    => sanitize_text_field($r['full_name']),
                'gender'       => sanitize_text_field($r['gender']),
                'chip_time'    => sanitize_text_field($r['chip_time']),
                'gun_time'     => isset($r['gun_time']) ? sanitize_text_field($r['gun_time']) : '',
                'rank_overall' => 0, // Will be computed shortly
                'rank_gender'  => 0  // Will be computed shortly
            ));

            if ($inserted) {
                $insert_count++;
            }
        }

        // 3. Auto-calculate rankings for affected distances
        foreach ($affected_distances as $dist) {
            $this->recalculate_ranks_for_distance($event_id, sanitize_text_field($dist));
        }

        wp_send_json_success(array(
            'message' => sprintf('Successfully processed and saved %d results. Rankings have been automatically calculated!', $insert_count),
            'count' => $insert_count
        ));
    }

    /**
     * Recalculates Overall and Gender Ranks for a specific event distance based on chip_time.
     *
     * @since 1.0.0
     */
    private function recalculate_ranks_for_distance($event_id, $distance)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'race_results';

        // 1. Calculate Overall Ranks
        // We order by chip_time ASC. Null or empty string goes to the end or is ignored.
        $overall_results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, chip_time FROM $table_name 
             WHERE event_id = %d AND distance = %s 
             AND chip_time != '' AND chip_time IS NOT NULL
             ORDER BY chip_time ASC",
            $event_id,
            $distance
        ));

        $rank = 1;
        foreach ($overall_results as $row) {
            $wpdb->update($table_name, array('rank_overall' => $rank), array('id' => $row->id));
            $rank++;
        }

        // 2. Calculate Gender Ranks (Male)
        $male_results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, chip_time FROM $table_name 
             WHERE event_id = %d AND distance = %s 
             AND LOWER(gender) IN ('m', 'male')
             AND chip_time != '' AND chip_time IS NOT NULL
             ORDER BY chip_time ASC",
            $event_id,
            $distance
        ));

        $rank = 1;
        foreach ($male_results as $row) {
            $wpdb->update($table_name, array('rank_gender' => $rank), array('id' => $row->id));
            $rank++;
        }

        // 3. Calculate Gender Ranks (Female)
        $female_results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, chip_time FROM $table_name 
             WHERE event_id = %d AND distance = %s 
             AND LOWER(gender) IN ('f', 'female')
             AND chip_time != '' AND chip_time IS NOT NULL
             ORDER BY chip_time ASC",
            $event_id,
            $distance
        ));

        $rank = 1;
        foreach ($female_results as $row) {
            $wpdb->update($table_name, array('rank_gender' => $rank), array('id' => $row->id));
            $rank++;
        }
    }
}
