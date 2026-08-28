<?php

namespace HotelBooking\Services;

use HotelBooking\Logging\Logger;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;
use function HotelBooking\hb_now_gmt;

final class ReservationService
{
    private const CRON_HOOK = 'hb_expire_reservation_holds';

    public function init(): void
    {
        // The custom interval must be registered BEFORE scheduling: wp_schedule_event()
        // validates the recurrence against the cron_schedules filter and silently
        // refuses (and warns via _doing_it_wrong) if the interval is not yet known.
        add_filter('cron_schedules', [$this, 'add_cron_schedule']);

        add_action(self::CRON_HOOK, [$this, 'expire_holds']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hb_five_minutes', self::CRON_HOOK);
        }
    }

    public function add_cron_schedule(array $schedules): array
    {
        $schedules['hb_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every 5 minutes', 'hotel-booking'),
        ];
        return $schedules;
    }

    public function create_hold(
        int $room_id,
        string $session_id,
        string $check_in,
        string $check_out,
        int $quantity = 1
    ): int|false {
        global $wpdb;

        $hold_minutes = max(1, (int) hb_get_option('hb_booking', 'temporary_hold_minutes', 10));
        $expires_at = gmdate('Y-m-d H:i:s', time() + ($hold_minutes * 60));

        // Release any existing hold for this session first
        $this->release_by_session($session_id);

        $result = $wpdb->insert(
            $wpdb->prefix . 'hb_reservation_holds',
            [
                'room_id'            => $room_id,
                'booking_session_id' => $session_id,
                'check_in'           => $check_in,
                'check_out'          => $check_out,
                'quantity'           => $quantity,
                'expires_at'         => $expires_at,
                'status'             => 'active',
                'created_at'         => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            Logger::error('Failed to create reservation hold', [
                'room_id'    => $room_id,
                'session_id' => $session_id,
            ]);
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    public function release_by_session(string $session_id): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $wpdb->prefix . 'hb_reservation_holds',
            ['status' => 'released'],
            [
                'booking_session_id' => $session_id,
                'status'             => 'active',
            ],
            ['%s'],
            ['%s', '%s']
        );
    }

    public function convert_to_booking(string $session_id): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $wpdb->prefix . 'hb_reservation_holds',
            ['status' => 'converted'],
            [
                'booking_session_id' => $session_id,
                'status'             => 'active',
            ],
            ['%s'],
            ['%s', '%s']
        );
    }

    public function expire_holds(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $affected = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}hb_reservation_holds
                 SET status = 'expired'
                 WHERE status = %s AND expires_at < %s",
                'active',
                hb_now_gmt()
            )
        );

        if ($affected > 0) {
            Logger::info("Expired {$affected} reservation hold(s)");
        }
    }

    public function get_active_hold(string $session_id): ?object
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_reservation_holds
             WHERE booking_session_id = %s AND status = 'active' AND expires_at > %s
             LIMIT 1",
            $session_id,
            hb_now_gmt()
        ));
    }
}
