<?php

namespace HotelBooking\Services;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;

/**
 * The single authority on which Stripe credential is in play.
 *
 * Two axes decide it: the mode (test/live) and the API key type (a full secret
 * key, or a restricted key). Nothing else in the plugin reads the credential
 * options directly, so there is one place to audit.
 *
 * Server-side only. The publishable key is deliberately handled separately and
 * is the *only* Stripe credential that ever reaches the browser.
 */
final class StripeKeyResolver
{
    public const MODE_TEST = 'test';
    public const MODE_LIVE = 'live';

    public const TYPE_SECRET     = 'secret_key';
    public const TYPE_RESTRICTED = 'restricted_key';

    /** Option key holding the server-side credential for a mode + type. */
    public static function option_key(string $mode, string $type): string
    {
        $suffix = $type === self::TYPE_RESTRICTED ? 'restricted_key' : 'secret_key';

        return 'stripe_' . ($mode === self::MODE_LIVE ? 'live' : 'test') . '_' . $suffix;
    }

    public static function publishable_option_key(string $mode): string
    {
        return 'stripe_' . ($mode === self::MODE_LIVE ? 'live' : 'test') . '_publishable_key';
    }

    public function get_mode(): string
    {
        return hb_get_option('hb_payments', 'stripe_mode', self::MODE_TEST) === self::MODE_LIVE
            ? self::MODE_LIVE
            : self::MODE_TEST;
    }

    /**
     * Existing installs have no stored type, so they resolve to a secret key --
     * exactly what they were using before restricted keys existed.
     */
    public function get_key_type(): string
    {
        return hb_get_option('hb_payments', 'stripe_key_type', self::TYPE_SECRET) === self::TYPE_RESTRICTED
            ? self::TYPE_RESTRICTED
            : self::TYPE_SECRET;
    }

    /** The raw stored credential for an explicit mode + type. */
    public function get_stored_key(string $mode, string $type): string
    {
        return trim((string) hb_get_option('hb_payments', self::option_key($mode, $type), ''));
    }

    /**
     * The credential the Stripe client should authenticate with.
     *
     * Only the selected type is consulted -- there is deliberately no fallback
     * to the other type, because silently authenticating with a full secret key
     * when the admin chose a restricted one would defeat the point of choosing.
     */
    public function get_api_key(): string
    {
        return $this->get_stored_key($this->get_mode(), $this->get_key_type());
    }

    public function get_publishable_key(): string
    {
        return trim((string) hb_get_option(
            'hb_payments',
            self::publishable_option_key($this->get_mode()),
            ''
        ));
    }

    /** Human label for the active key type, for admin messages. */
    public function get_key_type_label(?string $type = null): string
    {
        return ($type ?? $this->get_key_type()) === self::TYPE_RESTRICTED
            ? __('Restricted key', 'hotel-booking')
            : __('Secret key', 'hotel-booking');
    }

    public function get_mode_label(?string $mode = null): string
    {
        return ($mode ?? $this->get_mode()) === self::MODE_LIVE
            ? __('Live', 'hotel-booking')
            : __('Test', 'hotel-booking');
    }

    /** Prefixes Stripe uses for a given mode + type. */
    public static function expected_prefixes(string $mode, string $type): array
    {
        $env = $mode === self::MODE_LIVE ? 'live' : 'test';

        return $type === self::TYPE_RESTRICTED
            ? ['rk_' . $env . '_']
            : ['sk_' . $env . '_'];
    }

    /**
     * Prefix check only. Stripe itself is the real authority on whether a
     * credential works, which is what the connection test is for -- this just
     * catches an obvious paste of the wrong key into the wrong box.
     */
    public function validate_format(string $key, string $mode, string $type): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        foreach (self::expected_prefixes($mode, $type) as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display form of a stored credential: enough to recognise which key is
     * saved, never enough to use it. The full value is never rendered.
     */
    public static function mask(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            return '';
        }

        // Keep the prefix (which identifies mode and type) and the last 4.
        $parts = explode('_', $key);
        $prefix = count($parts) >= 3
            ? $parts[0] . '_' . $parts[1] . '_'
            : substr($key, 0, 3);

        $tail = strlen($key) > 4 ? substr($key, -4) : '';

        return $prefix . '••••••••' . $tail;
    }

    /**
     * Configuration problems for the active mode + type, phrased for an admin.
     *
     * @return string[]
     */
    public function get_config_errors(): array
    {
        $mode = $this->get_mode();
        $type = $this->get_key_type();

        $mode_label = $this->get_mode_label($mode);
        $type_label = $this->get_key_type_label($type);

        $errors = [];

        if ($this->get_publishable_key() === '') {
            /* translators: %s: Test or Live */
            $errors[] = sprintf(__('Stripe %s publishable key is missing.', 'hotel-booking'), $mode_label);
        }

        $api_key = $this->get_api_key();

        if ($api_key === '') {
            $errors[] = sprintf(
                /* translators: 1: Test or Live, 2: Secret key or Restricted key */
                __('Stripe %1$s %2$s is missing. It is required because %2$s authentication is selected.', 'hotel-booking'),
                $mode_label,
                strtolower($type_label)
            );
        } elseif (!$this->validate_format($api_key, $mode, $type)) {
            $expected = implode(', ', array_map(
                static fn(string $p): string => $p . '…',
                self::expected_prefixes($mode, $type)
            ));

            $errors[] = sprintf(
                /* translators: 1: Test or Live, 2: key type, 3: expected prefix */
                __('The Stripe %1$s %2$s does not look right — it should begin with %3$s.', 'hotel-booking'),
                $mode_label,
                strtolower($type_label),
                $expected
            );
        }

        return $errors;
    }
}
