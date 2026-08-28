<?php

namespace HotelBooking\Services;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;

/**
 * Resolves which booking fields a room actually collects, and normalises the
 * values that arrive for them.
 *
 * Visibility is server-side state: a disabled field's submitted value is
 * discarded outright rather than merely hidden with CSS, so a crafted request
 * cannot smuggle a guest count or check-in time past a field the hotel turned
 * off.
 */
final class BookingFieldsService
{
    public const FIELDS = ['checkin_time', 'checkout_time', 'adults', 'children'];

    public const OVERRIDE_GLOBAL   = 'global';
    public const OVERRIDE_ENABLED  = 'enabled';
    public const OVERRIDE_DISABLED = 'disabled';

    /** Room meta key holding a field's override. */
    public static function meta_key(string $field): string
    {
        return '_hb_field_' . $field;
    }

    public static function global_option_key(string $field): string
    {
        return 'enable_' . $field;
    }

    /** Global default for one field; every field ships enabled. */
    public function is_globally_enabled(string $field): bool
    {
        $stored = hb_get_option('hb_booking', self::global_option_key($field), null);

        // Absent means the setting predates this feature, so keep the old
        // behaviour of showing the field.
        return $stored === null ? true : (bool) $stored;
    }

    /** A room's override for one field: 'global' | 'enabled' | 'disabled'. */
    public function get_room_override(int $room_id, string $field): string
    {
        $value = (string) get_post_meta($room_id, self::meta_key($field), true);

        return in_array($value, [self::OVERRIDE_ENABLED, self::OVERRIDE_DISABLED], true)
            ? $value
            : self::OVERRIDE_GLOBAL;
    }

    /**
     * Effective visibility for every field on a room. A room-level Enable or
     * Disable always wins; 'global' defers to the site setting.
     *
     * @return array<string,bool>
     */
    public function get_visibility(int $room_id): array
    {
        $out = [];

        foreach (self::FIELDS as $field) {
            $override = $this->get_room_override($room_id, $field);

            $out[$field] = match ($override) {
                self::OVERRIDE_ENABLED  => true,
                self::OVERRIDE_DISABLED => false,
                default                 => $this->is_globally_enabled($field),
            };
        }

        return $out;
    }

    public function is_enabled(int $room_id, string $field): bool
    {
        return $this->get_visibility($room_id)[$field] ?? true;
    }

    /**
     * Guest counts to actually book with.
     *
     * When a field is switched off the request's value is ignored entirely:
     * adults fall back to the room's configured occupancy (a fixed-occupancy
     * room still needs a real headcount for per-person pricing) and children to
     * zero. Enabled fields are clamped to sane bounds but otherwise respected;
     * capacity is still validated downstream by AvailabilityService.
     *
     * @return array{adults:int, children:int}
     */
    public function normalize_guests(int $room_id, $adults, $children): array
    {
        $visibility = $this->get_visibility($room_id);

        if ($visibility['adults']) {
            $resolved_adults = max(1, (int) $adults);
        } else {
            $configured = (int) get_post_meta($room_id, '_hb_max_adults', true);
            $resolved_adults = max(1, $configured);
        }

        $resolved_children = $visibility['children'] ? max(0, (int) $children) : 0;

        return [
            'adults'   => $resolved_adults,
            'children' => $resolved_children,
        ];
    }

    /**
     * Check-in/check-out clock times, falling back to the configured hotel
     * defaults whenever the field is switched off or the value is malformed.
     *
     * @return array{check_in_time:string, check_out_time:string}
     */
    public function normalize_times(int $room_id, $check_in_time, $check_out_time): array
    {
        $visibility = $this->get_visibility($room_id);

        $default_in  = (string) hb_get_option('hb_booking', 'default_check_in_time', '15:00');
        $default_out = (string) hb_get_option('hb_booking', 'default_check_out_time', '11:00');

        $in = $visibility['checkin_time']
            ? $this->sanitize_time((string) $check_in_time, $default_in)
            : $this->sanitize_time($default_in, '15:00');

        $out = $visibility['checkout_time']
            ? $this->sanitize_time((string) $check_out_time, $default_out)
            : $this->sanitize_time($default_out, '11:00');

        return ['check_in_time' => $in, 'check_out_time' => $out];
    }

    /** HH:MM within a real 24h clock, otherwise the supplied fallback. */
    private function sanitize_time(string $value, string $fallback): string
    {
        $value = trim($value);

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) {
            return $value;
        }

        return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $fallback) ? $fallback : '00:00';
    }

    /** Shape handed to the frontend so it renders exactly the enabled fields. */
    public function get_frontend_config(int $room_id): array
    {
        $visibility = $this->get_visibility($room_id);
        $guests = $this->normalize_guests($room_id, 1, 0);
        $times  = $this->normalize_times($room_id, '', '');

        return [
            'fields'   => $visibility,
            // Values the server will use for whichever fields are hidden, so the
            // UI can display them read-only rather than pretending they are unset.
            'defaults' => [
                'adults'         => $guests['adults'],
                'children'       => $guests['children'],
                'check_in_time'  => $times['check_in_time'],
                'check_out_time' => $times['check_out_time'],
            ],
        ];
    }
}
