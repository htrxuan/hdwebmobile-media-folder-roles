<?php

/**
 * Permissions Class
 */

namespace htrxuan\hdwebmobilemediafolderroles;

// Prevent direct file access.
if (!defined('ABSPATH')) {
    exit;
}

class HDWEMEFO_Permissions
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
        add_action(HDWEMEFO_TAXONOMY . '_edit_form_fields', array($this, 'add_permission_fields'), 10, 2);
        add_action('edited_' . HDWEMEFO_TAXONOMY, array($this, 'save_permission_fields'), 10, 2);

        // Filter media queries
        add_filter('ajax_query_attachments_args', array($this, 'filter_media_library'), 20);
        add_filter('pre_get_posts', array($this, 'filter_media_list_view'), 20);

        // Hook for auto-assigning folder on upload
        add_action('add_attachment', array($this, 'auto_assign_folder'));

        // Register capabilities for PublishPress
        add_action('init', array($this, 'register_capabilities'));
    }

    public function register_capabilities()
    {
        $admin = get_role('administrator');
        if ($admin) {
            if (!$admin->has_cap('hdwemefo_manage_folders')) {
                $admin->add_cap('hdwemefo_manage_folders');
            }
            if (!$admin->has_cap('hdwemefo_bypass_restrictions')) {
                $admin->add_cap('hdwemefo_bypass_restrictions');
            }
        }
    }

    public function add_permission_fields($term, $taxonomy)
    {
        $is_admin_user = current_user_can('manage_options');

        $allowed_roles = get_term_meta($term->term_id, 'hdwemefo_allowed_roles', true);
        if (!is_array($allowed_roles)) {
            $allowed_roles = array();
        }

        $wp_roles = wp_roles()->get_names();

        if ($is_admin_user) {
            wp_nonce_field('hdwemefo_save_permissions', 'hdwemefo_permissions_nonce');
        }
?>
        <tr class="form-field">
            <th scope="row" valign="top"><label><?php esc_html_e('Allowed Roles', 'hdwebmobile-media-folder-roles'); ?></label></th>
            <td>
                <?php foreach ($wp_roles as $role_key => $role_name): ?>
                    <label style="display: block; margin-bottom: 5px;">
                        <input type="checkbox" name="hdwemefo_allowed_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $allowed_roles)); ?> <?php disabled(!$is_admin_user); ?>>
                        <?php echo esc_html($role_name); ?>
                    </label>
                <?php endforeach; ?>
                <p class="description">
                    <?php esc_html_e('Select roles that can access media in this folder. Administrators always have access.', 'hdwebmobile-media-folder-roles'); ?>
                </p>
                <?php if (!$is_admin_user): ?>
                    <p class="description">
                        <?php esc_html_e('Only administrators can change this setting.', 'hdwebmobile-media-folder-roles'); ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
<?php
    }

    public function save_permission_fields($term_id, $tt_id)
    {
        // Enforce admin-only changes server-side.
        if (!current_user_can('manage_options')) {
            return;
        }

        // Verify nonce for security
        if (!isset($_POST['hdwemefo_permissions_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdwemefo_permissions_nonce'])), 'hdwemefo_save_permissions')) {
            return;
        }

        if (isset($_POST['hdwemefo_allowed_roles']) && is_array($_POST['hdwemefo_allowed_roles'])) {
            $roles = array_map('sanitize_text_field', wp_unslash($_POST['hdwemefo_allowed_roles']));
            update_term_meta($term_id, 'hdwemefo_allowed_roles', $roles);
        } else {
            update_term_meta($term_id, 'hdwemefo_allowed_roles', array());
        }
    }

    public function filter_media_library($query)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core media query requests provide context; this filter must read request params.
        $requested_folder = isset($_REQUEST['query']['media_folder']) ? sanitize_text_field(wp_unslash($_REQUEST['query']['media_folder'])) : '';

        // If not admin AND no specific folder requested, apply restriction logic
        if (current_user_can('hdwemefo_bypass_restrictions') && (empty($requested_folder) || 'all' === $requested_folder)) {
            return $query;
        }

        $allowed_folder_ids = $this->get_allowed_folder_ids();

        if (!empty($requested_folder) && 'all' !== $requested_folder && 'unassigned' !== $requested_folder) {
            $folder_id = is_numeric($requested_folder) ? intval($requested_folder) : 0;
            if (!$folder_id && !is_numeric($requested_folder)) {
                $term = get_term_by('slug', $requested_folder, HDWEMEFO_TAXONOMY);
                if ($term)
                    $folder_id = $term->term_id;
            }

            if (!$folder_id || !in_array($folder_id, $allowed_folder_ids, true)) {
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy constraint is required for role-based folder access.
                $query['tax_query'] = array(array('taxonomy' => HDWEMEFO_TAXONOMY, 'field' => 'term_id', 'terms' => array(-1)));
                return $query;
            }
        } else if ('unassigned' === $requested_folder) {
            // Non-admin users can only access explicitly assigned folders.
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy constraint is required for role-based folder access.
            $query['tax_query'] = array(array('taxonomy' => HDWEMEFO_TAXONOMY, 'field' => 'term_id', 'terms' => array(-1)));
            return $query;
        } else {
            if (empty($allowed_folder_ids)) {
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy constraint is required for role-based folder access.
                $query['tax_query'][] = array(
                    'taxonomy' => HDWEMEFO_TAXONOMY,
                    'field' => 'term_id',
                    'terms' => array(-1),
                    'operator' => 'IN',
                );
            } else {
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy constraint is required for role-based folder access.
                $query['tax_query'][] = array(
                    'taxonomy' => HDWEMEFO_TAXONOMY,
                    'field' => 'term_id',
                    'terms' => $allowed_folder_ids,
                    'operator' => 'IN',
                );
            }
        }

        return $query;
    }

    public function filter_media_list_view($query)
    {
        if (!is_admin() || !$query->is_main_query() || 'attachment' !== $query->get('post_type')) {
            return;
        }

        if (current_user_can('hdwemefo_bypass_restrictions')) {
            return;
        }

        $allowed_folder_ids = $this->get_allowed_folder_ids();

        if (empty($allowed_folder_ids)) {
            $tax_query = array(
                array(
                    'taxonomy' => HDWEMEFO_TAXONOMY,
                    'field' => 'term_id',
                    'terms' => array(-1),
                    'operator' => 'IN',
                ),
            );
        } else {
            $tax_query = array(
                array(
                    'taxonomy' => HDWEMEFO_TAXONOMY,
                    'field' => 'term_id',
                    'terms' => $allowed_folder_ids,
                    'operator' => 'IN',
                ),
            );
        }

        $query->set('tax_query', $tax_query);
    }

    public function get_allowed_folder_ids()
    {
        if (current_user_can('hdwemefo_bypass_restrictions')) {
            $terms = get_terms(array(
                'taxonomy' => HDWEMEFO_TAXONOMY,
                'hide_empty' => false,
                'fields' => 'ids',
            ));
            return is_wp_error($terms) ? array() : $terms;
        }

        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;

        $terms = get_terms(array(
            'taxonomy' => HDWEMEFO_TAXONOMY,
            'hide_empty' => false,
        ));

        $allowed_ids = array();
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $allowed_roles = get_term_meta($term->term_id, 'hdwemefo_allowed_roles', true);
                if (is_array($allowed_roles) && !empty($allowed_roles) && array_intersect($user_roles, $allowed_roles)) {
                    $allowed_ids[] = $term->term_id;
                }
            }
        }

        return $allowed_ids;
    }

    public function auto_assign_folder($attachment_id)
    {
        if (!is_user_logged_in() || !current_user_can('upload_files')) {
            return;
        }

        $folder_id = '';

        // 1. Check for nonce if folder data is provided in the request.
        $request_nonce = isset($_REQUEST['hdwemefo_nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['hdwemefo_nonce'])) : '';
        
        if (!empty($request_nonce) && wp_verify_nonce($request_nonce, 'hdwemefo_sidebar_nonce')) {
            if (isset($_POST['media_folder']) && is_scalar($_POST['media_folder'])) {
                $folder_id = sanitize_text_field(wp_unslash((string) $_POST['media_folder']));
            } else if (isset($_POST['fbv']) && is_scalar($_POST['fbv'])) {
                $folder_id = sanitize_text_field(wp_unslash((string) $_POST['fbv']));
            }
        }

        // 2. Fallback to user meta if folder_id is STILL empty (nonce missing or invalid, or no POST data)
        if (empty($folder_id)) {
            $user_id = get_current_user_id();
            if ($user_id > 0) {
                $stored_folder = get_user_meta($user_id, 'hdwemefo_active_folder', true);
                if ('' !== (string) $stored_folder) {
                    $folder_id = sanitize_text_field((string) $stored_folder);
                }
            }
        }

        if (empty($folder_id) || 'all' === $folder_id || 'unassigned' === $folder_id || '-1' === (string) $folder_id) {
            return;
        }

        // Handle array inputs
        if (is_array($folder_id)) {
            $folder_id = reset($folder_id);
        }

        $term_id = is_numeric($folder_id) ? intval($folder_id) : 0;
        if (!$term_id && !is_numeric($folder_id)) {
            $term = get_term_by('slug', $folder_id, HDWEMEFO_TAXONOMY);
            if ($term) {
                $term_id = $term->term_id;
            }
        }

        if ($term_id) {
            wp_set_object_terms($attachment_id, array($term_id), HDWEMEFO_TAXONOMY);
        }
    }
}
