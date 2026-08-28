<?php

namespace HotelBooking\Services;

use HotelBooking\PostTypes\RoomPostType;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;
use function HotelBooking\hb_is_room_active;
use function HotelBooking\hb_parse_date;
use function HotelBooking\hb_now_gmt;

final class AvailabilityService
{
    public function check(
        int $room_id,
        string $check_in,
        string $check_out,
        int $adults,
        int $children,
        ?string $exclude_session_id = null
    ): array {
        $room = get_post($room_id);
        if (!$room || $room->post_type !== RoomPostType::SLUG || $room->post_status !== 'publish') {
            return ['available' => false, 'error' => __('Room not found.', 'hotel-booking')];
        }

        if (!hb_is_room_active($room_id)) {
            return ['available' => false, 'error' => __('This room is currently unavailable.', 'hotel-booking')];
        }

        $check_in_dt  = hb_parse_date($check_in);
        $check_out_dt = hb_parse_date($check_out);

        if (!$check_in_dt || !$check_out_dt) {
            return ['available' => false, 'error' => __('Invalid date format. Please use YYYY-MM-DD.', 'hotel-booking')];
        }

        // Both sides are site-timezone midnights, so this is a pure date compare.
        $today = new \DateTimeImmutable('today', wp_timezone());
        if ($check_in_dt < $today) {
            return ['available' => false, 'error' => __('Check-in date cannot be in the past.', 'hotel-booking')];
        }

        if ($check_out_dt <= $check_in_dt) {
            return ['available' => false, 'error' => __('Check-out must be after check-in.', 'hotel-booking')];
        }

        $min_stay = max(1, (int) hb_get_option('hb_booking', 'minimum_stay', 1));
        $nights = $check_in_dt->diff($check_out_dt)->days;
        if ($nights < $min_stay) {
            return [
                'available' => false,
                'error' => sprintf(
                    __('Minimum stay is %d night(s).', 'hotel-booking'),
                    $min_stay
                ),
            ];
        }

        $capacity = $this->validate_capacity($room_id, $adults, $children);
        if ($capacity !== true) {
            return ['available' => false, 'error' => $capacity];
        }

        $remaining = $this->get_remaining_inventory(
            $room_id,
            $check_in,
            $check_out,
            $exclude_session_id
        );

        if ($remaining < 1) {
            return ['available' => false, 'error' => __('No rooms available for the selected dates.', 'hotel-booking')];
        }

        return [
            'available'          => true,
            'remaining_inventory' => $remaining,
            'nights'             => $nights,
        ];
    }

    /**
     * @return bool|string true when the party fits, otherwise the reason why not.
     *
     * Typed bool|string rather than true|string: the standalone `true` type is
     * PHP 8.2+, and this plugin supports 8.1, where it is a fatal parse error.
     */
    public function validate_capacity(int $room_id, int $adults, int $children): bool|string
    {
        $max_adults   = (int) get_post_meta($room_id, '_hb_max_adults', true) ?: 1;
        $max_children = (int) get_post_meta($room_id, '_hb_max_children', true) ?: 0;
        $max_total    = (int) get_post_meta($room_id, '_hb_max_total_guests', true) ?: 1;

        if ($adults < 1) {
            return __('At least 1 adult is required.', 'hotel-booking');
        }

        if ($adults > $max_adults) {
            return sprintf(
                __('Maximum %d adult(s) allowed for this room.', 'hotel-booking'),
                $max_adults
            );
        }

        if ($children < 0) {
            return __('Children count cannot be negative.', 'hotel-booking');
        }

        if ($children > $max_children) {
            return sprintf(
                __('Maximum %d child(ren) allowed for this room.', 'hotel-booking'),
                $max_children
            );
        }

        if (($adults + $children) > $max_total) {
            return sprintf(
                __('Maximum %d total guest(s) allowed for this room.', 'hotel-booking'),
                $max_total
            );
        }

        return true;
    }

    public function get_remaining_inventory(
        int $room_id,
        string $check_in,
        string $check_out,
        ?string $exclude_session_id = null
    ): int {
        global $wpdb;

        $total_inventory = (int) get_post_meta($room_id, '_hb_inventory', true) ?: 0;
        if ($total_inventory < 1) {
            return 0;
        }

        $bookings_table = $wpdb->prefix . 'hb_bookings';
        $holds_table = $wpdb->prefix . 'hb_reservation_holds';

        // Count confirmed/pending bookings overlapping this date range.
        // Nights are date-based and the interval is half-open [check_in, check_out),
        // so compare on DATE() -- the columns are DATETIME carrying the hotel's
        // check-in/check-out clock times, and a raw comparison would treat a
        // same-day turnover (previous guest leaves 11:00, next arrives 15:00) as
        // a conflict and needlessly withhold inventory.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $booked = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table}
             WHERE room_id = %d
               AND booking_status IN ('pending', 'confirmed')
               AND DATE(check_in) < %s
               AND DATE(check_out) > %s",
            $room_id,
            $check_out,
            $check_in
        ));

        // Count active holds (not expired) overlapping this date range, excluding current session.
        // expires_at is written with gmdate(), so it must be compared against a GMT
        // timestamp -- MySQL NOW() is server-local and would drift on non-UTC servers.
        $hold_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$holds_table}
             WHERE room_id = %d
               AND status = 'active'
               AND expires_at > %s
               AND DATE(check_in) < %s
               AND DATE(check_out) > %s",
            $room_id,
            hb_now_gmt(),
            $check_out,
            $check_in
        );

        if ($exclude_session_id) {
            $hold_sql .= $wpdb->prepare(" AND booking_session_id != %s", $exclude_session_id);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $held = (int) $wpdb->get_var($hold_sql);

        return max(0, $total_inventory - $booked - $held);
    }
}
