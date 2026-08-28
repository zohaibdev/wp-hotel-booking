<?php

namespace HotelBooking\REST;

use HotelBooking\PostTypes\RoomPostType;
use HotelBooking\Taxonomies\AmenityTaxonomy;

defined('ABSPATH') || exit;

use function HotelBooking\hb_is_amenity_active;
use function HotelBooking\hb_get_amenity_config;
use function HotelBooking\hb_amenity_quantity_label;
use function HotelBooking\hb_amenity_pricing_suffix;

final class AmenityController extends RestController
{
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/rooms/(?P<id>\d+)/amenities', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_amenities'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => static fn($val) => is_numeric($val) && (int) $val > 0,
                    'sanitize_callback' => 'absint',
                ],
                // Person-scoped amenities default and cap to the party size, so
                // the quantity contract cannot be resolved without it.
                'adults' => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 1],
            ],
        ]);
    }

    public function get_amenities(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $room_id = $request->get_param('id');
        $adults  = max(1, (int) $request->get_param('adults'));
        $room = get_post($room_id);

        if (!$room || $room->post_type !== RoomPostType::SLUG) {
            return $this->error(__('Room not found.', 'hotel-booking'), 404);
        }

        $terms = wp_get_post_terms($room_id, AmenityTaxonomy::SLUG);
        if (is_wp_error($terms)) {
            return $this->error(__('Could not load amenities.', 'hotel-booking'), 500);
        }

        $amenities = [];
        foreach ($terms as $term) {
            if (!hb_is_amenity_active($term->term_id)) {
                continue;
            }

            $config = hb_get_amenity_config($term->term_id, ['adults' => $adults]);

            // Withdrawn optional amenities are not offered at all.
            if (!$config['is_included'] && !$config['customer_selectable']) {
                continue;
            }

            $amenities[] = [
                'id'                  => $term->term_id,
                'name'                => $term->name,
                'description'         => get_term_meta($term->term_id, '_hb_description', true) ?: '',
                'icon'                => get_term_meta($term->term_id, '_hb_icon', true) ?: '',
                'pricing_type'        => $config['pricing_type'],
                'price'               => $config['price'],
                'unit_price'          => $config['price'],
                'amenity_type'        => $config['amenity_type'],
                'is_included'         => $config['is_included'],
                'is_free'             => $config['pricing_type'] === 'free',
                'customer_selectable' => $config['customer_selectable'],
                'quantity_required'   => $config['uses_quantity'],
                'uses_quantity'       => $config['uses_quantity'],
                'quantity_default'    => $config['default_quantity'],
                'quantity_min'        => $config['min_quantity'],
                'quantity_max'        => $config['max_quantity'],
                'min_quantity'        => $config['min_quantity'],
                'max_quantity'        => $config['max_quantity'],
                'quantity_label'      => hb_amenity_quantity_label($config['pricing_type']),
                'price_suffix'        => hb_amenity_pricing_suffix($config['pricing_type']),
            ];
        }

        return $this->success(['amenities' => $amenities]);
    }
}
