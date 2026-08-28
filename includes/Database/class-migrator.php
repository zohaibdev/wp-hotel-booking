<?php

namespace HotelBooking\Database;

defined('ABSPATH') || exit;

final class Migrator
{
    private const VERSION_OPTION = 'hb_db_version';

    public static function run(): void
    {
        $installed_version = get_option(self::VERSION_OPTION, '0');

        if (version_compare($installed_version, HB_DB_VERSION, '>=')) {
            return;
        }

        self::create_tables();
        update_option(self::VERSION_OPTION, HB_DB_VERSION);
    }

    /**
     * Brings the schema forward after an in-place plugin update, where the
     * activation hook never fires. dbDelta only adds missing columns and indexes,
     * so existing booking rows are untouched and keep their column defaults.
     */
    public static function maybe_upgrade(): void
    {
        if (version_compare((string) get_option(self::VERSION_OPTION, '0'), HB_DB_VERSION, '>=')) {
            return;
        }

        // Guard against two concurrent requests both running dbDelta.
        if (!get_transient('hb_migration_running')) {
            set_transient('hb_migration_running', 1, 60);

            try {
                self::create_tables();
                update_option(self::VERSION_OPTION, HB_DB_VERSION);
                \HotelBooking\Logging\Logger::info('Database schema upgraded to ' . HB_DB_VERSION);
            } finally {
                delete_transient('hb_migration_running');
            }
        }
    }

    public static function create_tables(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (Schema::get_tables() as $sql) {
            dbDelta($sql);
        }
    }

    public static function drop_tables(): void
    {
        global $wpdb;

        $tables = array_keys(Schema::get_tables());

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::VERSION_OPTION);
    }
}
