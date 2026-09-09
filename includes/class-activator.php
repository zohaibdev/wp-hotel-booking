<?php

namespace HotelBooking;

use HotelBooking\Database\Migrator;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate(): void
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(HB_PLUGIN_BASENAME);
            wp_die(
                esc_html__('Hotel Booking requires PHP 8.1 or higher.', 'hotel-booking'),
                esc_html__('Plugin Activation Error', 'hotel-booking'),
                ['back_link' => true]
            );
        }

        Migrator::run();
        self::set_default_options();
        flush_rewrite_rules();
    }

    private static function set_default_options(): void
    {
        $defaults = [
            'hb_general' => [
                'hotel_name'        => get_bloginfo('name'),
                'hotel_logo'        => '',
                'currency'          => 'USD',
                'currency_symbol'   => '$',
                'currency_position' => 'before',
                'delete_data_on_uninstall' => false,
            ],
            'hb_booking' => [
                'default_check_in_time'     => '15:00',
                'default_check_out_time'    => '11:00',
                'allow_custom_check_in_time'  => true,
                'allow_custom_check_out_time' => true,
                'minimum_stay'              => 1,
                'temporary_hold_minutes'    => 10,
                'enable_terms_step'         => true,
                'enable_checkin_time'       => true,
                'enable_checkout_time'      => true,
                'enable_adults'             => true,
                'enable_children'           => true,
                'terms_checkbox_text'       => __('I agree to the Terms & Conditions.', 'hotel-booking'),
                'terms_content'             => '',
            ],
            'hb_payments' => [
                'stripe_enabled'             => false,
                'stripe_mode'                => 'test',
                // Existing installs have no stored type and resolve to the same
                // secret-key behaviour they had before restricted keys existed.
                'stripe_key_type'            => 'secret_key',
                'stripe_test_publishable_key' => '',
                'stripe_test_secret_key'     => '',
                'stripe_live_publishable_key' => '',
                'stripe_live_secret_key'     => '',
                'stripe_test_restricted_key' => '',
                'stripe_live_restricted_key' => '',
                'offline_payment_enabled'    => true,
                'offline_payment_title'      => __('Pay at Hotel', 'hotel-booking'),
                'offline_payment_description' => __('Pay at the hotel upon arrival.', 'hotel-booking'),
                'enable_partial_payment'     => false,
                'initial_payment_percentage' => 50,
                'balance_due_days_before_check_in' => 30,
            ],
            'hb_emails' => [
                'admin_notification_email' => get_option('admin_email'),
                'from_name'                => get_bloginfo('name'),
                'from_email'               => get_option('admin_email'),
                'reply_to_email'           => get_option('admin_email'),
                'customer_booking_enabled' => true,
                'admin_booking_enabled'    => true,
            ],
            'hb_form_fields' => [
                'fields' => [
                    [
                        'key'         => 'first_name',
                        'label'       => __('First Name', 'hotel-booking'),
                        'type'        => 'text',
                        'required'    => true,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 1,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'last_name',
                        'label'       => __('Last Name', 'hotel-booking'),
                        'type'        => 'text',
                        'required'    => true,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 2,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'email',
                        'label'       => __('Email', 'hotel-booking'),
                        'type'        => 'email',
                        'required'    => true,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 3,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'phone',
                        'label'       => __('Phone', 'hotel-booking'),
                        'type'        => 'phone',
                        'required'    => true,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 4,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'country',
                        'label'       => __('Country', 'hotel-booking'),
                        'type'        => 'text',
                        'required'    => false,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 5,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'address',
                        'label'       => __('Address', 'hotel-booking'),
                        'type'        => 'text',
                        'required'    => false,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 6,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'city',
                        'label'       => __('City', 'hotel-booking'),
                        'type'        => 'text',
                        'required'    => false,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 7,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'state',
                        'label'       => __('State / Province', 'hotel-booking'),
                        'type'        => 'text',
                        'required'    => false,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 8,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'postal_code',
                        'label'       => __('Postal Code', 'hotel-booking'),
                        'type'        => 'text',
                        'required'    => false,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 9,
                        'system'      => true,
                    ],
                    [
                        'key'         => 'special_requests',
                        'label'       => __('Special Requests', 'hotel-booking'),
                        'type'        => 'textarea',
                        'required'    => false,
                        'enabled'     => true,
                        'placeholder' => '',
                        'sort_order'  => 10,
                        'system'      => true,
                    ],
                ],
            ],
        ];

        foreach ($defaults as $option_name => $values) {
            if (get_option($option_name) === false) {
                add_option($option_name, $values);
            }
        }
    }
}
