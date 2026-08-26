<?php

/**
 * Admin UI Class
 */

namespace htrxuan\hdwebmobilemediafolderroles;

// Prevent direct file access.
if (!defined('ABSPATH')) {
    exit;
}

class HDWEMEFO_Admin
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
        add_action('restrict_manage_posts', array($this, 'add_folder_filter'));
        add_filter('ajax_query_attachments_args', array($this, 'add_grid_filter_logic'));
        add_action('admin_menu', array($this, 'add_menu_page'));
    }

    /**
     * This plugin has no settings of its own (folder permissions live on the term-edit
     * screen), so this small page exists solely to host the "more plugins by this author"
     * panel -- kept minimal rather than skipped, so every hdwebmobile plugin offers the same
     * discovery path. Sits under Settings (not WooCommerce) since this plugin doesn't require
     * WooCommerce.
     */
    public function add_menu_page()
    {
        add_options_page(
            __('Media Folder Roles', 'hdwebmobile-media-folder-roles'),
            __('Media Folder Roles', 'hdwebmobile-media-folder-roles'),
            'manage_options',
            'hdwemefo-plugins',
            array($this, 'render_plugins_page')
        );
    }

    public function render_plugins_page()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Media Folder Roles', 'hdwebmobile-media-folder-roles'); ?></h1>
            <?php $this->render_sibling_plugins_panel('hdwebmobile-media-folder-roles'); ?>
        </div>
        <?php
    }

    /**
     * A self-contained "more plugins by this author" panel, shown at the bottom of this
     * plugin's own settings page only (never a global admin notice or dashboard widget) --
     * stays within WordPress.org's guidelines for author self-promotion.
     */
    private function render_sibling_plugins_panel($exclude_slug)
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = array(
            'hdwebmobile-mix-match-bundles' => array(
                'name' => 'Mix & Match Bundles',
                'file' => 'hdwebmobile-mix-match-bundles/hdwebmobile-mix-match-bundles.php',
                'description' => __('Build-a-box bundles for WooCommerce.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-back-in-stock-waitlist' => array(
                'name' => 'Back In Stock & Waitlist',
                'file' => 'hdwebmobile-back-in-stock-waitlist/hdwebmobile-back-in-stock-waitlist.php',
                'description' => __('Notify customers the moment an out-of-stock product is back.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-spin-and-win' => array(
                'name' => 'Spin & Win',
                'file' => 'hdwebmobile-spin-and-win/hdwebmobile-spin-and-win.php',
                'description' => __('A gamified discount-wheel popup with server-enforced one spin per email.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-abandoned-cart-recovery' => array(
                'name' => 'Abandoned Cart Recovery',
                'file' => 'hdwebmobile-abandoned-cart-recovery/hdwebmobile-abandoned-cart-recovery.php',
                'description' => __('Recover lost sales with automatic cart-recovery emails.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-live-shopping-with-agora' => array(
                'name' => 'Live Shopping with Agora',
                'file' => 'hdwebmobile-live-shopping-with-agora/hdwebmobile-live-shopping-with-agora.php',
                'description' => __('Sell live on livestream video with real-time WooCommerce checkout.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-media-folder-roles' => array(
                'name' => 'Media Folder Roles',
                'file' => 'hdwebmobile-media-folder-roles/hd-media-folder-roles.php',
                'description' => __('Organize the Media Library into folders with per-role permissions.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-socials-floating' => array(
                'name' => 'Socials Floating',
                'file' => 'hdwebmobile-socials-floating/hdwebmobile-socials-floating.php',
                'description' => __('A floating social-media contact bar for your site.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-wishlist' => array(
                'name' => 'Wishlist',
                'file' => 'hdwebmobile-wishlist/hdwebmobile-wishlist.php',
                'description' => __('A wishlist that works with block-based Shop pages and guest browsing.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-frequently-bought-together' => array(
                'name' => 'Frequently Bought Together',
                'file' => 'hdwebmobile-frequently-bought-together/hdwebmobile-frequently-bought-together.php',
                'description' => __('An Amazon-style widget that adds several related products in one click.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-delivery-date-time-slot' => array(
                'name' => 'Delivery Date & Time Slot',
                'file' => 'hdwebmobile-delivery-date-time-slot/hdwebmobile-delivery-date-time-slot.php',
                'description' => __('Let customers pick a delivery date and time slot at checkout.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-product-options' => array(
                'name' => 'Product Options & Add-ons',
                'file' => 'hdwebmobile-product-options/hdwebmobile-product-options.php',
                'description' => __('Add paid text, dropdown, and checkbox options to products -- flat pricing only, no formulas.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-invoices-packing-slips' => array(
                'name' => 'Invoices & Packing Slips',
                'file' => 'hdwebmobile-invoices-packing-slips/hdwebmobile-invoices-packing-slips.php',
                'description' => __('On-demand invoice and packing-slip documents, printable to PDF from any browser.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-gift-cards' => array(
                'name' => 'Gift Cards',
                'file' => 'hdwebmobile-gift-cards/hdwebmobile-gift-cards.php',
                'description' => __('Sell digital gift cards redeemed through WooCommerce\'s own native coupon field.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-shipment-tracking' => array(
                'name' => 'Shipment Tracking',
                'file' => 'hdwebmobile-shipment-tracking/hdwebmobile-shipment-tracking.php',
                'description' => __('Add a carrier and tracking number to any order and show it to the customer.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-order-export' => array(
                'name' => 'Order Export',
                'file' => 'hdwebmobile-order-export/hdwebmobile-order-export.php',
                'description' => __('Export orders to CSV, filtered by date range and status.', 'hdwebmobile-media-folder-roles'),
            ),
            'hdwebmobile-photo-video-reviews' => array(
                'name' => 'Photo & Video Reviews',
                'file' => 'hdwebmobile-photo-video-reviews/hdwebmobile-photo-video-reviews.php',
                'description' => __('Let customers attach photos and short videos to their product reviews.', 'hdwebmobile-media-folder-roles'),
            ),
        );

        unset($plugins[$exclude_slug]);
        ?>
        <div class="hdwebmobile-sibling-plugins" style="margin-top:1em;padding:1.5em;background:#fff;border:1px solid #dcdcde;border-radius:4px;max-width:800px;">
            <h2 style="margin-top:0;"><?php esc_html_e('More Plugins by HDWebmobile', 'hdwebmobile-media-folder-roles'); ?></h2>
            <table class="widefat striped" style="margin-top:1em;">
                <tbody>
                    <?php foreach ($plugins as $plugin) : ?>
                        <?php $active = is_plugin_active($plugin['file']); ?>
                        <?php $installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin['file']); ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($plugin['name']); ?></strong><br />
                                <span style="opacity:.75;"><?php echo esc_html($plugin['description']); ?></span>
                            </td>
                            <td style="text-align:right;white-space:nowrap;vertical-align:middle;">
                                <?php if ($active) : ?>
                                    <span style="color:#1a7f37;">&#10003; <?php esc_html_e('Active', 'hdwebmobile-media-folder-roles'); ?></span>
                                <?php elseif ($installed) : ?>
                                    <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-small"><?php esc_html_e('Activate', 'hdwebmobile-media-folder-roles'); ?></a>
                                <?php else : ?>
                                    <a href="https://hdwebmobile.com/plugins/" target="_blank" rel="noopener noreferrer" class="button button-small"><?php esc_html_e('Learn More', 'hdwebmobile-media-folder-roles'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function add_folder_filter($post_type)
    {
        if ('attachment' !== $post_type) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected = isset($_GET['media_folder']) ? sanitize_text_field(wp_unslash($_GET['media_folder'])) : '';

        wp_dropdown_categories(array(
            'show_option_all' => __('All Folders', 'hdwebmobile-media-folder-roles'),
            'taxonomy' => HDWEMEFO_TAXONOMY,
            'name' => 'media_folder',
            'orderby' => 'name',
            'selected' => $selected,
            'hierarchical' => true,
            'show_count' => true,
            'hide_empty' => false,
            'value_field' => 'slug',
        ));
    }

    public function add_grid_filter_logic($args)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $folder = isset($_REQUEST['query']['media_folder']) ? sanitize_text_field(wp_unslash($_REQUEST['query']['media_folder'])) : '';

        if ('unassigned' === $folder) {
            $args['tax_query'][] = array(
                'taxonomy' => HDWEMEFO_TAXONOMY,
                'field' => 'term_id',
                'operator' => 'NOT EXISTS',
            );
        } elseif (!empty($folder) && 'all' !== $folder) {
            $field = is_numeric($folder) ? 'term_id' : 'slug';
            $args['tax_query'][] = array(
                'taxonomy' => HDWEMEFO_TAXONOMY,
                'field' => $field,
                'terms' => $folder,
            );
        }
        return $args;
    }
}
