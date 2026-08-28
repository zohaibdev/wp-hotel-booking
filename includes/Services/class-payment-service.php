<?php

namespace HotelBooking\Services;

use HotelBooking\Logging\Logger;

defined('ABSPATH') || exit;

final class PaymentService
{
    public function create_stripe_record(
        int $booking_id,
        string $intent_id,
        float $amount,
        string $currency
    ): int {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'hb_payments',
            [
                'booking_id'       => $booking_id,
                'provider'         => 'stripe',
                'transaction_id'   => $intent_id,
                'amount'           => $amount,
                'currency'         => $currency,
                'status'           => 'paid',
                'payment_method'   => 'card',
                'gateway_response' => wp_json_encode(['payment_intent' => $intent_id]),
                'created_at'       => current_time('mysql', true),
                'updated_at'       => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function update_status(int $payment_id, string $status): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->update(
            $wpdb->prefix . 'hb_payments',
            ['status' => $status, 'updated_at' => current_time('mysql', true)],
            ['id' => $payment_id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    /**
     * True when this gateway transaction has already been recorded against a
     * booking. Must be called inside the confirm transaction: FOR UPDATE takes a
     * next-key lock on the transaction_id index so two concurrent replays of the
     * same PaymentIntent cannot both pass this check.
     */
    public function transaction_exists(string $provider, string $transaction_id): bool
    {
        global $wpdb;

        if ($transaction_id === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}hb_payments
             WHERE provider = %s AND transaction_id = %s
             LIMIT 1 FOR UPDATE",
            $provider,
            $transaction_id
        ));

        return $found !== null;
    }

    /**
     * The gateway's own identifier for the most recent payment on this booking --
     * a Stripe PaymentIntent id, or the generated OFFLINE-* reference. Read from
     * the payments table so the confirmation screen, emails and admin all show
     * the same value.
     */
    public function get_transaction_id(int $booking_id): string
    {
        $payment = $this->get_by_booking($booking_id);
        return (string) ($payment->transaction_id ?? '');
    }

    public function get_by_booking(int $booking_id): ?object
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_payments WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
            $booking_id
        ));
    }
}
