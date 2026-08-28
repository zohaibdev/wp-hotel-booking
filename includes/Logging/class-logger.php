<?php

namespace HotelBooking\Logging;

defined('ABSPATH') || exit;

final class Logger
{
    private const LOG_OPTION = 'hb_error_log';

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        $entry = [
            'level'     => $level,
            'message'   => $message,
            'context'   => self::sanitize_context($context),
            'timestamp' => current_time('mysql', true),
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('[Hotel Booking][%s] %s %s', $level, $message, wp_json_encode($entry['context'])));
        }

        $log = get_option(self::LOG_OPTION, []);
        $log[] = $entry;

        if (count($log) > 500) {
            $log = array_slice($log, -500);
        }

        update_option(self::LOG_OPTION, $log, false);
    }

    private static function sanitize_context(array $context): array
    {
        $sensitive_keys = ['secret_key', 'password', 'card_number', 'cvv', 'cvc', 'client_secret', 'api_key', 'token', 'stripe_secret'];

        foreach ($context as $key => $value) {
            if (in_array(strtolower($key), $sensitive_keys, true)) {
                $context[$key] = '***REDACTED***';
            }
            if (is_array($value)) {
                $context[$key] = self::sanitize_context($value);
            }
        }

        return $context;
    }

    public static function get_log(int $limit = 100): array
    {
        $log = get_option(self::LOG_OPTION, []);
        return array_slice(array_reverse($log), 0, $limit);
    }

    public static function clear(): void
    {
        delete_option(self::LOG_OPTION);
    }
}
