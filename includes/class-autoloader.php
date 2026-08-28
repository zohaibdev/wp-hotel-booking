<?php

namespace HotelBooking;

defined('ABSPATH') || exit;

final class Autoloader
{
    private const NAMESPACE_PREFIX = 'HotelBooking\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        if (!str_starts_with($class, self::NAMESPACE_PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::NAMESPACE_PREFIX));
        $parts = explode('\\', $relative);
        $class_name = array_pop($parts);
        $filename = 'class-' . str_replace('_', '-', strtolower(
            preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name)
        )) . '.php';

        $path = HB_PLUGIN_DIR . 'includes/';
        if ($parts) {
            $path .= implode('/', $parts) . '/';
        }
        $path .= $filename;

        if (file_exists($path)) {
            require_once $path;
        }
    }
}
