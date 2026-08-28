<?php

namespace HotelBooking\REST;

use HotelBooking\Services\AvailabilityService;
use HotelBooking\Services\PricingService;
use HotelBooking\Services\BookingService;
use HotelBooking\Services\CustomerService;
use HotelBooking\Services\PaymentService;
use HotelBooking\Services\ReservationService;
use HotelBooking\Services\StripeGateway;
use HotelBooking\Services\OfflineGateway;
use HotelBooking\Services\FieldService;
use HotelBooking\Services\InvoiceService;
use HotelBooking\Services\EmailService;
use HotelBooking\Services\PaymentPlanService;
use HotelBooking\Services\TermsService;
use HotelBooking\Services\BookingFieldsService;
use HotelBooking\Logging\Logger;

defined('ABSPATH') || exit;

final class BookingController extends RestController
{
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/reservations/hold', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_hold'],
            'permission_callback' => '__return_true',
            'args'                => [
                'room_id'    => ['required' => true, 'sanitize_callback' => 'absint'],
                'check_in'   => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'check_out'  => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'adults'     => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'children'   => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'session_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/bookings/confirm', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'confirm'],
            'permission_callback' => '__return_true',
            'args'                => [
                'room_id'          => ['required' => true, 'sanitize_callback' => 'absint'],
                'check_in'         => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'check_out'        => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'check_in_time'    => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                'check_out_time'   => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                'adults'           => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'children'         => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 0],
                'amenity_ids'      => ['required' => false, 'default' => []],
                'amenities'        => ['required' => false, 'default' => []],
                'customer'         => ['required' => true],
                'payment_method'   => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'payment_intent_id' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                'session_id'       => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'payment_plan'     => ['required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => 'full_payment'],
                'terms_accepted'   => ['required' => false, 'default' => false],
            ],
        ]);
    }

    public function create_hold(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!$this->verify_nonce($request)) {
            return $this->error(__('Security check failed. Please refresh the page.', 'hotel-booking'), 403);
        }

        $room_id    = $request->get_param('room_id');
        $check_in   = $request->get_param('check_in');
        $check_out  = $request->get_param('check_out');
        $session_id = $request->get_param('session_id');

        // Same normalisation as every other endpoint, so a hidden field cannot
        // pass a different guest count here than at pricing time.
        $guests   = (new BookingFieldsService())->normalize_guests(
            $room_id,
            $request->get_param('adults'),
            $request->get_param('children')
        );
        $adults   = $guests['adults'];
        $children = $guests['children'];

        if (empty($session_id) || strlen($session_id) < 16) {
            return $this->error(__('Invalid session ID.', 'hotel-booking'));
        }

        $availability = new AvailabilityService();
        $result = $availability->check($room_id, $check_in, $check_out, $adults, $children, $session_id);

        if (!$result['available']) {
            return $this->error($result['error'], 422, 'hb_unavailable');
        }

        $reservation = new ReservationService();
        $hold_id = $reservation->create_hold($room_id, $session_id, $check_in, $check_out);

        if ($hold_id === false) {
            return $this->error(__('Could not create reservation hold.', 'hotel-booking'), 500);
        }

        return $this->success([
            'hold_id'    => $hold_id,
            'session_id' => $session_id,
        ]);
    }

    public function confirm(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!$this->verify_nonce($request)) {
            return $this->error(__('Security check failed. Please refresh the page.', 'hotel-booking'), 403);
        }

        global $wpdb;

        $room_id        = $request->get_param('room_id');
        $check_in       = $request->get_param('check_in');
        $check_out      = $request->get_param('check_out');
        $check_in_time  = $request->get_param('check_in_time') ?: '';
        $check_out_time = $request->get_param('check_out_time') ?: '';
        $customer_data  = (array) $request->get_param('customer');
        $payment_method = $request->get_param('payment_method');
        $intent_id      = $request->get_param('payment_intent_id');
        $session_id     = $request->get_param('session_id');
        $terms_accepted = filter_var($request->get_param('terms_accepted'), FILTER_VALIDATE_BOOLEAN);

        // Guest counts and clock times for any hidden field come from the room's
        // configuration, not the request.
        $fields   = new BookingFieldsService();
        $guests   = $fields->normalize_guests($room_id, $request->get_param('adults'), $request->get_param('children'));
        $adults   = $guests['adults'];
        $children = $guests['children'];

        $times          = $fields->normalize_times($room_id, $check_in_time, $check_out_time);
        $check_in_time  = $times['check_in_time'];
        $check_out_time = $times['check_out_time'];

        // Quantity-aware selection, falling back to the plain id list.
        $amenities_param = $request->get_param('amenities');
        $selection = (is_array($amenities_param) && !empty($amenities_param))
            ? $amenities_param
            : array_map('absint', (array) $request->get_param('amenity_ids'));

        if (empty($session_id) || strlen($session_id) < 16) {
            return $this->error(__('Invalid session ID.', 'hotel-booking'));
        }

        if (!in_array($payment_method, ['stripe', 'offline'], true)) {
            return $this->error(__('Invalid payment method.', 'hotel-booking'));
        }

        // Duplicate submission prevention: check if hold is already converted
        $holds_table = $wpdb->prefix . 'hb_reservation_holds';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $converted = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$holds_table} WHERE booking_session_id = %s AND status = 'converted'",
            $session_id
        ));
        if ((int) $converted > 0) {
            return $this->error(__('This booking has already been submitted.', 'hotel-booking'), 409, 'hb_duplicate');
        }

        // Terms acceptance is enforced here, not only in the UI -- a request that
        // skips the step must still be rejected while the step is enabled.
        $terms_service = new TermsService();
        $terms_check = $terms_service->validate_acceptance($terms_accepted);

        if (!$terms_check['valid']) {
            return $this->error($terms_check['error'], 422, 'hb_terms_required');
        }

        // Validate customer fields server-side
        $field_service = new FieldService();
        $field_result = $field_service->validate_fields($customer_data);

        if (!$field_result['valid']) {
            return $this->error(implode(' ', $field_result['errors']), 422);
        }

        // Server-side price recalculation
        $pricing = new PricingService();
        $totals = $pricing->calculate_booking_total($room_id, $check_in, $check_out, $adults, $children, $selection);

        // Deposit split, recalculated from settings rather than trusted.
        $plan = (new PaymentPlanService())->resolve(
            $totals['total_amount'],
            (string) $request->get_param('payment_plan'),
            $check_in,
            $totals['currency']
        );

        $charge_amount = $plan['initial_amount'];

        // Verify Stripe payment if applicable
        if ($payment_method === 'stripe') {
            if (empty($intent_id)) {
                return $this->error(__('Payment intent ID is required for Stripe payments.', 'hotel-booking'));
            }

            // Verified against the amount actually due now -- the deposit under a
            // partial plan -- so a deposit is not rejected for failing to match the
            // full total.
            $stripe = new StripeGateway();
            if (!$stripe->verify_payment($intent_id, $charge_amount, $totals['currency'])) {
                $reason = $stripe->get_last_error();
                return $this->error(
                    $reason !== '' ? $reason : __('Payment verification failed.', 'hotel-booking'),
                    422,
                    'hb_payment_unverified'
                );
            }
        }

        if ($payment_method === 'offline') {
            $offline = new OfflineGateway();
            if (!$offline->is_enabled()) {
                return $this->error(__('Offline payment is not available.', 'hotel-booking'));
            }
        }

        // Begin transaction for double-booking prevention
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query('START TRANSACTION');

        try {
            // Lock and verify hold
            $holds_table = $wpdb->prefix . 'hb_reservation_holds';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
            $hold = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$holds_table}
                 WHERE booking_session_id = %s AND status = 'active' AND expires_at > %s
                 FOR UPDATE",
                $session_id,
                \HotelBooking\hb_now_gmt()
            ));

            if (!$hold) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query('ROLLBACK');
                return $this->error(__('Your reservation hold has expired. Please start over.', 'hotel-booking'), 410);
            }

            // A succeeded PaymentIntent must back exactly one booking. Without this
            // the same intent could be replayed under a fresh session to obtain a
            // second booking on a single payment. The FOR UPDATE inside
            // transaction_exists() serialises concurrent attempts.
            $payment_service = new PaymentService();

            if ($payment_method === 'stripe'
                && $payment_service->transaction_exists('stripe', (string) $intent_id)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query('ROLLBACK');
                Logger::warning('Rejected replayed Stripe PaymentIntent', [
                    'intent_id'  => $intent_id,
                    'session_id' => $session_id,
                ]);
                return $this->error(
                    __('This payment has already been used for another booking.', 'hotel-booking'),
                    409,
                    'hb_payment_already_used'
                );
            }

            // Re-check availability within transaction
            $availability = new AvailabilityService();
            $remaining = $availability->get_remaining_inventory($room_id, $check_in, $check_out, $session_id);

            if ($remaining < 1) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query('ROLLBACK');
                return $this->error(__('Room is no longer available. Please try different dates.', 'hotel-booking'), 422);
            }

            // Create customer
            $customer_service = new CustomerService();
            $system_data = $field_result['data']['system'] ?? [];
            $custom_data = $field_result['data']['custom'] ?? [];

            $customer_id = $customer_service->find_or_create($system_data);

            if (!$customer_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query('ROLLBACK');
                return $this->error(__('Could not save customer information.', 'hotel-booking'), 500);
            }

            if (!empty($custom_data)) {
                $customer_service->save_meta($customer_id, $custom_data);
            }

            // Build check-in/check-out with time
            $ci_datetime = $check_in . ($check_in_time ? ' ' . $check_in_time . ':00' : ' 15:00:00');
            $co_datetime = $check_out . ($check_out_time ? ' ' . $check_out_time . ':00' : ' 11:00:00');

            // Create booking
            $booking_service = new BookingService();
            $booking_id = $booking_service->create([
                'room_id'            => $room_id,
                'customer_id'        => $customer_id,
                'check_in'           => $ci_datetime,
                'check_out'          => $co_datetime,
                'adults'             => $adults,
                'children'           => $children,
                'room_subtotal'      => $totals['room_subtotal'],
                'amenities_subtotal' => $totals['amenities_subtotal'],
                'tax_amount'         => $totals['tax_amount'],
                'discount_amount'    => $totals['discount_amount'],
                'total_amount'       => $totals['total_amount'],
                'currency'           => $totals['currency'],
                'payment_method'     => $payment_method,
                'special_requests'   => $system_data['special_requests'] ?? '',
                // Payment plan snapshot. balance_due_date is frozen here so a later
                // settings change cannot move a date the guest was already given.
                'payment_plan'               => $plan['payment_plan'],
                'initial_payment_percentage' => $plan['initial_percentage'],
                'initial_payment_amount'     => $plan['initial_amount'],
                'remaining_balance'          => $plan['remaining_balance'],
                'balance_due_date'           => $plan['balance_due_date'],
                'balance_payment_status'     => $plan['is_partial'] ? 'pending' : 'not_applicable',
                'terms'                      => $terms_service->get_acceptance_record($terms_accepted),
                'room_item'          => [
                    'item_id'      => $room_id,
                    'name'         => get_the_title($room_id),
                    'quantity'     => $totals['room']['nights'],
                    'unit_price'   => $totals['room']['base_price'],
                    'pricing_type' => 'per_night',
                    'subtotal'     => $totals['room']['subtotal'],
                ],
                'amenities' => $totals['amenities'],
            ]);

            if (!$booking_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query('ROLLBACK');
                return $this->error(__('Could not create booking.', 'hotel-booking'), 500);
            }

            // Create payment record ($payment_service is created above, with the
            // replay check, so the same instance is reused here). The recorded
            // amount is what was actually taken now, not the booking total.
            if ($payment_method === 'stripe') {
                $payment_service->create_stripe_record($booking_id, $intent_id, $charge_amount, $totals['currency']);
                $booking_service->update_status($booking_id, 'confirmed');

                // A deposit leaves a balance outstanding, so the booking is
                // partially paid -- never 'paid'.
                $booking_service->update_payment_status(
                    $booking_id,
                    $plan['is_partial'] ? 'partially_paid' : 'paid'
                );
                $booking_service->update_payment_plan_fields($booking_id, [
                    'initial_payment_status'         => 'paid',
                    'initial_payment_transaction_id' => (string) $intent_id,
                ]);
            } else {
                $offline_gw = new OfflineGateway();
                $offline_ref = $offline_gw->create_payment_record($booking_id, $charge_amount, $totals['currency']);
                $booking_service->update_status($booking_id, 'confirmed');
                $booking_service->update_payment_plan_fields($booking_id, [
                    'initial_payment_status'         => 'pending',
                    'initial_payment_transaction_id' => (string) $payment_service->get_transaction_id($booking_id),
                ]);
                unset($offline_ref);
            }

            // Convert hold
            $reservation = new ReservationService();
            $reservation->convert_to_booking($session_id);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query('COMMIT');

        } catch (\Throwable $e) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query('ROLLBACK');
            Logger::error('Booking creation failed: ' . $e->getMessage());
            return $this->error(__('An unexpected error occurred. Please try again.', 'hotel-booking'), 500);
        }

        // Post-commit work. Everything below is non-critical: the payment is
        // captured and the booking is committed, so a failure here must never turn
        // a paid booking into an error for the customer.
        $booking = $booking_service->get($booking_id);
        $booking_number = $booking->booking_number ?? '';

        // The real gateway identifier, read back from the payment row rather than
        // echoed from the request.
        $transaction_id = $payment_service->get_transaction_id($booking_id);

        try {
            $invoice_service = new InvoiceService();
            $invoice_service->create_for_booking($booking_id);
        } catch (\Throwable $e) {
            Logger::error('Invoice creation failed for booking ' . $booking_id . ': ' . $e->getMessage());
        }

        // Stamp the booking number onto the existing PaymentIntent. The intent was
        // created before the booking row existed, so the number could not be part of
        // the original metadata; updating is preferred over creating a second intent.
        if ($payment_method === 'stripe' && $intent_id) {
            try {
                $room_name = get_the_title($room_id) ?: '';
                (new StripeGateway())->update_payment_intent($intent_id, [
                    'booking_number' => $booking_number,
                    'booking_id'     => $booking_id,
                    'room_name'      => $room_name,
                    'room_id'        => $room_id,
                    'check_in'       => $booking->check_in ?? $check_in,
                    'check_out'      => $booking->check_out ?? $check_out,
                    'adults'         => $adults,
                    'children'       => $children,
                    'customer_name'  => trim(($system_data['first_name'] ?? '') . ' ' . ($system_data['last_name'] ?? '')),
                    'customer_email' => $system_data['email'] ?? '',
                    'customer_phone' => $system_data['phone'] ?? '',
                    'payment_method' => 'stripe',
                    'payment_plan'   => $plan['payment_plan'],
                    'booking_total'  => $totals['total_amount'],
                    'amount_paid_now' => $charge_amount,
                    'remaining_balance' => $plan['remaining_balance'],
                    'balance_due_date'  => substr((string) $plan['balance_due_date'], 0, 10),
                ]);
            } catch (\Throwable $e) {
                Logger::warning('Could not annotate PaymentIntent with the booking number', [
                    'booking_id' => $booking_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        try {
            $email_service = new EmailService();
            $email_service->send_booking_confirmation($booking_id);
            $email_service->send_admin_notification($booking_id);
        } catch (\Throwable $e) {
            Logger::error('Email sending failed for booking ' . $booking_id . ': ' . $e->getMessage());
        }

        return $this->success([
            'booking_id'     => $booking_id,
            'booking_number' => $booking_number,
            'total_amount'   => $totals['total_amount'],
            'currency'       => $totals['currency'],
            'payment_method' => $payment_method,
            'payment_status' => $booking->payment_status ?? ($payment_method === 'stripe' ? 'paid' : 'pending'),
            'transaction_id' => $transaction_id,
            'customer_email' => $system_data['email'] ?? '',
            'status'         => $booking->booking_status ?? 'confirmed',

            // Payment plan, so the confirmation screen can show what was paid now
            // and what remains without recalculating anything.
            'payment_plan'      => $plan['payment_plan'],
            'is_partial'        => $plan['is_partial'],
            'amount_paid_now'   => $charge_amount,
            'remaining_balance' => $plan['remaining_balance'],
            'balance_due_date'  => $plan['balance_due_date'],
            'terms_accepted'    => (bool) $terms_accepted,
        ]);
    }
}
