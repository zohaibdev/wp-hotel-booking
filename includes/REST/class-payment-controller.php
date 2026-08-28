<?php

namespace HotelBooking\REST;

use HotelBooking\Services\PricingService;
use HotelBooking\Services\StripeGateway;
use HotelBooking\Services\FieldService;
use HotelBooking\Services\PaymentPlanService;
use HotelBooking\Services\BookingFieldsService;
use HotelBooking\Logging\Logger;

defined('ABSPATH') || exit;

final class PaymentController extends RestController
{
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/payments/create-intent', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_intent'],
            'permission_callback' => '__return_true',
            'args'                => [
                'room_id'     => ['required' => true, 'sanitize_callback' => 'absint'],
                'check_in'    => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'check_out'   => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'adults'      => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'children'    => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'amenity_ids' => ['required' => false, 'default' => []],
                'amenities'   => ['required' => false, 'default' => []],
                'session_id'  => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                // Sanitised server-side through FieldService, never used for pricing.
                'customer'    => ['required' => false, 'default' => []],
                // Only selects which plan applies; amounts are always server-derived.
                'payment_plan' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => 'full_payment'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/fields', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_fields'],
            'permission_callback' => '__return_true',
        ]);

        // Server-calculated payment plan options for the payment step. The UI
        // never derives deposit amounts or due dates itself.
        register_rest_route(self::NAMESPACE, '/payment-plans', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'get_payment_plans'],
            'permission_callback' => '__return_true',
            'args'                => [
                'room_id'     => ['required' => true, 'sanitize_callback' => 'absint'],
                'check_in'    => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'check_out'   => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'adults'      => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'children'    => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'amenities'   => ['required' => false, 'default' => []],
                'amenity_ids' => ['required' => false, 'default' => []],
            ],
        ]);
    }

    public function get_payment_plans(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!$this->verify_nonce($request)) {
            return $this->error(__('Security check failed. Please refresh the page.', 'hotel-booking'), 403);
        }

        $room_id   = $request->get_param('room_id');
        $check_in  = $request->get_param('check_in');
        $check_out = $request->get_param('check_out');

        $guests   = (new BookingFieldsService())->normalize_guests(
            $room_id, $request->get_param('adults'), $request->get_param('children')
        );
        $adults   = $guests['adults'];
        $children = $guests['children'];

        $selection = $this->resolve_amenity_selection($request);

        $totals = (new PricingService())
            ->calculate_booking_total($room_id, $check_in, $check_out, $adults, $children, $selection);

        $plans = (new PaymentPlanService())
            ->get_options($totals['total_amount'], $check_in, $totals['currency']);

        return $this->success([
            'booking_total'      => $totals['total_amount'],
            'room_subtotal'      => $totals['room_subtotal'],
            'amenities_subtotal' => $totals['amenities_subtotal'],
            'currency'           => $totals['currency'],
            'amenities'          => $totals['amenities'],
            'plans'              => $plans,
        ]);
    }

    /**
     * Accepts the quantity-aware `amenities` payload, falling back to the plain
     * `amenity_ids` list so older clients keep working.
     */
    private function resolve_amenity_selection(\WP_REST_Request $request): array
    {
        $amenities = $request->get_param('amenities');

        if (is_array($amenities) && !empty($amenities)) {
            return $amenities;
        }

        $ids = $request->get_param('amenity_ids');

        return is_array($ids) ? array_map('absint', $ids) : [];
    }

    public function create_intent(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!$this->verify_nonce($request)) {
            return $this->error(__('Security check failed. Please refresh the page.', 'hotel-booking'), 403);
        }

        $room_id     = $request->get_param('room_id');
        $check_in    = $request->get_param('check_in');
        $check_out   = $request->get_param('check_out');
        $session_id  = $request->get_param('session_id');
        $selection   = $this->resolve_amenity_selection($request);

        // Hidden fields are resolved server-side, never taken from the request.
        $guests   = (new BookingFieldsService())->normalize_guests(
            $room_id, $request->get_param('adults'), $request->get_param('children')
        );
        $adults   = $guests['adults'];
        $children = $guests['children'];

        if (empty($session_id) || strlen($session_id) < 16) {
            return $this->error(__('Invalid session ID.', 'hotel-booking'));
        }

        $stripe = new StripeGateway();

        if (!$stripe->is_enabled()) {
            return $this->error(__('Card payment is not available.', 'hotel-booking'), 400, 'hb_stripe_disabled');
        }

        if (!$stripe->is_configured()) {
            // Do not leak which key is missing to the customer; the detail is logged
            // and shown to admins via the settings notice.
            Logger::error('Stripe create-intent blocked by configuration', [
                'errors' => $stripe->get_config_errors(),
            ]);
            return $this->error(
                __('Card payment is temporarily unavailable. Please choose another method.', 'hotel-booking'),
                503,
                'hb_stripe_misconfigured'
            );
        }

        $pricing = new PricingService();
        $totals = $pricing->calculate_booking_total($room_id, $check_in, $check_out, $adults, $children, $selection);

        if ($totals['total_amount'] <= 0) {
            return $this->error(__('Invalid booking total.', 'hotel-booking'));
        }

        // The guest picks a plan; the deposit amount and balance are computed here
        // from the stored settings, never taken from the request.
        $plan = (new PaymentPlanService())->resolve(
            $totals['total_amount'],
            (string) $request->get_param('payment_plan'),
            $check_in,
            $totals['currency']
        );

        $charge_amount = $plan['initial_amount'];

        if ($charge_amount <= 0) {
            return $this->error(__('Invalid payment amount.', 'hotel-booking'));
        }

        // Customer identity for the Stripe record. The booking row does not exist
        // yet at intent-creation time (it is created by /bookings/confirm after the
        // charge), so this comes from the submitted form -- but it is passed through
        // the same FieldService validation that later produces the customer row, so
        // Stripe and the database receive identical, sanitised values. Nothing here
        // touches pricing, which stays entirely server-derived.
        $customer_input = (array) $request->get_param('customer');
        $field_result = (new FieldService())->validate_fields($customer_input);
        $customer = $field_result['valid'] ? ($field_result['data']['system'] ?? []) : [];

        $customer_name  = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        $customer_email = $customer['email'] ?? '';
        $customer_phone = $customer['phone'] ?? '';

        // Room name is read from the database, never from the request.
        $room_name = get_the_title($room_id) ?: '';

        $metadata = [
            'room_id'        => $room_id,
            'room_name'      => $room_name,
            'session_id'     => $session_id,
            'check_in'       => $check_in,
            'check_out'      => $check_out,
            'nights'         => $totals['room']['nights'] ?? '',
            'adults'         => $adults,
            'children'       => $children,
            'customer_name'  => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'payment_method' => 'stripe',
            'payment_plan'   => $plan['payment_plan'],
            'booking_total'  => $totals['total_amount'],
        ];

        if ($plan['is_partial']) {
            $metadata['initial_payment_amount'] = $plan['initial_amount'];
            $metadata['remaining_balance']      = $plan['remaining_balance'];
            $metadata['balance_due_date']       = substr((string) $plan['balance_due_date'], 0, 10);
            $metadata['initial_percentage']     = $plan['initial_percentage'];
        }

        $amenity_ids = array_map(
            static fn(array $a): int => (int) $a['amenity_id'],
            $totals['amenities']
        );

        if (!empty($amenity_ids)) {
            $metadata['amenity_ids'] = implode(',', $amenity_ids);
        }

        $description = $room_name !== ''
            /* translators: 1: room name, 2: check-in date, 3: check-out date */
            ? sprintf(__('%1$s — %2$s to %3$s', 'hotel-booking'), $room_name, $check_in, $check_out)
            : '';

        if ($plan['is_partial'] && $description !== '') {
            $description .= sprintf(
                /* translators: %s: deposit percentage */
                __(' (%s%% deposit)', 'hotel-booking'),
                rtrim(rtrim(number_format($plan['initial_percentage'], 2, '.', ''), '0'), '.')
            );
        }

        // Keyed on the session, the exact priced booking, and the payer identity,
        // so a retry after a network blip reuses the original intent instead of
        // authorising twice. Stripe rejects a reused key whose request body differs,
        // so anything sent to the API -- including receipt_email -- must be in here.
        $idempotency_key = 'hb_' . hash('sha256', implode('|', [
            $session_id,
            $room_id,
            $check_in,
            $check_out,
            $adults,
            $children,
            implode(',', $amenity_ids),
            (string) $totals['total_amount'],
            $totals['currency'],
            $customer_name,
            $customer_email,
            $customer_phone,
            $description,
            $plan['payment_plan'],
            (string) $charge_amount,
        ]));

        $intent = $stripe->create_payment_intent(
            $charge_amount,
            $totals['currency'],
            $metadata,
            $idempotency_key,
            [
                'receipt_email' => $customer_email,
                'description'   => $description,
            ]
        );

        if (!$intent) {
            $reason = $stripe->get_last_error();
            return $this->error(
                $reason !== '' ? $reason : __('Could not initialize payment. Please try again.', 'hotel-booking'),
                502,
                'hb_stripe_intent_failed'
            );
        }

        return $this->success([
            'client_secret'     => $intent['client_secret'],
            'intent_id'         => $intent['id'],
            // The amount actually being charged now -- the deposit under a partial
            // plan, the full total otherwise.
            'amount'            => $charge_amount,
            'booking_total'     => $totals['total_amount'],
            'payment_plan'      => $plan['payment_plan'],
            'remaining_balance' => $plan['remaining_balance'],
            'balance_due_date'  => $plan['balance_due_date'],
            'currency'          => $totals['currency'],
        ]);
    }

    public function get_fields(): \WP_REST_Response
    {
        $field_service = new FieldService();
        return $this->success(['fields' => $field_service->get_fields_for_frontend()]);
    }
}
