<?php

namespace HotelBooking\Admin;

defined('ABSPATH') || exit;

use HotelBooking\Services\BookingService;
use HotelBooking\Services\CustomerService;
use HotelBooking\Services\InvoiceService;

use function HotelBooking\hb_format_price;
use function HotelBooking\hb_get_booking_statuses;
use function HotelBooking\hb_get_payment_statuses;
use function HotelBooking\hb_get_payment_statuses_extended;
use function HotelBooking\hb_get_balance_payment_statuses;

final class BookingDetailPage
{
    public function render(int $booking_id): void
    {
        $booking_service = new BookingService();
        $booking = $booking_service->get($booking_id);

        if (!$booking) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Booking not found.', 'hotel-booking') . '</p></div></div>';
            return;
        }

        $this->handle_status_update($booking_id, $booking_service);
        $booking = $booking_service->get($booking_id);

        $customer = null;
        if ($booking->customer_id) {
            $customer = (new CustomerService())->get((int) $booking->customer_id);
        }

        $invoice = (new InvoiceService())->get_by_booking($booking_id);

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $amenities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_booking_amenities WHERE booking_id = %d",
            $booking_id
        ));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_payments WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
            $booking_id
        ));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $customer_meta = [];
        if ($customer) {
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT field_key, field_value FROM {$wpdb->prefix}hb_customer_meta WHERE customer_id = %d",
                $customer->id
            ));
            foreach ($meta_rows as $m) {
                $customer_meta[$m->field_key] = $m->field_value;
            }
        }

        $statuses         = hb_get_booking_statuses();
        $payment_statuses = hb_get_payment_statuses_extended();
        $room_title       = get_the_title((int) $booking->room_id) ?: __('(deleted room)', 'hotel-booking');

        $back_url = admin_url('admin.php?page=hotel-booking-bookings');

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">';
        echo '<a href="' . esc_url($back_url) . '" class="page-title-action" style="margin-right:8px;">&larr; ' . esc_html__('Back', 'hotel-booking') . '</a>';
        echo esc_html(sprintf(__('Booking %s', 'hotel-booking'), $booking->booking_number));
        echo '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<div class="hb-detail-grid">';

        // Status update form
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Update Status', 'hotel-booking') . '</h3>';
        echo '<form method="post">';
        wp_nonce_field('hb_update_booking_status', 'hb_status_nonce');
        echo '<table class="form-table"><tbody>';

        echo '<tr><th>' . esc_html__('Booking Status', 'hotel-booking') . '</th><td>';
        echo '<select name="booking_status">';
        foreach ($statuses as $val => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($booking->booking_status, $val, false), esc_html($label));
        }
        echo '</select></td></tr>';

        if ($booking->payment_method === 'offline') {
            echo '<tr><th>' . esc_html__('Payment Status', 'hotel-booking') . '</th><td>';
            echo '<select name="payment_status">';
            foreach ($payment_statuses as $val => $label) {
                printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($booking->payment_status, $val, false), esc_html($label));
            }
            echo '</select></td></tr>';
        }

        echo '</tbody></table>';
        submit_button(__('Update', 'hotel-booking'));
        echo '</form>';
        echo '</div>';

        // Booking details
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Booking Details', 'hotel-booking') . '</h3>';
        echo '<table class="widefat striped"><tbody>';
        $this->detail_row(__('Room', 'hotel-booking'), $room_title);
        $this->detail_row(__('Check-in', 'hotel-booking'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->check_in)));
        $this->detail_row(__('Check-out', 'hotel-booking'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->check_out)));
        $this->detail_row(__('Adults', 'hotel-booking'), (string) $booking->adults);
        $this->detail_row(__('Children', 'hotel-booking'), (string) $booking->children);
        $this->detail_row(__('Status', 'hotel-booking'), $statuses[$booking->booking_status] ?? $booking->booking_status);
        if (!empty($booking->special_requests)) {
            $this->detail_row(__('Special Requests', 'hotel-booking'), $booking->special_requests);
        }
        echo '</tbody></table>';
        echo '</div>';

        // Customer info
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Customer', 'hotel-booking') . '</h3>';
        if ($customer) {
            $customer_url = admin_url('admin.php?page=hotel-booking-customers&action=view&id=' . $customer->id);
            echo '<table class="widefat striped"><tbody>';
            $this->detail_row(__('Name', 'hotel-booking'), '<a href="' . esc_url($customer_url) . '">' . esc_html(trim($customer->first_name . ' ' . $customer->last_name)) . '</a>', false);
            $this->detail_row(__('Email', 'hotel-booking'), $customer->email);
            if (!empty($customer->phone)) {
                $this->detail_row(__('Phone', 'hotel-booking'), $customer->phone);
            }
            if (!empty($customer->country)) {
                $this->detail_row(__('Country', 'hotel-booking'), $customer->country);
            }
            foreach ($customer_meta as $key => $val) {
                $this->detail_row(ucwords(str_replace('_', ' ', $key)), $val);
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No customer data.', 'hotel-booking') . '</p>';
        }
        echo '</div>';

        // Price breakdown
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Price Breakdown', 'hotel-booking') . '</h3>';
        echo '<table class="widefat striped"><tbody>';
        $this->detail_row(__('Room Subtotal', 'hotel-booking'), hb_format_price((float) $booking->room_subtotal));

        if (!empty($amenities)) {
            foreach ($amenities as $a) {
                $label = $a->name;
                if ((int) $a->quantity > 1) {
                    $label .= ' &times; ' . (int) $a->quantity;
                }
                $this->detail_row($label, hb_format_price((float) $a->subtotal), false);
            }
            $this->detail_row(__('Amenities Subtotal', 'hotel-booking'), hb_format_price((float) $booking->amenities_subtotal));
        }

        if ((float) $booking->tax_amount > 0) {
            $this->detail_row(__('Tax', 'hotel-booking'), hb_format_price((float) $booking->tax_amount));
        }
        if ((float) $booking->discount_amount > 0) {
            $this->detail_row(__('Discount', 'hotel-booking'), '-' . hb_format_price((float) $booking->discount_amount));
        }
        echo '<tr><th style="font-weight:700;">' . esc_html__('Total', 'hotel-booking') . '</th><td style="font-weight:700;">' . esc_html(hb_format_price((float) $booking->total_amount)) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        // Payment
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Payment', 'hotel-booking') . '</h3>';
        echo '<table class="widefat striped"><tbody>';
        $method_label = $booking->payment_method === 'stripe' ? __('Credit/Debit Card (Stripe)', 'hotel-booking') : __('Pay at Hotel', 'hotel-booking');
        $this->detail_row(__('Method', 'hotel-booking'), $method_label);
        $this->detail_row(__('Status', 'hotel-booking'), $payment_statuses[$booking->payment_status] ?? $booking->payment_status);
        if ($payment) {
            $this->detail_row(
                __('Transaction ID', 'hotel-booking'),
                $payment->transaction_id
                    ? '<code style="font-size:12px;word-break:break-all;">' . esc_html($payment->transaction_id) . '</code>'
                    : '&mdash;',
                false
            );
            $this->detail_row(__('Amount', 'hotel-booking'), hb_format_price((float) $payment->amount));
        }
        echo '</tbody></table>';
        echo '</div>';

        // Payment plan / outstanding balance
        $is_partial = ($booking->payment_plan ?? 'full_payment') === 'partial_payment';

        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Payment Plan', 'hotel-booking') . '</h3>';
        echo '<table class="widefat striped"><tbody>';

        $this->detail_row(
            __('Plan', 'hotel-booking'),
            $is_partial ? __('Partial payment (deposit)', 'hotel-booking') : __('Full payment', 'hotel-booking')
        );
        $this->detail_row(__('Booking Total', 'hotel-booking'), hb_format_price((float) $booking->total_amount));

        if ($is_partial) {
            $this->detail_row(
                __('Initial Payment', 'hotel-booking'),
                sprintf(
                    /* translators: 1: deposit amount, 2: percentage */
                    __('%1$s (%2$s%%)', 'hotel-booking'),
                    hb_format_price((float) $booking->initial_payment_amount),
                    rtrim(rtrim(number_format((float) $booking->initial_payment_percentage, 2, '.', ''), '0'), '.')
                )
            );
            $this->detail_row(__('Remaining Balance', 'hotel-booking'), hb_format_price((float) $booking->remaining_balance));
            $this->detail_row(
                __('Balance Due Date', 'hotel-booking'),
                !empty($booking->balance_due_date)
                    ? wp_date(get_option('date_format'), strtotime($booking->balance_due_date))
                    : '—'
            );
        }

        $plan_statuses = hb_get_balance_payment_statuses();
        $pay_statuses  = hb_get_payment_statuses();

        $this->detail_row(
            __('Initial Payment Status', 'hotel-booking'),
            $pay_statuses[$booking->initial_payment_status ?? ''] ?? ($booking->initial_payment_status ?: '—')
        );

        if ($is_partial) {
            $this->detail_row(
                __('Balance Payment Status', 'hotel-booking'),
                $plan_statuses[$booking->balance_payment_status ?? ''] ?? ($booking->balance_payment_status ?: '—')
            );
        }

        $this->detail_row(
            __('Initial Transaction ID', 'hotel-booking'),
            !empty($booking->initial_payment_transaction_id)
                ? '<code style="font-size:12px;word-break:break-all;">' . esc_html($booking->initial_payment_transaction_id) . '</code>'
                : '&mdash;',
            false
        );

        if ($is_partial) {
            $this->detail_row(
                __('Balance Transaction ID', 'hotel-booking'),
                !empty($booking->balance_payment_transaction_id)
                    ? '<code style="font-size:12px;word-break:break-all;">' . esc_html($booking->balance_payment_transaction_id) . '</code>'
                    : '&mdash;',
                false
            );
        }

        echo '</tbody></table>';
        echo '</div>';

        // Terms acceptance
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Terms &amp; Conditions', 'hotel-booking') . '</h3>';
        echo '<table class="widefat striped"><tbody>';
        $this->detail_row(
            __('Accepted', 'hotel-booking'),
            !empty($booking->terms_accepted) ? __('Yes', 'hotel-booking') : __('No', 'hotel-booking')
        );
        if (!empty($booking->terms_accepted_at)) {
            $this->detail_row(
                __('Accepted At', 'hotel-booking'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->terms_accepted_at))
            );
        }
        if (!empty($booking->terms_version)) {
            $this->detail_row(
                __('Terms Version', 'hotel-booking'),
                '<code style="font-size:11px;">' . esc_html($booking->terms_version) . '</code>',
                false
            );
        }
        echo '</tbody></table>';
        echo '</div>';

        // Invoice
        echo '<div class="hb-detail-card">';
        echo '<h3>' . esc_html__('Invoice', 'hotel-booking') . '</h3>';
        if ($invoice) {
            echo '<table class="widefat striped"><tbody>';
            $this->detail_row(__('Invoice #', 'hotel-booking'), $invoice->invoice_number);
            $this->detail_row(__('Status', 'hotel-booking'), ucfirst($invoice->status));
            $this->detail_row(__('Issued', 'hotel-booking'), $invoice->issued_at ? wp_date(get_option('date_format'), strtotime($invoice->issued_at)) : '&mdash;');
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No invoice generated.', 'hotel-booking') . '</p>';
        }
        echo '</div>';

        echo '</div>'; // .hb-detail-grid
        echo '</div>'; // .wrap
    }

    private function handle_status_update(int $booking_id, BookingService $service): void
    {
        if (!isset($_POST['hb_status_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hb_status_nonce'])), 'hb_update_booking_status')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $new_booking_status = sanitize_text_field(wp_unslash($_POST['booking_status'] ?? ''));
        $new_payment_status = sanitize_text_field(wp_unslash($_POST['payment_status'] ?? ''));

        if ($new_booking_status && array_key_exists($new_booking_status, hb_get_booking_statuses())) {
            $service->update_status($booking_id, $new_booking_status);
        }

        if ($new_payment_status && array_key_exists($new_payment_status, hb_get_payment_statuses_extended())) {
            $service->update_payment_status($booking_id, $new_payment_status);
        }

        echo '<div class="notice notice-success"><p>' . esc_html__('Booking updated.', 'hotel-booking') . '</p></div>';
    }

    private function detail_row(string $label, string $value, bool $escape_value = true): void
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>' . ($escape_value ? esc_html($value) : $value) . '</td></tr>';
    }
}
