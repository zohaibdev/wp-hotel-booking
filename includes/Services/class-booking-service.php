<?php

namespace HotelBooking\Services;

use HotelBooking\Logging\Logger;

defined('ABSPATH') || exit;

use function HotelBooking\hb_generate_booking_number;
use function HotelBooking\hb_get_option;

final class BookingService
{
    public function create(array $data): int|false
    {
        global $wpdb;

        $booking_number = hb_generate_booking_number();
        $terms = is_array($data['terms'] ?? null) ? $data['terms'] : [];

        $result = $wpdb->insert(
            $wpdb->prefix . 'hb_bookings',
            [
                'booking_number'     => $booking_number,
                'room_id'            => (int) $data['room_id'],
                'customer_id'        => (int) ($data['customer_id'] ?? 0),
                'check_in'           => $data['check_in'],
                'check_out'          => $data['check_out'],
                'adults'             => (int) $data['adults'],
                'children'           => (int) $data['children'],
                'room_subtotal'      => (float) $data['room_subtotal'],
                'amenities_subtotal' => (float) $data['amenities_subtotal'],
                'tax_amount'         => (float) ($data['tax_amount'] ?? 0),
                'discount_amount'    => (float) ($data['discount_amount'] ?? 0),
                'total_amount'       => (float) $data['total_amount'],
                'currency'           => sanitize_text_field($data['currency'] ?? hb_get_option('hb_general', 'currency', 'USD')),
                'booking_status'     => 'pending',
                'payment_status'     => 'pending',
                'payment_method'     => sanitize_text_field($data['payment_method'] ?? ''),
                'special_requests'   => sanitize_textarea_field($data['special_requests'] ?? ''),

                // Payment plan snapshot. Defaults describe a normal full payment,
                // so callers that know nothing about deposits behave as before.
                'payment_plan'               => sanitize_text_field($data['payment_plan'] ?? 'full_payment'),
                'initial_payment_percentage' => (float) ($data['initial_payment_percentage'] ?? 100),
                'initial_payment_amount'     => (float) ($data['initial_payment_amount'] ?? ($data['total_amount'] ?? 0)),
                'remaining_balance'          => (float) ($data['remaining_balance'] ?? 0),
                'balance_due_date'           => $data['balance_due_date'] ?? null,
                'initial_payment_status'     => sanitize_text_field($data['initial_payment_status'] ?? 'pending'),
                'balance_payment_status'     => sanitize_text_field($data['balance_payment_status'] ?? 'not_applicable'),
                'initial_payment_transaction_id' => sanitize_text_field($data['initial_payment_transaction_id'] ?? ''),
                'balance_payment_transaction_id' => sanitize_text_field($data['balance_payment_transaction_id'] ?? ''),

                'terms_accepted'     => !empty($terms['terms_accepted']) ? 1 : 0,
                'terms_accepted_at'  => $terms['terms_accepted_at'] ?? null,
                'terms_version'      => sanitize_text_field($terms['terms_version'] ?? ''),

                'created_at'         => current_time('mysql', true),
                'updated_at'         => current_time('mysql', true),
            ],
            [
                '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%f', '%f', '%f', '%f', '%f',
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s',
                '%d', '%s', '%s',
                '%s', '%s',
            ]
        );

        if ($result === false) {
            Logger::error('Failed to create booking', ['room_id' => $data['room_id']]);
            return false;
        }

        $booking_id = (int) $wpdb->insert_id;

        if (!empty($data['room_item'])) {
            $this->insert_booking_item($booking_id, $data['room_item']);
        }

        if (!empty($data['amenities']) && is_array($data['amenities'])) {
            foreach ($data['amenities'] as $amenity) {
                $this->insert_booking_amenity($booking_id, $amenity);
            }
        }

        Logger::info("Booking created: {$booking_number}", ['booking_id' => $booking_id]);

        return $booking_id;
    }

    private function insert_booking_item(int $booking_id, array $item): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'hb_booking_items',
            [
                'booking_id'   => $booking_id,
                'item_type'    => 'room',
                'item_id'      => (int) ($item['item_id'] ?? 0),
                'name'         => sanitize_text_field($item['name'] ?? ''),
                'quantity'     => (int) ($item['quantity'] ?? 1),
                'unit_price'   => (float) ($item['unit_price'] ?? 0),
                'pricing_type' => sanitize_text_field($item['pricing_type'] ?? 'per_night'),
                'subtotal'     => (float) ($item['subtotal'] ?? 0),
            ],
            ['%d', '%s', '%d', '%s', '%d', '%f', '%s', '%f']
        );
    }

    private function insert_booking_amenity(int $booking_id, array $amenity): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'hb_booking_amenities',
            [
                'booking_id'   => $booking_id,
                'amenity_id'   => (int) ($amenity['amenity_id'] ?? 0),
                'name'         => sanitize_text_field($amenity['name'] ?? ''),
                'pricing_type' => sanitize_text_field($amenity['pricing_type'] ?? 'free'),
                'amenity_type' => sanitize_text_field($amenity['amenity_type'] ?? 'optional'),
                'quantity'     => (int) ($amenity['quantity'] ?? 1),
                'unit_price'   => (float) ($amenity['unit_price'] ?? 0),
                'subtotal'     => (float) ($amenity['subtotal'] ?? 0),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%f', '%f']
        );
    }

    public function update_status(int $booking_id, string $status): bool
    {
        global $wpdb;

        $valid = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (!in_array($status, $valid, true)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update(
            $wpdb->prefix . 'hb_bookings',
            ['booking_status' => $status, 'updated_at' => current_time('mysql', true)],
            ['id' => $booking_id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function update_payment_status(int $booking_id, string $status): bool
    {
        global $wpdb;

        // 'partially_paid' marks a deposit taken with a balance still outstanding.
        $valid = ['pending', 'partially_paid', 'paid', 'failed', 'refunded'];
        if (!in_array($status, $valid, true)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update(
            $wpdb->prefix . 'hb_bookings',
            ['payment_status' => $status, 'updated_at' => current_time('mysql', true)],
            ['id' => $booking_id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Updates payment-plan columns only. Values are whitelisted per column so a
     * caller cannot write arbitrary fields through this method.
     */
    public function update_payment_plan_fields(int $booking_id, array $fields): bool
    {
        global $wpdb;

        $allowed = [
            'initial_payment_status'         => '%s',
            'balance_payment_status'         => '%s',
            'initial_payment_transaction_id' => '%s',
            'balance_payment_transaction_id' => '%s',
            'remaining_balance'              => '%f',
            'balance_due_date'               => '%s',
        ];

        $statuses = ['pending', 'paid', 'failed', 'refunded', 'not_applicable'];

        $update = [];
        $format = [];

        foreach ($fields as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }

            if (str_ends_with($key, '_status') && !in_array($value, $statuses, true)) {
                continue;
            }

            $update[$key] = $allowed[$key] === '%f'
                ? (float) $value
                : sanitize_text_field((string) $value);
            $format[] = $allowed[$key];
        }

        if (empty($update)) {
            return false;
        }

        $update['updated_at'] = current_time('mysql', true);
        $format[] = '%s';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->update(
            $wpdb->prefix . 'hb_bookings',
            $update,
            ['id' => $booking_id],
            $format,
            ['%d']
        ) !== false;
    }

    public function get(int $booking_id): ?object
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_bookings WHERE id = %d",
            $booking_id
        ));
    }

    public function get_by_number(string $booking_number): ?object
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_bookings WHERE booking_number = %s",
            $booking_number
        ));
    }
}
