<?php

namespace HotelBooking;

use HotelBooking\Admin\Admin;
use HotelBooking\PostTypes\RoomPostType;
use HotelBooking\Taxonomies\RoomCategoryTaxonomy;
use HotelBooking\Taxonomies\AmenityTaxonomy;
use HotelBooking\Shortcode\BookNowShortcode;
use HotelBooking\Customizer\Customizer;
use HotelBooking\Services\ReservationService;
use HotelBooking\REST\AvailabilityController;
use HotelBooking\REST\RoomController;
use HotelBooking\REST\AmenityController;
use HotelBooking\REST\BookingController;
use HotelBooking\REST\PaymentController;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void
    {
        load_plugin_textdomain('hotel-booking', false, dirname(HB_PLUGIN_BASENAME) . '/languages');

        // Activation only fires on activate; an in-place plugin update needs the
        // schema brought forward here or new columns would be missing.
        \HotelBooking\Database\Migrator::maybe_upgrade();

        $this->register_post_types();
        $this->register_taxonomies();

        if (is_admin()) {
            (new Admin())->init();
        }

        (new BookNowShortcode())->register();
        (new ReservationService())->init();

        add_action('rest_api_init', [$this, 'register_rest_routes']);
        $customizer = new Customizer();
        add_action('customize_register', [$customizer, 'register']);
        add_action('wp_head', [$customizer, 'inject_css'], 100);
    }

    private function register_post_types(): void
    {
        (new RoomPostType())->register();
    }

    private function register_taxonomies(): void
    {
        (new RoomCategoryTaxonomy())->register();
        (new AmenityTaxonomy())->register();
    }

    public function register_rest_routes(): void
    {
        (new AvailabilityController())->register_routes();
        (new RoomController())->register_routes();
        (new AmenityController())->register_routes();
        (new BookingController())->register_routes();
        (new PaymentController())->register_routes();
    }
}
