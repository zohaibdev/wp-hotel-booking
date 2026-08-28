<?php

namespace HotelBooking;

defined('ABSPATH') || exit;

final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('hb_expire_reservation_holds');
    }
}
