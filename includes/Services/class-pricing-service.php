<?php

namespace HotelBooking\Services;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;
use function HotelBooking\hb_calculate_nights;
use function HotelBooking\hb_is_amenity_active;
use function HotelBooking\hb_parse_date;
use function HotelBooking\hb_get_amenity_config;

final class PricingService
{
    public function calculate_room_subtotal(int $room_id, string $check_in, string $check_out): array
    {
        $base_price = (float) get_post_meta($room_id, '_hb_base_price', true);

        $check_in_dt  = hb_parse_date($check_in);
        $check_out_dt = hb_parse_date($check_out);

        if (!$check_in_dt || !$check_out_dt) {
            return ['base_price' => $base_price, 'nights' => 0, 'subtotal' => 0.00];
        }

        $nights = hb_calculate_nights($check_in_dt, $check_out_dt);

        $subtotal = round($base_price * $nights, 2);

        return [
            'base_price' => $base_price,
            'nights'     => $nights,
            'subtotal'   => $subtotal,
        ];
    }

    /**
     * The single source of truth for amenity pricing across all supported models.
     *
     * @param int $requested_quantity Guest-chosen quantity, used only by the
     *                                quantity-driven models (per_hour, per_drop)
     *                                and clamped to the amenity's configured
     *                                min/max. Every other model derives its own
     *                                quantity from the stay, so a crafted value
     *                                cannot inflate or deflate the charge.
     */
    public function calculate_amenity_cost(
        int $amenity_id,
        int $nights,
        int $adults,
        int $children,
        ?int $requested_quantity = null
    ): array {
        $adults = max(0, $adults);
        $nights = max(0, $nights);

        $config = hb_get_amenity_config($amenity_id, ['adults' => $adults]);

        $pricing_type = $config['pricing_type'];
        $unit_price   = $config['price'];

        // Quantity-driven models clamp the guest's choice to the amenity's bounds
        // -- for person-scoped types that ceiling is the adult count, so a crafted
        // request can never bill for more people than are staying. Everything else
        // derives its own quantity from the stay and ignores the request entirely.
        if ($config['uses_quantity']) {
            $requested = $requested_quantity === null
                ? $config['default_quantity']
                : $requested_quantity;

            $quantity = max($config['min_quantity'], min($config['max_quantity'], $requested));
        } else {
            $quantity = $pricing_type === 'per_night' ? $nights : 1;
        }

        $subtotal = match ($pricing_type) {
            'free'                 => 0.00,
            'fixed'                => $unit_price,
            'per_night'            => $unit_price * $nights,
            // Person-scoped models bill the chosen headcount, not every adult.
            'per_person'           => $unit_price * $quantity,
            'per_person_per_night' => $unit_price * $quantity * $nights,
            'per_hour'             => $unit_price * $quantity,
            'per_drop'             => $unit_price * $quantity,
            default                => 0.00,
        };

        return [
            'amenity_id'       => $amenity_id,
            'pricing_type'     => $pricing_type,
            'amenity_type'     => $config['amenity_type'],
            'unit_price'       => $unit_price,
            'quantity'         => max(0, (int) $quantity),
            'uses_quantity'    => $config['uses_quantity'],
            'min_quantity'     => $config['min_quantity'],
            'max_quantity'     => $config['max_quantity'],
            'default_quantity' => $config['default_quantity'],
            'subtotal'         => round((float) $subtotal, 2),
        ];
    }

    /**
     * Flattens a selection into [amenity_id => requested_quantity].
     *
     * Accepts the legacy shape (a list of ids) and the quantity-aware shape
     * ([{id, quantity}]), so older callers keep working unchanged.
     *
     * @return array<int,int>
     */
    private function normalize_selection(array $selection): array
    {
        $out = [];

        foreach ($selection as $entry) {
            if (is_array($entry)) {
                $id = (int) ($entry['id'] ?? $entry['amenity_id'] ?? 0);
                // null means "not specified", so the amenity's own default applies
                // -- important for person-scoped types, whose default is the adult
                // count rather than 1.
                $qty = array_key_exists('quantity', $entry) && $entry['quantity'] !== null
                    ? max(1, (int) $entry['quantity'])
                    : null;
            } elseif (is_numeric($entry)) {
                $id  = (int) $entry;
                $qty = null;
            } else {
                continue;
            }

            if ($id <= 0) {
                continue;
            }

            // A repeated id keeps the larger explicit quantity rather than summing.
            if (!array_key_exists($id, $out) || $out[$id] === null) {
                $out[$id] = $qty;
            } elseif ($qty !== null) {
                $out[$id] = max($out[$id], $qty);
            }
        }

        return $out;
    }

    public function calculate_booking_total(
        int $room_id,
        string $check_in,
        string $check_out,
        int $adults,
        int $children,
        array $selected_amenity_ids = []
    ): array {
        $room = $this->calculate_room_subtotal($room_id, $check_in, $check_out);
        $nights = $room['nights'];

        $amenities = [];
        $amenities_subtotal = 0.00;

        // Only amenities actually assigned to this room may be priced in, so a
        // crafted request cannot attach arbitrary terms to the booking.
        $assigned = wp_get_post_terms($room_id, \HotelBooking\Taxonomies\AmenityTaxonomy::SLUG, ['fields' => 'ids']);
        $assigned = is_wp_error($assigned) ? [] : array_map('intval', $assigned);

        // Accepts either [12, 13] or [['id' => 12, 'quantity' => 4], ...].
        $requested = $this->normalize_selection($selected_amenity_ids);

        // Included amenities are part of the room whether or not the client echoed
        // them back, and are recorded as line items so the snapshot is complete.
        foreach ($assigned as $assigned_id) {
            if (hb_get_amenity_config($assigned_id)['is_included']
                && !array_key_exists($assigned_id, $requested)) {
                // null so the amenity's own default quantity applies.
                $requested[$assigned_id] = null;
            }
        }

        foreach ($requested as $amenity_id => $quantity) {
            $amenity_id = (int) $amenity_id;

            if (!in_array($amenity_id, $assigned, true)) {
                continue;
            }

            $term = get_term($amenity_id);
            if (!$term || is_wp_error($term)) {
                continue;
            }

            if (!hb_is_amenity_active($amenity_id)) {
                continue;
            }

            $config = hb_get_amenity_config($amenity_id, ['adults' => $adults]);

            // An optional amenity the admin has withdrawn from guest selection can
            // only enter a booking if it is included.
            if (!$config['is_included'] && !$config['customer_selectable']) {
                continue;
            }

            $cost = $this->calculate_amenity_cost(
                $amenity_id,
                $nights,
                $adults,
                $children,
                $quantity === null ? null : (int) $quantity
            );
            $cost['name'] = $term->name;
            $amenities[] = $cost;
            $amenities_subtotal += $cost['subtotal'];
        }

        $amenities_subtotal = round($amenities_subtotal, 2);
        $subtotal = round($room['subtotal'] + $amenities_subtotal, 2);
        $tax_amount = 0.00;
        $discount_amount = 0.00;
        $total = round($subtotal + $tax_amount - $discount_amount, 2);

        return [
            'room'               => $room,
            'amenities'          => $amenities,
            'room_subtotal'      => $room['subtotal'],
            'amenities_subtotal' => $amenities_subtotal,
            'subtotal'           => $subtotal,
            'tax_amount'         => $tax_amount,
            'discount_amount'    => $discount_amount,
            'total_amount'       => $total,
            'currency'           => hb_get_option('hb_general', 'currency', 'USD'),
        ];
    }
}
