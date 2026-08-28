<?php

namespace HotelBooking\REST;

use HotelBooking\Services\AvailabilityService;
use HotelBooking\Services\PricingService;
use HotelBooking\Services\BookingFieldsService;

defined('ABSPATH') || exit;

final class AvailabilityController extends RestController
{
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/availability/check', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'check'],
            'permission_callback' => '__return_true',
            'args'                => [
                'room_id'    => ['required' => true, 'sanitize_callback' => 'absint'],
                'check_in'   => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'check_out'  => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                // Optional: a room may not collect these at all, in which case the
                // server supplies the values itself.
                'adults'     => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'children'   => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'session_id' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function check(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $room_id    = $request->get_param('room_id');
        $check_in   = $request->get_param('check_in');
        $check_out  = $request->get_param('check_out');
        $session_id = $request->get_param('session_id');

        // A field the hotel switched off has its submitted value discarded and
        // replaced with the configured default, so hiding a field cannot be used
        // to smuggle a guest count past capacity checks or per-person pricing.
        $guests   = (new BookingFieldsService())->normalize_guests(
            $room_id,
            $request->get_param('adults'),
            $request->get_param('children')
        );
        $adults   = $guests['adults'];
        $children = $guests['children'];

        $availability = new AvailabilityService();
        $result = $availability->check($room_id, $check_in, $check_out, $adults, $children, $session_id);

        if (!$result['available']) {
            return $this->error($result['error'], 422, 'hb_unavailable');
        }

        $pricing = new PricingService();
        $room_cost = $pricing->calculate_room_subtotal($room_id, $check_in, $check_out);

        return $this->success([
            'available'           => true,
            // Echoed back so the UI shows the same figures the server booked with,
            // which matters when a field is hidden and defaulted.
            'adults'              => $adults,
            'children'            => $children,
            'remaining_inventory' => $result['remaining_inventory'],
            'nights'              => $result['nights'],
            'base_price'          => $room_cost['base_price'],
            'room_subtotal'       => $room_cost['subtotal'],
        ]);
    }
}
