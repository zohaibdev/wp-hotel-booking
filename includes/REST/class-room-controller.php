<?php

namespace HotelBooking\REST;

use HotelBooking\PostTypes\RoomPostType;
use HotelBooking\Taxonomies\RoomCategoryTaxonomy;

defined('ABSPATH') || exit;

use function HotelBooking\hb_format_price;
use function HotelBooking\hb_is_room_active;

final class RoomController extends RestController
{
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/rooms/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_room'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => static fn($val) => is_numeric($val) && (int) $val > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function get_room(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $room_id = $request->get_param('id');
        $room = get_post($room_id);

        if (!$room || $room->post_type !== RoomPostType::SLUG || $room->post_status !== 'publish') {
            return $this->error(__('Room not found.', 'hotel-booking'), 404);
        }

        if (!hb_is_room_active($room_id)) {
            return $this->error(__('This room is currently unavailable.', 'hotel-booking'), 403, 'hb_room_inactive');
        }

        $gallery_ids = get_post_meta($room_id, '_hb_gallery', true) ?: [];
        $gallery = [];
        foreach ((array) $gallery_ids as $img_id) {
            $url = wp_get_attachment_image_url((int) $img_id, 'large');
            $thumb = wp_get_attachment_image_url((int) $img_id, 'medium');
            if ($url) {
                $gallery[] = ['id' => (int) $img_id, 'url' => $url, 'thumbnail' => $thumb ?: $url];
            }
        }

        $featured = get_the_post_thumbnail_url($room_id, 'large');

        $categories = wp_get_post_terms($room_id, RoomCategoryTaxonomy::SLUG, ['fields' => 'names']);

        return $this->success([
            'id'               => $room_id,
            'title'            => get_the_title($room_id),
            'description'      => wp_kses_post(wpautop($room->post_content)),
            'excerpt'          => wp_trim_words($room->post_content, 30),
            'featured_image'   => $featured ?: '',
            'gallery'          => $gallery,
            'categories'       => is_wp_error($categories) ? [] : $categories,
            'inventory'        => (int) get_post_meta($room_id, '_hb_inventory', true),
            'max_adults'       => (int) get_post_meta($room_id, '_hb_max_adults', true) ?: 1,
            'max_children'     => (int) get_post_meta($room_id, '_hb_max_children', true) ?: 0,
            'max_total_guests' => (int) get_post_meta($room_id, '_hb_max_total_guests', true) ?: 1,
            'base_price'       => (float) get_post_meta($room_id, '_hb_base_price', true),
            'price_unit'       => get_post_meta($room_id, '_hb_price_unit', true) ?: 'per_night',
            'formatted_price'  => hb_format_price((float) get_post_meta($room_id, '_hb_base_price', true)),
            // Which first-step fields this room actually collects, plus the values
            // the server will use for the ones it does not.
            'field_config'     => (new \HotelBooking\Services\BookingFieldsService())->get_frontend_config($room_id),
        ]);
    }
}
