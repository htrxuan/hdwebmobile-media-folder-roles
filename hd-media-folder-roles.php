<?php

/**
 * Plugin Name: HDWebmobile Media Folder Roles
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-media-folder-roles/
 * Description: Manage media folders and set role-based permissions, compatible with PublishPress Capabilities.
 * Version: 1.1.5
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-media-folder-roles
 * Domain Path: /languages
 */

namespace htrxuan\hdwebmobilemediafolderroles;

// Prevent direct file access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 */
final class HDWEMEFO_Core
{

    /**
     * Instance of this class.
     *
     * @var HDWEMEFO_Core
     */
    private static $instance = null;

    /**
     * Get instance of this class.
     *
     * @return HDWEMEFO_Core
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        self::$instance = $this;
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define constants.
     */
    private function define_constants()
    {
        define('HDWEMEFO_VERSION', '1.1.5');
        define('HDWEMEFO_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('HDWEMEFO_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('HDWEMEFO_TAXONOMY', 'hdwemefo_media_folder');
    }

    /**
     * Include required files.
     */
    private function includes()
    {
        require_once HDWEMEFO_PLUGIN_DIR . 'includes/class-hdwemefo-permissions.php';
        require_once HDWEMEFO_PLUGIN_DIR . 'includes/class-hdwemefo-admin-ui.php';
        require_once HDWEMEFO_PLUGIN_DIR . 'includes/class-hdwemefo-sidebar.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'maybe_migrate_legacy_taxonomy'), 5);
        add_action('init', array($this, 'register_taxonomy'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        add_action('admin_notices', array($this, 'render_media_folder_notice'));

        // Initialize components
        HDWEMEFO_Permissions::get_instance();
        HDWEMEFO_Admin::get_instance();
        HDWEMEFO_Sidebar::get_instance();
    }

    /**
     * Add links on the Plugins screen.
     *
     * @param array $links Existing plugin action links.
     * @return array
     */
    public function add_action_links($links)
    {
        $folders_link = '<a href="upload.php">' . __('Folders', 'hdwebmobile-media-folder-roles') . '</a>';
        $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" style="color:#d54e21;font-weight:bold;">' . __('Donate', 'hdwebmobile-media-folder-roles') . '</a>';

        array_unshift($links, $donate_link);
        array_unshift($links, $folders_link);

        return $links;
    }

    /**
     * Show description and donate link on the Media Folders admin page.
     */
    public function render_media_folder_notice()
    {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || 'edit-tags' !== $screen->base || HDWEMEFO_TAXONOMY !== $screen->taxonomy) {
            return;
        }

        $message = __('Use this page to create folder hierarchy and manage role access to media files.', 'hdwebmobile-media-folder-roles');
        $support = __('Support development:', 'hdwebmobile-media-folder-roles');
        $donate = __('Donate via PayPal', 'hdwebmobile-media-folder-roles');

        printf(
            '<div class="notice notice-info"><p>%1$s %2$s <strong><a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a></strong></p></div>',
            esc_html($message),
            esc_html($support),
            esc_url('https://paypal.me/htrxuan/20'),
            esc_html($donate)
        );
    }

    /**
     * Register media folder taxonomy.
     */
    public function register_taxonomy()
    {
        $labels = array(
            'name' => _x('Media Folders', 'taxonomy general name', 'hdwebmobile-media-folder-roles'),
            'singular_name' => _x('Media Folder', 'taxonomy singular name', 'hdwebmobile-media-folder-roles'),
            'search_items' => __('Search Media Folders', 'hdwebmobile-media-folder-roles'),
            'all_items' => __('All Media Folders', 'hdwebmobile-media-folder-roles'),
            'parent_item' => __('Parent Media Folder', 'hdwebmobile-media-folder-roles'),
            'parent_item_colon' => __('Parent Media Folder:', 'hdwebmobile-media-folder-roles'),
            'edit_item' => __('Edit Media Folder', 'hdwebmobile-media-folder-roles'),
            'update_item' => __('Update Media Folder', 'hdwebmobile-media-folder-roles'),
            'add_new_item' => __('Add New Media Folder', 'hdwebmobile-media-folder-roles'),
            'new_item_name' => __('New Media Folder Name', 'hdwebmobile-media-folder-roles'),
            'menu_name' => __('Folders', 'hdwebmobile-media-folder-roles'),
        );

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => false,
            'rewrite' => array('slug' => 'media-folder'),
            'show_in_rest' => true,
        );

        register_taxonomy(HDWEMEFO_TAXONOMY, array('attachment'), $args);
    }

    /**
     * One-time migration from legacy generic taxonomy key to prefixed key.
     */
    public function maybe_migrate_legacy_taxonomy()
    {
        if (get_option('hdwemefo_taxonomy_migrated')) {
            return;
        }

        $legacy_taxonomy = 'media_folder';
        if ($legacy_taxonomy === HDWEMEFO_TAXONOMY) {
            update_option('hdwemefo_taxonomy_migrated', HDWEMEFO_VERSION, false);
            return;
        }

        // We don't register the legacy taxonomy name anymore to avoid compliance flags.
        // If it was used in an older version, the terms should still be in the database.

        $legacy_terms = get_terms(array(
            'taxonomy' => $legacy_taxonomy,
            'hide_empty' => false,
        ));

        if (is_wp_error($legacy_terms) || empty($legacy_terms)) {
            update_option('hdwemefo_taxonomy_migrated', HDWEMEFO_VERSION, false);
            return;
        }

        $term_id_map = array();

        foreach ($legacy_terms as $legacy_term) {
            $new_term_id = 0;

            $existing = get_term_by('slug', $legacy_term->slug, HDWEMEFO_TAXONOMY);
            if ($existing && !is_wp_error($existing)) {
                $new_term_id = (int) $existing->term_id;
            } else {
                $created = wp_insert_term($legacy_term->name, HDWEMEFO_TAXONOMY, array(
                    'slug' => $legacy_term->slug,
                    'description' => $legacy_term->description,
                ));

                if (is_wp_error($created)) {
                    if ('term_exists' === $created->get_error_code()) {
                        $existing_id = (int) $created->get_error_data('term_exists');
                        $new_term_id = $existing_id > 0 ? $existing_id : 0;
                    }
                } else {
                    $new_term_id = (int) $created['term_id'];
                }
            }

            if ($new_term_id <= 0) {
                continue;
            }

            $legacy_term_id = (int) $legacy_term->term_id;
            $term_id_map[$legacy_term_id] = $new_term_id;

            $all_meta = get_term_meta($legacy_term_id);
            if (!empty($all_meta) && is_array($all_meta)) {
                foreach ($all_meta as $meta_key => $meta_values) {
                    delete_term_meta($new_term_id, $meta_key);
                    foreach ((array) $meta_values as $meta_value) {
                        add_term_meta($new_term_id, $meta_key, maybe_unserialize($meta_value));
                    }
                }
            }
        }

        foreach ($legacy_terms as $legacy_term) {
            $legacy_term_id = (int) $legacy_term->term_id;
            if (!isset($term_id_map[$legacy_term_id])) {
                continue;
            }

            $new_parent_id = 0;
            $legacy_parent_id = (int) $legacy_term->parent;
            if ($legacy_parent_id > 0 && isset($term_id_map[$legacy_parent_id])) {
                $new_parent_id = $term_id_map[$legacy_parent_id];
            }

            wp_update_term($term_id_map[$legacy_term_id], HDWEMEFO_TAXONOMY, array(
                'parent' => $new_parent_id,
            ));
        }

        foreach ($legacy_terms as $legacy_term) {
            $legacy_term_id = (int) $legacy_term->term_id;
            if (!isset($term_id_map[$legacy_term_id])) {
                continue;
            }

            $object_ids = get_objects_in_term($legacy_term_id, $legacy_taxonomy);
            if (is_wp_error($object_ids) || empty($object_ids)) {
                continue;
            }

            foreach ($object_ids as $object_id) {
                wp_set_object_terms((int) $object_id, array($term_id_map[$legacy_term_id]), HDWEMEFO_TAXONOMY, true);
            }
        }

        update_option('hdwemefo_taxonomy_migrated', HDWEMEFO_VERSION, false);
    }

    /**
     * AJAX handler to sync the currently selected folder to user meta.
     * This ensures the server knows where to assign uploads even if the uploader
     * does not send the parameter directly.
     */
    public function ajax_sync_selected_folder()
    {
        check_ajax_referer('hdwemefo_sidebar_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You are not allowed to update the active media folder.', 'hdwebmobile-media-folder-roles')), 403);
        }

        $folder_id = isset($_POST['folder_id']) ? sanitize_text_field(wp_unslash($_POST['folder_id'])) : 'all';
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            update_user_meta($user_id, 'hdwemefo_active_folder', $folder_id);
        }

        wp_send_json_success(array('synced' => $folder_id));
    }
}

/**
 * Initialize the plugin.
 */
function hdwemefo_core()
{
    return HDWEMEFO_Core::get_instance();
}

hdwemefo_core();
