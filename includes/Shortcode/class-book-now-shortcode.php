<?php

namespace HotelBooking\Shortcode;

use HotelBooking\PostTypes\RoomPostType;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;
use function HotelBooking\hb_is_room_active;
use function HotelBooking\hb_asset_version;

final class BookNowShortcode
{
    private static bool $popup_rendered = false;
    private static bool $assets_enqueued = false;

    public function register(): void
    {
        add_shortcode('hotel_book_now', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
    }

    public function render(array|string $atts): string
    {
        $atts = shortcode_atts([
            'room_id' => 0,
            'text'    => __('Book Now', 'hotel-booking'),
            'class'   => '',
        ], $atts, 'hotel_book_now');

        $room_id = absint($atts['room_id']);

        if (!$room_id || get_post_type($room_id) !== RoomPostType::SLUG) {
            return '';
        }

        if (get_post_status($room_id) !== 'publish') {
            return '';
        }

        if (!hb_is_room_active($room_id)) {
            return '';
        }

        $this->enqueue_assets();

        $classes = 'hb-book-now-btn';
        if (!empty($atts['class'])) {
            $classes .= ' ' . sanitize_html_class($atts['class']);
        }

        $output = sprintf(
            '<button type="button" class="%s" data-room-id="%d">%s</button>',
            esc_attr($classes),
            esc_attr($room_id),
            esc_html($atts['text'])
        );

        if (!self::$popup_rendered) {
            self::$popup_rendered = true;
            ob_start();
            include HB_PLUGIN_DIR . 'templates/frontend/booking-popup.php';
            $output .= ob_get_clean();
        }

        return $output;
    }

    public function maybe_enqueue_assets(): void
    {
        global $post;
        if ($post && has_shortcode($post->post_content, 'hotel_book_now')) {
            $this->enqueue_assets();
        }
    }

    private function enqueue_assets(): void
    {
        if (self::$assets_enqueued) {
            return;
        }
        self::$assets_enqueued = true;

        wp_enqueue_style(
            'hb-frontend',
            HB_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            hb_asset_version('assets/css/frontend.css')
        );

        wp_enqueue_script(
            'hb-utils',
            HB_PLUGIN_URL . 'assets/js/frontend/utils.js',
            [],
            hb_asset_version('assets/js/frontend/utils.js'),
            true
        );

        wp_enqueue_script(
            'hb-booking-popup',
            HB_PLUGIN_URL . 'assets/js/frontend/booking-popup.js',
            ['hb-utils'],
            hb_asset_version('assets/js/frontend/booking-popup.js'),
            true
        );

        $payment_settings = get_option('hb_payments', []);
        $payment_deps = ['hb-booking-popup'];

        // Only offer card payment when Stripe can actually complete a charge:
        // enabled with both keys present for the active mode. A half-configured
        // gateway otherwise fails at the final step, after the guest has filled
        // in everything.
        $stripe = new \HotelBooking\Services\StripeGateway();
        $stripe_ready = $stripe->is_configured();

        if ($stripe_ready) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
            $payment_deps[] = 'stripe-js';
        }

        wp_enqueue_script(
            'hb-payment',
            HB_PLUGIN_URL . 'assets/js/frontend/payment.js',
            $payment_deps,
            hb_asset_version('assets/js/frontend/payment.js'),
            true
        );

        $booking_settings = get_option('hb_booking', []);
        $general_settings = get_option('hb_general', []);

        wp_localize_script('hb-utils', 'hbBooking', [
            'rest_url'                    => esc_url_raw(rest_url('hotel-booking/v1/')),
            'nonce'                       => wp_create_nonce('wp_rest'),
            'currency'                    => $general_settings['currency'] ?? 'USD',
            'currency_symbol'             => $general_settings['currency_symbol'] ?? '$',
            'currency_position'           => $general_settings['currency_position'] ?? 'before',
            'default_check_in_time'       => $booking_settings['default_check_in_time'] ?? '15:00',
            'default_check_out_time'      => $booking_settings['default_check_out_time'] ?? '11:00',
            'allow_custom_check_in_time'  => !empty($booking_settings['allow_custom_check_in_time']),
            'allow_custom_check_out_time' => !empty($booking_settings['allow_custom_check_out_time']),
            'minimum_stay'                => (int) ($booking_settings['minimum_stay'] ?? 1),
            'stripe_enabled'              => $stripe_ready,
            'stripe_publishable_key'      => $stripe_ready ? $stripe->get_publishable_key() : '',
            'terms_enabled'               => (new \HotelBooking\Services\TermsService())->is_enabled(),
            'partial_payment_enabled'     => (new \HotelBooking\Services\PaymentPlanService())->is_partial_enabled(),
            'offline_enabled'             => !empty($payment_settings['offline_payment_enabled']),
            'offline_title'               => $payment_settings['offline_payment_title'] ?? __('Pay at Hotel', 'hotel-booking'),
            'offline_description'         => $payment_settings['offline_payment_description'] ?? '',
        ]);
    }

}
