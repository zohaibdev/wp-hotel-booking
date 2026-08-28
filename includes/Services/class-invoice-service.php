<?php

namespace HotelBooking\Services;

use HotelBooking\Logging\Logger;

defined('ABSPATH') || exit;

use function HotelBooking\hb_format_price;
use function HotelBooking\hb_get_option;

final class InvoiceService
{
    public function create_for_booking(int $booking_id): int|false
    {
        global $wpdb;

        $booking = (new BookingService())->get($booking_id);
        if (!$booking) {
            Logger::error('Cannot create invoice: booking not found', ['booking_id' => $booking_id]);
            return false;
        }

        $invoice_number = $this->generate_number();

        $result = $wpdb->insert(
            $wpdb->prefix . 'hb_invoices',
            [
                'booking_id'     => $booking_id,
                'invoice_number' => $invoice_number,
                'subtotal'       => (float) $booking->room_subtotal + (float) $booking->amenities_subtotal,
                'tax'            => (float) $booking->tax_amount,
                'discount'       => (float) $booking->discount_amount,
                'total'          => (float) $booking->total_amount,
                'currency'       => $booking->currency,
                'status'         => 'issued',
                'issued_at'      => current_time('mysql', true),
                'created_at'     => current_time('mysql', true),
            ],
            ['%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            Logger::error('Failed to create invoice', ['booking_id' => $booking_id]);
            return false;
        }

        Logger::info("Invoice {$invoice_number} created for booking {$booking->booking_number}");

        return (int) $wpdb->insert_id;
    }

    private function generate_number(): string
    {
        global $wpdb;

        $year = gmdate('Y');
        $table = $wpdb->prefix . 'hb_invoices';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE invoice_number LIKE %s",
            "INV-{$year}-%"
        ));

        $seq = $count + 1;

        return sprintf('INV-%s-%05d', $year, $seq);
    }

    /**
     * Invoice figures joined to the booking's payment-plan snapshot.
     *
     * The invoice row keeps the immutable totals; the paid/outstanding split comes
     * from the booking, which froze its own plan values at creation time.
     */
    public function get_payment_summary(int $booking_id): array
    {
        $booking = (new BookingService())->get($booking_id);

        if (!$booking) {
            return [];
        }

        $total      = (float) $booking->total_amount;
        $is_partial = ($booking->payment_plan ?? 'full_payment') === 'partial_payment'
            && (float) ($booking->remaining_balance ?? 0) > 0;

        $paid = $is_partial
            ? (float) $booking->initial_payment_amount
            : (($booking->payment_status ?? '') === 'paid' ? $total : 0.0);

        return [
            'payment_plan'      => $booking->payment_plan ?? 'full_payment',
            'is_partial'        => $is_partial,
            'booking_total'     => $total,
            'paid_amount'       => $paid,
            'remaining_balance' => $is_partial ? (float) $booking->remaining_balance : max(0.0, $total - $paid),
            'balance_due_date'  => $booking->balance_due_date ?? null,
            'payment_status'    => $booking->payment_status ?? 'pending',
        ];
    }

    public function get_by_booking(int $booking_id): ?object
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_invoices WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
            $booking_id
        ));
    }

    public function get(int $invoice_id): ?object
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_invoices WHERE id = %d",
            $invoice_id
        ));
    }
}
