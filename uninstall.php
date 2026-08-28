<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

$delete_data = get_option('hb_general', []);
$should_delete = !empty($delete_data['delete_data_on_uninstall']);

if ($should_delete) {
    global $wpdb;

    $tables = [
        $wpdb->prefix . 'hb_bookings',
        $wpdb->prefix . 'hb_booking_items',
        $wpdb->prefix . 'hb_booking_amenities',
        $wpdb->prefix . 'hb_customers',
        $wpdb->prefix . 'hb_customer_meta',
        $wpdb->prefix . 'hb_payments',
        $wpdb->prefix . 'hb_invoices',
        $wpdb->prefix . 'hb_reservation_holds',
    ];

    foreach ($tables as $table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    $options = [
        'hb_general',
        'hb_booking',
        'hb_payments',
        'hb_emails',
        'hb_form_fields',
        'hb_db_version',
        'hb_error_log',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    $rooms = get_posts([
        'post_type'   => 'hb_room',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields'      => 'ids',
    ]);

    foreach ($rooms as $room_id) {
        wp_delete_post($room_id, true);
    }

    $theme_mods = [
        'hb_branding_logo', 'hb_color_primary', 'hb_color_primary_hover',
        'hb_color_primary_light', 'hb_color_text', 'hb_color_text_light',
        'hb_color_bg', 'hb_color_bg_alt', 'hb_color_border',
        'hb_color_success', 'hb_color_danger', 'hb_popup_width',
        'hb_popup_radius', 'hb_popup_overlay_bg', 'hb_font_family',
        'hb_font_heading', 'hb_font_body', 'hb_btn_text_color',
    ];
    foreach ($theme_mods as $mod) {
        remove_theme_mod($mod);
    }

    $taxonomies = ['hb_room_category', 'hb_amenity'];
    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);
        if (is_array($terms)) {
            foreach ($terms as $term_id) {
                wp_delete_term($term_id, $taxonomy);
            }
        }
    }
}
