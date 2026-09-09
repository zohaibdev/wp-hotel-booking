<?php

namespace HotelBooking\Services;

use HotelBooking\Logging\Logger;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;
use function HotelBooking\hb_to_minor_units;
use function HotelBooking\hb_from_minor_units;

final class StripeGateway
{
    private const API_BASE = 'https://api.stripe.com/v1';

    /** The credential the API is called with -- a secret OR a restricted key. */
    private string $secret_key;
    private string $mode;

    private StripeKeyResolver $keys;

    /** Human-readable reason the last call failed, for surfacing upstream. */
    private string $last_error = '';

    public function __construct(?StripeKeyResolver $keys = null)
    {
        // One resolver decides mode + key type; nothing here reads the
        // credential options directly.
        $this->keys = $keys ?? new StripeKeyResolver();
        $this->mode = $this->keys->get_mode();
        $this->secret_key = $this->keys->get_api_key();
    }

    public function get_last_error(): string
    {
        return $this->last_error;
    }

    public function is_enabled(): bool
    {
        return !empty(hb_get_option('hb_payments', 'stripe_enabled', false));
    }

    /** The only Stripe credential that may reach the browser. */
    public function get_publishable_key(): string
    {
        return $this->keys->get_publishable_key();
    }

    public function get_key_type(): string
    {
        return $this->keys->get_key_type();
    }

    public function get_mode(): string
    {
        return $this->mode;
    }

    /**
     * Whether Stripe is usable: enabled and both keys present for the active mode.
     * Catches the half-configured case (publishable set, secret missing) that
     * otherwise only shows up as a 500 at payment time.
     */
    public function is_configured(): bool
    {
        // Delegates to the resolver so a key that is present but wrong for the
        // active mode or type still counts as misconfigured. Treating it as
        // ready would advertise card payment to guests and then fail the charge.
        return $this->is_enabled() && empty($this->keys->get_config_errors());
    }

    /**
     * Returns a list of configuration problems, for the admin notice and for
     * refusing to advertise Stripe to customers when it cannot work.
     */
    public function get_config_errors(): array
    {
        if (!$this->is_enabled()) {
            return [];
        }

        return $this->keys->get_config_errors();
    }

