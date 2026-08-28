<?php

namespace HotelBooking\Admin;

defined('ABSPATH') || exit;

use HotelBooking\Services\CustomerService;

use function HotelBooking\hb_format_price;
use function HotelBooking\hb_get_booking_statuses;
use function HotelBooking\hb_get_payment_statuses;

final class CustomerDetailPage
{
    public function render(int $customer_id): void
    {
        $customer = (new CustomerService())->get($customer_id);

        if (!$customer) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Customer not found.', 'hotel-booking') . '</p></div></div>';
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $meta_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT field_key, field_value FROM {$wpdb->prefix}hb_customer_meta WHERE customer_id = %d",
            $customer_id
        ));
        $meta = [];
        foreach ($meta_rows as $m) {
            $meta[$m->field_key] = $m->field_value;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_bookings WHERE customer_id = %d ORDER BY created_at DESC LIMIT 50",
            $customer_id
        ));

        $statuses         = hb_get_booking_statuses();
        $payment_statuses = hb_get_payment_statuses();
        $back_url         = admin_url('admin.php?page=hotel-booking-customers');

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">';
        echo '<a href="' . esc_url($back_url) . '" class="page-title-action" style="margin-right:8px;">&larr; ' . esc_html__('Back', 'hotel-booking') . '</a>';
        echo esc_html(trim($customer->first_name . ' ' . $customer->last_name) ?: __('Customer', 'hotel-booking'));
        echo '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<div class="hb-detail-grid">';

        // Customer info
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Customer Details', 'hotel-booking') . '</h3>';
        echo '<table class="widefat striped"><tbody>';
        $this->row(__('First Name', 'hotel-booking'), $customer->first_name);
        $this->row(__('Last Name', 'hotel-booking'), $customer->last_name);
        $this->row(__('Email', 'hotel-booking'), $customer->email);
        if (!empty($customer->phone)) {
            $this->row(__('Phone', 'hotel-booking'), $customer->phone);
        }
        if (!empty($customer->country)) {
            $this->row(__('Country', 'hotel-booking'), $customer->country);
        }
        if (!empty($customer->address)) {
            $this->row(__('Address', 'hotel-booking'), $customer->address);
        }
        if (!empty($customer->city)) {
            $this->row(__('City', 'hotel-booking'), $customer->city);
        }
        if (!empty($customer->state)) {
            $this->row(__('State', 'hotel-booking'), $customer->state);
        }
        if (!empty($customer->postal_code)) {
            $this->row(__('Postal Code', 'hotel-booking'), $customer->postal_code);
        }
        $this->row(__('Registered', 'hotel-booking'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($customer->created_at)));
        echo '</tbody></table>';

        if (!empty($meta)) {
            echo '<h4 style="margin:16px 0 8px;">' . esc_html__('Custom Fields', 'hotel-booking') . '</h4>';
            echo '<table class="widefat striped"><tbody>';
            foreach ($meta as $key => $val) {
                $this->row(ucwords(str_replace('_', ' ', $key)), $val);
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        // Stats card
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Summary', 'hotel-booking') . '</h3>';

        $total_spent = 0.0;
        foreach ($bookings as $b) {
            if (in_array($b->booking_status, ['confirmed', 'completed'], true)) {
                $total_spent += (float) $b->total_amount;
            }
        }

        echo '<table class="widefat striped"><tbody>';
        $this->row(__('Total Bookings', 'hotel-booking'), (string) count($bookings));
        $this->row(__('Total Spent', 'hotel-booking'), hb_format_price($total_spent));
        echo '</tbody></table>';
        echo '</div>';

        echo '</div>'; // .hb-detail-grid

        // Booking history
        if (!empty($bookings)) {
            echo '<div class="hb-booking-history">';
            echo '<h3>' . esc_html__('Booking History', 'hotel-booking') . '</h3>';
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Booking', 'hotel-booking') . '</th>';
            echo '<th>' . esc_html__('Room', 'hotel-booking') . '</th>';
            echo '<th>' . esc_html__('Dates', 'hotel-booking') . '</th>';
            echo '<th>' . esc_html__('Total', 'hotel-booking') . '</th>';
            echo '<th>' . esc_html__('Status', 'hotel-booking') . '</th>';
            echo '<th>' . esc_html__('Payment', 'hotel-booking') . '</th>';
            echo '</tr></thead><tbody>';

            $date_format = get_option('date_format');

            foreach ($bookings as $b) {
                $url = admin_url('admin.php?page=hotel-booking-bookings&action=view&id=' . (int) $b->id);
                $room = get_the_title((int) $b->room_id) ?: __('(deleted)', 'hotel-booking');
                $booking_label = $statuses[$b->booking_status] ?? $b->booking_status;
                $payment_label = $payment_statuses[$b->payment_status] ?? $b->payment_status;

                echo '<tr>';
                echo '<td><a href="' . esc_url($url) . '">' . esc_html($b->booking_number) . '</a></td>';
                echo '<td>' . esc_html($room) . '</td>';
                echo '<td>' . esc_html(wp_date($date_format, strtotime($b->check_in))) . ' &rarr; ' . esc_html(wp_date($date_format, strtotime($b->check_out))) . '</td>';
                echo '<td>' . esc_html(hb_format_price((float) $b->total_amount)) . '</td>';
                echo '<td><span class="hb-badge hb-badge--' . esc_attr($b->booking_status) . '">' . esc_html($booking_label) . '</span></td>';
                echo '<td><span class="hb-badge hb-badge--' . esc_attr($b->payment_status) . '">' . esc_html($payment_label) . '</span></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
        }

        echo '</div>'; // .wrap
    }

    private function row(string $label, string $value): void
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }
}
