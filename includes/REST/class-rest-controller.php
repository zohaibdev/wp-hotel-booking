<?php

namespace HotelBooking\REST;

defined('ABSPATH') || exit;

abstract class RestController
{
    protected const NAMESPACE = 'hotel-booking/v1';

    abstract public function register_routes(): void;

    protected function verify_nonce(\WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }
        return false;
    }

    /**
     * Errors use a WP_REST_Response rather than a WP_Error so the body shape
     * matches the success envelope exactly; WP_Error would serialise to its own
     * {code, message, data} form and force the client to branch on two shapes.
     */
    protected function error(string $message, int $status = 400, string $code = 'hb_error'): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'data'    => [],
            'message' => $message,
            'code'    => $code,
        ], $status);
    }

    protected function success(array $data, string $message = '', int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $status);
    }
}
