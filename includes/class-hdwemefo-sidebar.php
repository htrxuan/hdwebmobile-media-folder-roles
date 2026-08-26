<?php

/**
 * Sidebar UI Class
 */

namespace htrxuan\hdwebmobilemediafolderroles;

// Prevent direct file access.
if (!defined('ABSPATH')) {
    exit;
}

class HDWEMEFO_Sidebar
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_footer', array($this, 'inject_sidebar_container'));
        add_filter('admin_body_class', array($this, 'add_admin_body_class'));

        // Direct upload support
        add_action('pre-upload-ui', array($this, 'inject_uploader_input'));

        // AJAX handlers
        add_action('wp_ajax_hdwemefo_get_folders', array($this, 'ajax_get_folders'));
        add_action('wp_ajax_hdwemefo_create_folder', array($this, 'ajax_create_folder'));
        add_action('wp_ajax_hdwemefo_rename_folder', array($this, 'ajax_rename_folder'));
        add_action('wp_ajax_hdwemefo_delete_folder', array($this, 'ajax_delete_folder'));
        add_action('wp_ajax_hdwemefo_move_media', array($this, 'ajax_move_media'));

        // Sync active folder to user meta
        add_action('wp_ajax_hdwemefo_sync_selected_folder', array(HDWEMEFO_Core::get_instance(), 'ajax_sync_selected_folder'));
    }

    public function add_admin_body_class($classes)
    {
        global $pagenow;
        if ($pagenow === 'upload.php' || $pagenow === 'post.php' || $pagenow === 'post-new.php') {
            $classes .= ' hdwemefo-sidebar-active';
        }
        return $classes;
    }

    public function enqueue_assets($hook)
    {
        wp_enqueue_style('hdwemefo-admin-css', HDWEMEFO_PLUGIN_URL . 'assets/css/hdwemefo-admin.css', array(), HDWEMEFO_VERSION);

        $deps = array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable');
        if (wp_script_is('plupload')) {
            $deps[] = 'plupload';
        }
        if (wp_script_is('media-views')) {
            $deps[] = 'media-views';
        }

        wp_enqueue_script('hdwemefo-admin-js', HDWEMEFO_PLUGIN_URL . 'assets/js/hdwemefo-admin.js', $deps, HDWEMEFO_VERSION, true);

        wp_localize_script('hdwemefo-admin-js', 'hdwemefo_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hdwemefo_sidebar_nonce'),
            'i18n' => array(
                'new_folder' => __('New Folder', 'hdwebmobile-media-folder-roles'),
                'rename' => __('Rename', 'hdwebmobile-media-folder-roles'),
                'delete' => __('Delete', 'hdwebmobile-media-folder-roles'),
                'confirm_delete' => __('Are you sure you want to delete this folder?', 'hdwebmobile-media-folder-roles'),
                'enter_name' => __('Enter folder name...', 'hdwebmobile-media-folder-roles'),
            )
        ));
    }

    public function inject_uploader_input()
    {
        // This ensures the parameter is sent during Plupload uploads
        echo '<input type="hidden" name="media_folder" id="hdwemefo-direct-upload-folder" value="">';
        echo '<input type="hidden" name="fbv" id="hdwemefo-direct-upload-folder-fbv" value="">';
        echo '<input type="hidden" name="hdwemefo_nonce" id="hdwemefo-direct-upload-nonce" value="' . esc_attr(wp_create_nonce('hdwemefo_sidebar_nonce')) . '">';
    }

    public function inject_sidebar_container()
    {
        global $pagenow;
        if ($pagenow !== 'upload.php' && $pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
            return;
        }
?>
        <!-- HDWEMEFO Sidebar Start -->
        <div id="hdwemefo-sidebar-overlay" style="display: none;">
            <div id="hdwemefo-sidebar">
                <div class="hdwemefo-sidebar-header">
                    <span class="hdwemefo-brand">Folders</span>
                    <button id="hdwemefo-add-folder" class="hdwemefo-btn-primary">
                        <span class="dashicons dashicons-category"></span>
                        <span class="dashicons dashicons-plus hdwemefo-plus-icon"></span>
                        <?php esc_html_e('New Folder', 'hdwebmobile-media-folder-roles'); ?>
                    </button>
                </div>

                <div class="hdwemefo-sidebar-tools">
                    <button id="hdwemefo-rename-folder" class="hdwemefo-tool-btn" disabled>
                        <span class="dashicons dashicons-edit"></span> <?php esc_html_e('Rename', 'hdwebmobile-media-folder-roles'); ?>
                    </button>
                    <button id="hdwemefo-delete-folder" class="hdwemefo-tool-btn" disabled>
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete', 'hdwebmobile-media-folder-roles'); ?>
                    </button>
                    <span class="hdwemefo-icon-btn dashicons dashicons-editor-expand"></span>
                    <span class="hdwemefo-icon-btn dashicons dashicons-ellipsis"></span>
                </div>

                <div id="hdwemefo-folder-list-container">
                    <ul id="hdwemefo-folder-list">
                        <li data-id="all" class="hdwemefo-folder-item preset active" id="hdwemefo-item-all">
                            <div class="hdwemefo-folder-row">
                                <span class="dashicons dashicons-admin-home"></span>
                                <span
                                    class="hdwemefo-folder-name"><?php esc_html_e('All Files', 'hdwebmobile-media-folder-roles'); ?></span>
                                <span class="hdwemefo-count" id="hdwemefo-count-all">0</span>
                            </div>
                        </li>
                        <li data-id="unassigned" class="hdwemefo-folder-item preset" id="hdwemefo-item-unassigned">
                            <div class="hdwemefo-folder-row">
                                <span class="dashicons dashicons-category"></span>
                                <span
                                    class="hdwemefo-folder-name"><?php esc_html_e('Uncategorized', 'hdwebmobile-media-folder-roles'); ?></span>
                                <span class="hdwemefo-count" id="hdwemefo-count-unassigned">0</span>
                            </div>
                        </li>
                    </ul>

                    <div class="hdwemefo-inline-create">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" id="hdwemefo-inline-input"
                            placeholder="<?php esc_attr_e('Enter folder name...', 'hdwebmobile-media-folder-roles'); ?>">
                    </div>

                    <div id="hdwemefo-custom-folders">
                        <!-- Folders loaded via AJAX -->
                    </div>
                </div>
            </div>
            <div id="hdwemefo-sidebar-resizer"></div>
            <!-- Move collapse button out of sidebar -->
            <div id="hdwemefo-sidebar-collapse" title="<?php esc_attr_e('Collapse Sidebar', 'hdwebmobile-media-folder-roles'); ?>">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            </div>
        </div>
        <!-- HDWEMEFO Sidebar End -->
<?php
    }

    public function ajax_get_folders()
    {
        check_ajax_referer('hdwemefo_sidebar_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You are not allowed to access media folders.', 'hdwebmobile-media-folder-roles')), 403);
        }

        $permissions = HDWEMEFO_Permissions::get_instance();
        $allowed_folder_ids = $permissions->get_allowed_folder_ids();

        $terms = get_terms(array(
            'taxonomy' => HDWEMEFO_TAXONOMY,
            'hide_empty' => false,
            'include' => !empty($allowed_folder_ids) ? $allowed_folder_ids : array(-1),
        ));

        if (is_wp_error($terms)) {
            wp_send_json_error($terms->get_error_message());
        }

        // Build generic restriction query
        $tax_query_restriction = array();
        if (!current_user_can('hdwemefo_bypass_restrictions')) {
            if (empty($allowed_folder_ids)) {
                $tax_query_restriction = array(
                    array(
                        'taxonomy' => HDWEMEFO_TAXONOMY,
                        'field' => 'term_id',
                        'terms' => array(-1),
                        'operator' => 'IN',
                    ),
                );
            } else {
                $tax_query_restriction = array(
                    array(
                        'taxonomy' => HDWEMEFO_TAXONOMY,
                        'field' => 'term_id',
                        'terms' => $allowed_folder_ids,
                        'operator' => 'IN',
                    ),
                );
            }
        }

        // Count All (Allowed folders + Unassigned)
        $all_query_args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
        );
        if (!empty($tax_query_restriction)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required to enforce folder visibility restrictions.
            $all_query_args['tax_query'] = $tax_query_restriction;
        }

        $all_query = new \WP_Query($all_query_args);
        $all_count = $all_query->found_posts;

        // Count Unassigned
        $unassigned_query_args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required to count unassigned media.
            'tax_query' => array(
                array(
                    'taxonomy' => HDWEMEFO_TAXONOMY,
                    'field' => 'term_id',
                    'operator' => 'NOT EXISTS'
                )
            )
        );
        // Note: Unassigned query doesn't need extra restriction because unassigned is implicitly allowed in restriction logic (NOT EXISTS).
        // Unless we want to be super strict, but current logic allows unassigned.

        if (current_user_can('hdwemefo_bypass_restrictions')) {
            $unassigned_query = new \WP_Query($unassigned_query_args);
            $unassigned_count = $unassigned_query->found_posts;
        } else {
            $unassigned_count = 0;
        }

        $folders = array();
        foreach ($terms as $term) {
            // Precise hierarchical count for each folder
            $folder_query = new \WP_Query(array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => false,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for per-folder media counts.
                'tax_query' => array(
                    array(
                        'taxonomy' => HDWEMEFO_TAXONOMY,
                        'field' => 'term_id',
                        'terms' => $term->term_id,
                        'include_children' => true,
                    )
                )
            ));

            $folders[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'count' => $folder_query->found_posts,
                'parent' => $term->parent,
            );
        }

        wp_send_json_success(array(
            'folders' => $folders,
            'all_count' => $all_count,
            'unassigned_count' => $unassigned_count
        ));
    }

    public function ajax_create_folder()
    {
        check_ajax_referer('hdwemefo_sidebar_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You are not allowed to create media folders.', 'hdwebmobile-media-folder-roles')), 403);
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $parent = isset($_POST['parent']) ? intval($_POST['parent']) : 0;

        if (empty($name)) {
            wp_send_json_error(__('Name cannot be empty', 'hdwebmobile-media-folder-roles'));
        }

        $result = wp_insert_term($name, HDWEMEFO_TAXONOMY, array('parent' => $parent));
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('id' => $result['term_id'], 'name' => $name, 'parent' => $parent));
    }

    public function ajax_rename_folder()
    {
        check_ajax_referer('hdwemefo_sidebar_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You are not allowed to rename media folders.', 'hdwebmobile-media-folder-roles')), 403);
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if (!$id || empty($name)) {
            wp_send_json_error(__('Invalid data', 'hdwebmobile-media-folder-roles'));
        }

        $result = wp_update_term($id, HDWEMEFO_TAXONOMY, array('name' => $name));
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    public function ajax_delete_folder()
    {
        check_ajax_referer('hdwemefo_sidebar_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You are not allowed to delete media folders.', 'hdwebmobile-media-folder-roles')), 403);
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(__('Invalid ID', 'hdwebmobile-media-folder-roles'));
        }

        $result = wp_delete_term($id, HDWEMEFO_TAXONOMY);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    public function ajax_move_media()
    {
        check_ajax_referer('hdwemefo_sidebar_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You are not allowed to move media.', 'hdwebmobile-media-folder-roles')), 403);
        }

        $media_ids = isset($_POST['media_ids']) ? array_map('intval', (array) $_POST['media_ids']) : array();
        $folder_id = isset($_POST['folder_id']) ? ($_POST['folder_id'] === 'unassigned' ? 0 : intval($_POST['folder_id'])) : -1;

        if (empty($media_ids) || $folder_id === -1) {
            wp_send_json_error(__('Invalid parameters', 'hdwebmobile-media-folder-roles'));
        }

        foreach ($media_ids as $media_id) {
            if ($folder_id === 0) {
                wp_set_object_terms($media_id, array(), HDWEMEFO_TAXONOMY);
            } else {
                wp_set_object_terms($media_id, array($folder_id), HDWEMEFO_TAXONOMY);
            }
        }

        wp_send_json_success();
    }
}
