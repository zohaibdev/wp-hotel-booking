<?php

namespace HotelBooking;

defined('ABSPATH') || exit;

function hb_get_option(string $group, string $key, mixed $default = ''): mixed
{
    $options = get_option($group, []);
    return $options[$key] ?? $default;
}

function hb_format_price(float $amount, ?string $currency_symbol = null, ?string $position = null): string
{
    if ($currency_symbol === null) {
        $currency_symbol = hb_get_option('hb_general', 'currency_symbol', '$');
    }
    if ($position === null) {
        $position = hb_get_option('hb_general', 'currency_position', 'before');
    }

    $formatted = number_format($amount, 2, '.', ',');

    return $position === 'before'
        ? $currency_symbol . $formatted
        : $formatted . $currency_symbol;
}

function hb_get_booking_statuses(): array
{
    return [
        'pending'   => __('Pending', 'hotel-booking'),
        'confirmed' => __('Confirmed', 'hotel-booking'),
        'cancelled' => __('Cancelled', 'hotel-booking'),
        'completed' => __('Completed', 'hotel-booking'),
    ];
}

function hb_get_payment_statuses(): array
{
    return [
        'pending'  => __('Pending', 'hotel-booking'),
        'paid'     => __('Paid', 'hotel-booking'),
        'failed'   => __('Failed', 'hotel-booking'),
        'refunded' => __('Refunded', 'hotel-booking'),
    ];
}

function hb_generate_booking_number(): string
{
    $prefix = 'HB';
    $date   = gmdate('ymd');
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    return "{$prefix}-{$date}-{$random}";
}

function hb_calculate_nights(\DateTimeInterface $check_in, \DateTimeInterface $check_out): int
{
    $diff = $check_in->diff($check_out);
    return max(1, $diff->days);
}

/**
 * Room status is opt-out: only an explicit 'inactive' disables a room.
 * Rooms saved outside the classic metabox (Quick Edit, REST, programmatic
 * insert) have no status meta at all, and those must still be bookable.
 */
function hb_is_room_active(int $room_id): bool
{
    return get_post_meta($room_id, '_hb_room_status', true) !== 'inactive';
}

/** Amenity status follows the same opt-out rule as room status. */
function hb_is_amenity_active(int $amenity_id): bool
{
    return get_term_meta($amenity_id, '_hb_status', true) !== 'inactive';
}

/**
 * Parse a Y-m-d string into a site-timezone DateTime pinned to midnight.
 * The '!' resets all unparsed fields, so no current wall-clock time leaks in
 * and date-only comparisons stay timezone-consistent.
 */
function hb_parse_date(string $date): ?\DateTimeImmutable
{
    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());

    if (!$dt) {
        return null;
    }

    $errors = \DateTimeImmutable::getLastErrors();
    if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
        return null;
    }

    return $dt;
}

/**
 * Cache-busting version for a bundled asset.
 *
 * Uses the file's modification time rather than HB_VERSION, because the plugin
 * version only changes on release: without this, edited CSS and JS keep the
 * same query string and browsers (and page caches) go on serving the previous
 * copy. Falls back to the plugin version if the file cannot be stat'ed.
 *
 * @param string $relative_path Path under the plugin root, e.g. 'assets/css/frontend.css'.
 */
function hb_asset_version(string $relative_path): string
{
    $file = HB_PLUGIN_DIR . ltrim($relative_path, '/');

    if (is_readable($file)) {
        $mtime = @filemtime($file);
        if ($mtime) {
            return HB_VERSION . '.' . $mtime;
        }
    }

    return HB_VERSION;
}

/** Current UTC timestamp in MySQL DATETIME format, for GMT-stored columns. */
function hb_now_gmt(): string
{
    return gmdate('Y-m-d H:i:s');
}

/**
 * Currencies Stripe treats as having no minor unit -- the amount is charged in
 * whole units, so multiplying by 100 would overcharge by 100x.
 * https://docs.stripe.com/currencies#zero-decimal
 */
function hb_zero_decimal_currencies(): array
{
    return [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];
}

/**
 * Currencies Stripe bills with three decimal places. The API requires the
 * minor-unit amount to be a multiple of 10.
 * https://docs.stripe.com/currencies#three-decimal
 */
function hb_three_decimal_currencies(): array
{
    return ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
}

/** Number of decimal places Stripe bills this currency with. */
function hb_currency_decimals(string $currency): int
{
    $currency = strtoupper($currency);

    if (in_array($currency, hb_zero_decimal_currencies(), true)) {
        return 0;
    }

    if (in_array($currency, hb_three_decimal_currencies(), true)) {
        return 3;
    }

    return 2;
}

/**
 * Convert a decimal amount into the smallest currency unit Stripe expects.
 * Three-decimal currencies are rounded to the nearest multiple of 10, which the
 * API mandates.
 */
function hb_to_minor_units(float $amount, string $currency): int
{
    $decimals = hb_currency_decimals($currency);
    $minor    = (int) round($amount * (10 ** $decimals));

    if ($decimals === 3) {
        $minor = (int) (round($minor / 10) * 10);
    }

    return $minor;
}

/** Inverse of hb_to_minor_units(), for rendering a gateway amount back. */
function hb_from_minor_units(int $minor, string $currency): float
{
    return round($minor / (10 ** hb_currency_decimals($currency)), 4);
}

function hb_get_amenity_pricing_types(): array
{
    return [
        'free'                 => __('Free', 'hotel-booking'),
        'fixed'                => __('Fixed Price', 'hotel-booking'),
        'per_night'            => __('Per Night', 'hotel-booking'),
        'per_person'           => __('Per Person', 'hotel-booking'),
        'per_person_per_night' => __('Per Person Per Night', 'hotel-booking'),
        'per_hour'             => __('Per Hour', 'hotel-booking'),
        'per_drop'             => __('Per Drop / Each Way', 'hotel-booking'),
    ];
}

