<?php

namespace HotelBooking\Services;

defined('ABSPATH') || exit;

final class OfflineGateway
{
    public function is_enabled(): bool
    {
        $settings = get_option('hb_payments', []);
        return !empty($settings['offline_payment_enabled']);
    }

    public function create_payment_record(int $booking_id, float $amount, string $currency): int
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'hb_payments',
            [
                'booking_id'     => $booking_id,
                'provider'       => 'offline',
                'transaction_id' => 'OFFLINE-' . strtoupper(bin2hex(random_bytes(8))),
                'amount'         => $amount,
                'currency'       => $currency,
                'status'         => 'pending',
                'payment_method' => 'offline',
                'gateway_response' => wp_json_encode(['method' => 'pay_at_hotel']),
                'created_at'     => current_time('mysql', true),
                'updated_at'     => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }
}
