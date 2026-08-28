<?php

namespace HotelBooking\Admin;

use HotelBooking\PostTypes\RoomPostType;
use HotelBooking\Taxonomies\RoomCategoryTaxonomy;
use HotelBooking\Taxonomies\AmenityTaxonomy;

defined('ABSPATH') || exit;

use function HotelBooking\hb_asset_version;

final class Admin
{
    private SettingsPage $settings_page;

    public function init(): void
    {
        $this->settings_page = new SettingsPage();
        $this->settings_page->init();

        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'render_stripe_config_notice']);
        add_filter('plugin_row_meta', [$this, 'add_plugin_row_meta'], 10, 2);
        add_filter('plugin_action_links_' . HB_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);

        (new RoomMetabox())->init();
        (new AmenityFields())->init();
    }

    public function register_menus(): void
    {
        add_menu_page(
            __('Hotel Booking', 'hotel-booking'),
            __('Hotel Booking', 'hotel-booking'),
            'manage_options',
            'hotel-booking',
            [$this, 'render_dashboard'],
            'dashicons-building',
            26
        );

        add_submenu_page(
            'hotel-booking',
            __('Dashboard', 'hotel-booking'),
            __('Dashboard', 'hotel-booking'),
            'manage_options',
            'hotel-booking',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'hotel-booking',
            __('Rooms', 'hotel-booking'),
            __('Rooms', 'hotel-booking'),
            'edit_posts',
            'edit.php?post_type=' . RoomPostType::SLUG
        );

        add_submenu_page(
            'hotel-booking',
            __('Room Categories', 'hotel-booking'),
            __('Room Categories', 'hotel-booking'),
            'manage_categories',
            'edit-tags.php?taxonomy=' . RoomCategoryTaxonomy::SLUG . '&post_type=' . RoomPostType::SLUG
        );

        add_submenu_page(
            'hotel-booking',
            __('Amenities', 'hotel-booking'),
            __('Amenities', 'hotel-booking'),
            'manage_categories',
            'edit-tags.php?taxonomy=' . AmenityTaxonomy::SLUG . '&post_type=' . RoomPostType::SLUG
        );

        add_submenu_page(
            'hotel-booking',
            __('Bookings', 'hotel-booking'),
            __('Bookings', 'hotel-booking'),
            'manage_options',
            'hotel-booking-bookings',
            [$this, 'render_bookings']
        );

        add_submenu_page(
            'hotel-booking',
            __('Customers', 'hotel-booking'),
            __('Customers', 'hotel-booking'),
            'manage_options',
            'hotel-booking-customers',
            [$this, 'render_customers']
        );

        add_submenu_page(
            'hotel-booking',
            __('Invoices', 'hotel-booking'),
            __('Invoices', 'hotel-booking'),
            'manage_options',
            'hotel-booking-invoices',
            [$this, 'render_invoices']
        );

        add_submenu_page(
            'hotel-booking',
            __('Settings', 'hotel-booking'),
            __('Settings', 'hotel-booking'),
            'manage_options',
            'hotel-booking-settings',
            [$this, 'render_settings']
        );
    }

    public function enqueue_assets(string $hook): void
    {
        $plugin_pages = [
            'toplevel_page_hotel-booking',
            'hotel-booking_page_hotel-booking-bookings',
            'hotel-booking_page_hotel-booking-customers',
            'hotel-booking_page_hotel-booking-invoices',
            'hotel-booking_page_hotel-booking-settings',
        ];

        $post_type = RoomPostType::SLUG;
        $is_room_screen = in_array($hook, ['post.php', 'post-new.php'], true)
            && get_post_type() === $post_type;
        $is_taxonomy_screen = $hook === 'edit-tags.php'
            && isset($_GET['taxonomy'])
            && in_array($_GET['taxonomy'], [RoomCategoryTaxonomy::SLUG, AmenityTaxonomy::SLUG], true);

        if (!in_array($hook, $plugin_pages, true) && !$is_room_screen && !$is_taxonomy_screen) {
            return;
        }

        wp_enqueue_style(
            'hb-admin',
            HB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            hb_asset_version('assets/css/admin.css')
        );

        wp_enqueue_media();

        if ($is_room_screen) {
            wp_enqueue_script(
                'hb-room-metabox',
                HB_PLUGIN_URL . 'assets/js/admin/room-metabox.js',
                ['jquery', 'jquery-ui-sortable'],
                hb_asset_version('assets/js/admin/room-metabox.js'),
                true
            );
        }

        if ($is_taxonomy_screen && isset($_GET['taxonomy']) && $_GET['taxonomy'] === AmenityTaxonomy::SLUG) {
            wp_enqueue_script(
                'hb-amenity-fields',
                HB_PLUGIN_URL . 'assets/js/admin/amenity-fields.js',
                ['jquery'],
                hb_asset_version('assets/js/admin/amenity-fields.js'),
                true
            );
        }

        if ($hook === 'hotel-booking_page_hotel-booking-settings') {
            wp_enqueue_script(
                'hb-settings',
                HB_PLUGIN_URL . 'assets/js/admin/settings.js',
                ['jquery', 'jquery-ui-sortable'],
                hb_asset_version('assets/js/admin/settings.js'),
                true
            );
        }
    }

    /**
     * Stripe enabled but not usable is otherwise invisible until a guest reaches
     * the payment step and cannot pay.
     */
    public function render_stripe_config_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $errors = (new \HotelBooking\Services\StripeGateway())->get_config_errors();
        if (empty($errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>'
            . esc_html__('Hotel Booking: Stripe is enabled but not ready to accept payments.', 'hotel-booking')
            . '</strong></p><ul style="list-style:disc;margin-left:20px;">';

        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }

        echo '</ul><p>'
            . sprintf(
                /* translators: %s: link to the payment settings screen */
                esc_html__('Card payment is hidden from the booking form until this is resolved. %s', 'hotel-booking'),
                '<a href="' . esc_url(admin_url('admin.php?page=hotel-booking-settings&tab=payments')) . '">'
                    . esc_html__('Open payment settings', 'hotel-booking') . '</a>'
            )
            . '</p></div>';
    }

    /**
     * Settings shortcut on the plugin's action links.
     */
    public function add_plugin_action_links(array $links): array
    {
        $settings = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=hotel-booking-settings')),
            esc_html__('Settings', 'hotel-booking')
        );

        array_unshift($links, $settings);

        return $links;
    }

    /**
     * Adds an author link to the plugin's row on the Plugins screen.
     */
    public function add_plugin_row_meta(array $meta, string $plugin_file): array
    {
        if ($plugin_file !== HB_PLUGIN_BASENAME) {
            return $meta;
        }

        $meta[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(HB_AUTHOR_URL),
            esc_html__('Visit Webnday', 'hotel-booking')
        );

        return $meta;
    }

    public function render_dashboard(): void
    {
        (new DashboardPage())->render();
    }

    public function render_bookings(): void
    {
        (new BookingsPage())->render();
    }

    public function render_customers(): void
    {
        (new CustomersPage())->render();
    }

    public function render_invoices(): void
    {
        (new InvoicesPage())->render();
    }

    public function render_settings(): void
    {
        $this->settings_page->render();
    }
}