/**
 * Pricing types whose quantity the guest chooses.
 *
 * per_person and per_person_per_night are included: the adult count is only the
 * *default*, and a guest may book the amenity for fewer people than are staying.
 */
function hb_quantity_driven_pricing_types(): array
{
    return ['per_hour', 'per_drop', 'per_person', 'per_person_per_night'];
}

/** Quantity-driven types whose bounds come from the party size, not term meta. */
function hb_person_scoped_pricing_types(): array
{
    return ['per_person', 'per_person_per_night'];
}

function hb_amenity_uses_quantity(string $pricing_type): bool
{
    return in_array($pricing_type, hb_quantity_driven_pricing_types(), true);
}

/** Unit noun shown beside a quantity control, e.g. "4 hours". */
function hb_amenity_quantity_label(string $pricing_type): string
{
    return match ($pricing_type) {
        'per_hour' => __('Hours', 'hotel-booking'),
        'per_drop' => __('Number of Drops', 'hotel-booking'),
        default    => __('Quantity', 'hotel-booking'),
    };
}

/** Short suffix for price display, e.g. "$10 / hour". */
function hb_amenity_pricing_suffix(string $pricing_type): string
{
    return match ($pricing_type) {
        'per_night'            => __('/ night', 'hotel-booking'),
        'per_person'           => __('/ person', 'hotel-booking'),
        'per_person_per_night' => __('/ person / night', 'hotel-booking'),
        'per_hour'             => __('/ hour', 'hotel-booking'),
        'per_drop'             => __('per drop', 'hotel-booking'),
        default                => '',
    };
}

function hb_get_amenity_types(): array
{
    return [
        'included' => __('Included (always part of the room)', 'hotel-booking'),
        'optional' => __('Optional (guest chooses)', 'hotel-booking'),
    ];
}

/**
 * Normalised amenity configuration, with defaults chosen so amenities created
 * before these fields existed keep behaving exactly as they did: a free amenity
 * is included, anything priced is optional.
 */
/**
 * @param array $context Booking context, currently 'adults'. Person-scoped
 *                       amenities default and cap to the adult count, so their
 *                       bounds cannot be resolved without it.
 */
function hb_get_amenity_config(int $amenity_id, array $context = []): array
{
    $pricing_type = (string) get_term_meta($amenity_id, '_hb_pricing_type', true) ?: 'free';

    if (!array_key_exists($pricing_type, hb_get_amenity_pricing_types())) {
        $pricing_type = 'free';
    }

    $stored_type = (string) get_term_meta($amenity_id, '_hb_amenity_type', true);
    if (!array_key_exists($stored_type, hb_get_amenity_types())) {
        $stored_type = $pricing_type === 'free' ? 'included' : 'optional';
    }

    $selectable_meta = get_term_meta($amenity_id, '_hb_customer_selectable', true);
    $customer_selectable = $selectable_meta === ''
        ? ($stored_type === 'optional')
        : (bool) $selectable_meta;

    // An included amenity is never opt-out.
    if ($stored_type === 'included') {
        $customer_selectable = false;
    }

    $uses_quantity = hb_amenity_uses_quantity($pricing_type);

    if (in_array($pricing_type, hb_person_scoped_pricing_types(), true)) {
        // One line per adult by default, and never more than the party size.
        $adults  = max(1, (int) ($context['adults'] ?? 1));
        $min_qty = 1;
        $max_qty = $adults;
        $default = $adults;
    } else {
        $min_qty = (int) get_term_meta($amenity_id, '_hb_min_quantity', true);
        $min_qty = $min_qty > 0 ? $min_qty : 1;

        $max_qty = (int) get_term_meta($amenity_id, '_hb_max_quantity', true);
        $max_qty = $max_qty > 0 ? max($max_qty, $min_qty) : ($pricing_type === 'per_hour' ? 24 : 10);

        $default = $min_qty;
    }

    return [
        'pricing_type'        => $pricing_type,
        'price'               => (float) get_term_meta($amenity_id, '_hb_price', true),
        'amenity_type'        => $stored_type,
        'is_included'         => $stored_type === 'included',
        'customer_selectable' => $customer_selectable,
        'uses_quantity'       => $uses_quantity,
        'min_quantity'        => $uses_quantity ? $min_qty : 1,
        'max_quantity'        => $uses_quantity ? $max_qty : 1,
        'default_quantity'    => $uses_quantity ? $default : 1,
    ];
}

function hb_get_payment_plans(): array
{
    return [
        'full_payment'    => __('Pay Full Amount', 'hotel-booking'),
        'partial_payment' => __('Partial Payment', 'hotel-booking'),
    ];
}

function hb_get_balance_payment_statuses(): array
{
    return [
        'not_applicable' => __('Not applicable', 'hotel-booking'),
        'pending'        => __('Pending', 'hotel-booking'),
        'paid'           => __('Paid', 'hotel-booking'),
        'failed'         => __('Failed', 'hotel-booking'),
        'refunded'       => __('Refunded', 'hotel-booking'),
    ];
}

/**
 * Booking-level payment status gains 'partially_paid' so a deposit is never
 * mistaken for settlement.
 */
function hb_get_payment_statuses_extended(): array
{
    return array_merge(
        ['partially_paid' => __('Partially Paid', 'hotel-booking')],
        hb_get_payment_statuses()
    );
}

/** Round to currency precision using the gateway's own decimal rules. */
function hb_round_currency(float $amount, string $currency = 'USD'): float
{
    $decimals = hb_currency_decimals($currency);
    return round($amount, $decimals);
}
