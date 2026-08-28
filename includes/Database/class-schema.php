<?php

namespace HotelBooking\Database;

defined('ABSPATH') || exit;

final class Schema
{
    public static function get_tables(): array
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        return [
            "{$prefix}hb_bookings" => "CREATE TABLE {$prefix}hb_bookings (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booking_number varchar(32) NOT NULL,
                room_id bigint(20) unsigned NOT NULL,
                customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
                check_in datetime NOT NULL,
                check_out datetime NOT NULL,
                adults smallint(5) unsigned NOT NULL DEFAULT 1,
                children smallint(5) unsigned NOT NULL DEFAULT 0,
                room_subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
                amenities_subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
                tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                currency varchar(3) NOT NULL DEFAULT 'USD',
                booking_status varchar(20) NOT NULL DEFAULT 'pending',
                payment_status varchar(20) NOT NULL DEFAULT 'pending',
                payment_method varchar(30) NOT NULL DEFAULT '',
                special_requests text,
                payment_plan varchar(20) NOT NULL DEFAULT 'full_payment',
                initial_payment_percentage decimal(5,2) NOT NULL DEFAULT 100.00,
                initial_payment_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                remaining_balance decimal(10,2) NOT NULL DEFAULT 0.00,
                balance_due_date datetime DEFAULT NULL,
                initial_payment_status varchar(20) NOT NULL DEFAULT 'pending',
                balance_payment_status varchar(20) NOT NULL DEFAULT 'not_applicable',
                initial_payment_transaction_id varchar(255) NOT NULL DEFAULT '',
                balance_payment_transaction_id varchar(255) NOT NULL DEFAULT '',
                terms_accepted tinyint(1) NOT NULL DEFAULT 0,
                terms_accepted_at datetime DEFAULT NULL,
                terms_version varchar(64) NOT NULL DEFAULT '',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY booking_number (booking_number),
                KEY room_id (room_id),
                KEY customer_id (customer_id),
                KEY booking_status (booking_status),
                KEY payment_plan (payment_plan),
                KEY balance_due (balance_due_date, balance_payment_status),
                KEY dates_range (check_in, check_out)
            ) {$charset};",

            "{$prefix}hb_booking_items" => "CREATE TABLE {$prefix}hb_booking_items (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) unsigned NOT NULL,
                item_type varchar(20) NOT NULL DEFAULT 'room',
                item_id bigint(20) unsigned NOT NULL DEFAULT 0,
                name varchar(255) NOT NULL DEFAULT '',
                quantity int(10) unsigned NOT NULL DEFAULT 1,
                unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
                pricing_type varchar(30) NOT NULL DEFAULT '',
                subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
                PRIMARY KEY  (id),
                KEY booking_id (booking_id)
            ) {$charset};",

            "{$prefix}hb_booking_amenities" => "CREATE TABLE {$prefix}hb_booking_amenities (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) unsigned NOT NULL,
                amenity_id bigint(20) unsigned NOT NULL DEFAULT 0,
                name varchar(255) NOT NULL DEFAULT '',
                pricing_type varchar(30) NOT NULL DEFAULT 'free',
                amenity_type varchar(20) NOT NULL DEFAULT 'optional',
                quantity int(10) unsigned NOT NULL DEFAULT 1,
                unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
                subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
                PRIMARY KEY  (id),
                KEY booking_id (booking_id)
            ) {$charset};",

            "{$prefix}hb_customers" => "CREATE TABLE {$prefix}hb_customers (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                first_name varchar(100) NOT NULL DEFAULT '',
                last_name varchar(100) NOT NULL DEFAULT '',
                email varchar(200) NOT NULL DEFAULT '',
                phone varchar(50) NOT NULL DEFAULT '',
                country varchar(100) NOT NULL DEFAULT '',
                address varchar(255) NOT NULL DEFAULT '',
                city varchar(100) NOT NULL DEFAULT '',
                state varchar(100) NOT NULL DEFAULT '',
                postal_code varchar(20) NOT NULL DEFAULT '',
                special_requests text,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY email (email)
            ) {$charset};",

            "{$prefix}hb_customer_meta" => "CREATE TABLE {$prefix}hb_customer_meta (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                customer_id bigint(20) unsigned NOT NULL,
                field_key varchar(100) NOT NULL DEFAULT '',
                field_value longtext,
                PRIMARY KEY  (id),
                KEY customer_id (customer_id),
                KEY field_key (field_key)
            ) {$charset};",

            "{$prefix}hb_payments" => "CREATE TABLE {$prefix}hb_payments (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) unsigned NOT NULL,
                provider varchar(30) NOT NULL DEFAULT '',
                transaction_id varchar(255) NOT NULL DEFAULT '',
                amount decimal(10,2) NOT NULL DEFAULT 0.00,
                currency varchar(3) NOT NULL DEFAULT 'USD',
                status varchar(20) NOT NULL DEFAULT 'pending',
                payment_method varchar(30) NOT NULL DEFAULT '',
                gateway_response text,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY booking_id (booking_id),
                KEY transaction_id (transaction_id)
            ) {$charset};",

            "{$prefix}hb_invoices" => "CREATE TABLE {$prefix}hb_invoices (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) unsigned NOT NULL,
                invoice_number varchar(32) NOT NULL,
                subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
                tax decimal(10,2) NOT NULL DEFAULT 0.00,
                discount decimal(10,2) NOT NULL DEFAULT 0.00,
                total decimal(10,2) NOT NULL DEFAULT 0.00,
                currency varchar(3) NOT NULL DEFAULT 'USD',
                status varchar(20) NOT NULL DEFAULT 'issued',
                issued_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY invoice_number (invoice_number),
                KEY booking_id (booking_id)
            ) {$charset};",

            "{$prefix}hb_reservation_holds" => "CREATE TABLE {$prefix}hb_reservation_holds (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                room_id bigint(20) unsigned NOT NULL,
                booking_session_id varchar(64) NOT NULL DEFAULT '',
                check_in datetime NOT NULL,
                check_out datetime NOT NULL,
                quantity smallint(5) unsigned NOT NULL DEFAULT 1,
                expires_at datetime NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY room_id (room_id),
                KEY booking_session_id (booking_session_id),
                KEY expires_at (expires_at),
                KEY status_expires (status, expires_at)
            ) {$charset};",
        ];
    }
}