    /**
     * Authenticates against Stripe with the currently selected credential.
     *
     * Distinguishes "the key is wrong" (401) from "the key is valid but lacks
     * this permission" (403) -- the latter is the common case for a restricted
     * key scoped too narrowly, and is worth telling the admin precisely.
     *
     * The credential itself never appears in the returned message.
     *
     * @return array{success:bool, message:string}
     */
    public function test_connection(): array
    {
        if ($this->secret_key === '') {
            return [
                'success' => false,
                'message' => __('No API key is configured for the selected mode and key type.', 'hotel-booking'),
            ];
        }

        $response = wp_remote_get(self::API_BASE . '/payment_intents?limit=1', [
            'headers' => ['Authorization' => 'Bearer ' . $this->secret_key],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            Logger::warning('Stripe connection test could not reach the API', [
                'error' => $response->get_error_message(),
            ]);
            return [
                'success' => false,
                'message' => __('Could not reach Stripe. Check your server\'s outbound connection.', 'hotel-booking'),
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);
        $detail = is_array($body) ? ($body['error']['message'] ?? '') : '';

        if ($status === 200) {
            return [
                'success' => true,
                'message' => sprintf(
                    /* translators: 1: Test or Live, 2: secret key or restricted key */
                    __('Connected to Stripe in %1$s mode using your %2$s.', 'hotel-booking'),
                    $this->keys->get_mode_label(),
                    strtolower($this->keys->get_key_type_label())
                ),
            ];
        }

        if ($status === 401) {
            return [
                'success' => false,
                'message' => __('Stripe rejected the key. Check that it is correct and matches the selected mode.', 'hotel-booking'),
            ];
        }

        if ($status === 403) {
            return [
                'success' => false,
                'message' => __('The key is valid but lacks permission to read PaymentIntents. Grant your restricted key read and write access to PaymentIntents.', 'hotel-booking'),
            ];
        }

        Logger::warning('Stripe connection test returned an unexpected status', [
            'status' => $status,
            'error'  => $detail,
        ]);

        return [
            'success' => false,
            /* translators: %d: HTTP status code */
            'message' => sprintf(__('Stripe returned an unexpected response (HTTP %d).', 'hotel-booking'), $status),
        ];
    }

    /**
     * @param string $idempotency_key Reusing a key returns the original intent
     *                                instead of creating a duplicate authorisation.
     * @param array  $options         'receipt_email' associates the payer's address
     *                                with the payment (Stripe uses it for receipts
     *                                in live mode and for customer identification);
     *                                'description' is the dashboard summary line.
     */
    public function create_payment_intent(
        float $amount,
        string $currency,
        array $metadata = [],
        string $idempotency_key = '',
        array $options = []
    ): array|false {
        $this->last_error = '';

        if ($this->secret_key === '') {
            $this->last_error = __('Stripe is not configured.', 'hotel-booking');
            Logger::error('Stripe secret key not configured');
            return false;
        }

        $minor = hb_to_minor_units($amount, $currency);

        if ($minor <= 0) {
            $this->last_error = __('Invalid payment amount.', 'hotel-booking');
            Logger::error('Stripe amount resolved to zero or less', [
                'amount'   => $amount,
                'currency' => $currency,
            ]);
            return false;
        }

        $body = [
            'amount'               => $minor,
            'currency'             => strtolower($currency),
            'payment_method_types' => ['card'],
        ];

        // Associates the payer with the payment so Stripe can identify them and
        // issue a receipt. Stripe only emails receipts in live mode, and only once
        // per payment, so this cannot produce a duplicate of our own email.
        $receipt_email = isset($options['receipt_email']) ? sanitize_email((string) $options['receipt_email']) : '';
        if ($receipt_email !== '' && is_email($receipt_email)) {
            $body['receipt_email'] = $receipt_email;
        }

        if (!empty($options['description'])) {
            $body['description'] = $this->clean_meta((string) $options['description'], 350);
        }

        foreach ($this->normalize_metadata($metadata) as $k => $v) {
            $body["metadata[{$k}]"] = $v;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->secret_key,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];

        if ($idempotency_key !== '') {
            $headers['Idempotency-Key'] = $idempotency_key;
        }

        $response = wp_remote_post(self::API_BASE . '/payment_intents', [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->last_error = __('Could not reach the payment provider.', 'hotel-booking');
            Logger::error('Stripe API request failed', ['error' => $response->get_error_message()]);
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || !is_array($result) || empty($result['id'])) {
            $this->last_error = __('The payment provider rejected the request.', 'hotel-booking');
            Logger::error('Stripe PaymentIntent creation failed', [
                'status'      => $status,
                'error'       => $result['error']['message'] ?? 'Unknown error',
                'error_code'  => $result['error']['code'] ?? '',
                'sent_amount' => $minor,
                'currency'    => $currency,
            ]);
            return false;
        }

        return [
            'id'            => $result['id'],
            'client_secret' => $result['client_secret'] ?? '',
            'amount'        => (int) $result['amount'],
            'currency'      => $result['currency'] ?? strtolower($currency),
            'status'        => $result['status'] ?? '',
        ];
    }

    /**
     * Attach or overwrite metadata on an existing PaymentIntent.
     *
     * Used after the booking row exists to record the booking number against the
     * payment. Stripe permits metadata updates on a succeeded intent, so this
     * avoids creating a second PaymentIntent purely to carry the number.
     *
     * Failure here is non-fatal: the payment and booking are already correct and
     * only the dashboard annotation is missing.
     */
    public function update_payment_intent(string $intent_id, array $metadata, array $options = []): bool
    {
        $this->last_error = '';

        if ($this->secret_key === '' || $intent_id === '') {
            return false;
        }

        $body = [];

        foreach ($this->normalize_metadata($metadata) as $k => $v) {
            $body["metadata[{$k}]"] = $v;
        }

        if (!empty($options['description'])) {
            $body['description'] = $this->clean_meta((string) $options['description'], 350);
        }

        $receipt_email = isset($options['receipt_email']) ? sanitize_email((string) $options['receipt_email']) : '';
        if ($receipt_email !== '' && is_email($receipt_email)) {
            $body['receipt_email'] = $receipt_email;
        }

        if (empty($body)) {
            return false;
        }

        $response = wp_remote_post(self::API_BASE . '/payment_intents/' . rawurlencode($intent_id), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => $body,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            Logger::warning('Stripe metadata update failed (payment unaffected)', [
                'intent_id' => $intent_id,
                'error'     => $response->get_error_message(),
            ]);
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            Logger::warning('Stripe metadata update rejected (payment unaffected)', [
                'intent_id' => $intent_id,
                'status'    => $status,
                'error'     => $result['error']['message'] ?? '',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Stripe caps metadata at 50 keys, 40 chars per key and 500 per value, and
     * rejects non-scalar values. Trim rather than let the whole call fail.
     */
    private function normalize_metadata(array $metadata): array
    {
        $out = [];

        foreach ($metadata as $key => $value) {
            if (count($out) >= 50) {
                break;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }

            $k = substr(preg_replace('/[^A-Za-z0-9_]/', '_', (string) $key), 0, 40);
            if ($k === '') {
                continue;
            }

            $out[$k] = $this->clean_meta((string) $value, 500);
        }

        return $out;
    }

    /** Single-line, length-capped value safe for a Stripe metadata field. */
    private function clean_meta(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($value)));
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    public function retrieve_payment_intent(string $intent_id): array|false
    {
        $this->last_error = '';

        if ($this->secret_key === '' || $intent_id === '') {
            $this->last_error = __('Stripe is not configured.', 'hotel-booking');
            return false;
        }

        $response = wp_remote_get(self::API_BASE . '/payment_intents/' . rawurlencode($intent_id), [
            'headers' => ['Authorization' => 'Bearer ' . $this->secret_key],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->last_error = __('Could not reach the payment provider.', 'hotel-booking');
            Logger::error('Stripe retrieve failed', ['error' => $response->get_error_message()]);
            return false;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($result) || empty($result['id'])) {
            $this->last_error = __('Payment could not be verified.', 'hotel-booking');
            Logger::error('Stripe retrieve returned no intent', [
                'intent_id' => $intent_id,
                'status'    => wp_remote_retrieve_response_code($response),
            ]);
            return false;
        }

        return $result;
    }

    /**
     * Confirms the intent actually succeeded and that its amount and currency
     * match what the server independently calculated. The client never supplies
     * the expected figures.
     */
    public function verify_payment(string $intent_id, float $expected_amount, string $expected_currency): bool
    {
        $this->last_error = '';

        $intent = $this->retrieve_payment_intent($intent_id);
        if (!$intent) {
            return false;
        }

        if (($intent['status'] ?? '') !== 'succeeded') {
            $this->last_error = __('The payment has not completed.', 'hotel-booking');
            Logger::warning('Payment intent not succeeded', [
                'intent_id' => $intent_id,
                'status'    => $intent['status'] ?? 'unknown',
            ]);
            return false;
        }

        $expected_minor = hb_to_minor_units($expected_amount, $expected_currency);
        $actual_minor   = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);

        if ($actual_minor !== $expected_minor) {
            $this->last_error = __('The payment amount did not match the booking total.', 'hotel-booking');
            Logger::error('Payment amount mismatch', [
                'intent_id'       => $intent_id,
                'expected_minor'  => $expected_minor,
                'actual_minor'    => $actual_minor,
                'expected_amount' => $expected_amount,
                'currency'        => $expected_currency,
            ]);
            return false;
        }

        if (strtolower((string) ($intent['currency'] ?? '')) !== strtolower($expected_currency)) {
            $this->last_error = __('The payment currency did not match the booking.', 'hotel-booking');
            Logger::error('Payment currency mismatch', [
                'intent_id' => $intent_id,
                'expected'  => $expected_currency,
                'actual'    => $intent['currency'] ?? '',
            ]);
            return false;
        }

        return true;
    }

    /** Amount actually captured, in the site's decimal representation. */
    public function get_captured_amount(array $intent): float
    {
        $minor = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);
        return hb_from_minor_units($minor, (string) ($intent['currency'] ?? 'usd'));
    }
}
