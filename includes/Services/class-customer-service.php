<?php

namespace HotelBooking\Services;

defined('ABSPATH') || exit;

final class CustomerService
{
    public function find_or_create(array $data): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hb_customers';

        $email = sanitize_email($data['email'] ?? '');
        if (empty($email)) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s LIMIT 1",
            $email
        ));

        if ($existing) {
            $customer_id = (int) $existing;
            $this->update($customer_id, $data);
            return $customer_id;
        }

        return $this->create($data);
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'hb_customers',
            [
                'first_name'       => sanitize_text_field($data['first_name'] ?? ''),
                'last_name'        => sanitize_text_field($data['last_name'] ?? ''),
                'email'            => sanitize_email($data['email'] ?? ''),
                'phone'            => sanitize_text_field($data['phone'] ?? ''),
                'country'          => sanitize_text_field($data['country'] ?? ''),
                'address'          => sanitize_text_field($data['address'] ?? ''),
                'city'             => sanitize_text_field($data['city'] ?? ''),
                'state'            => sanitize_text_field($data['state'] ?? ''),
                'postal_code'      => sanitize_text_field($data['postal_code'] ?? ''),
                'special_requests' => sanitize_textarea_field($data['special_requests'] ?? ''),
                'created_at'       => current_time('mysql', true),
                'updated_at'       => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function update(int $customer_id, array $data): void
    {
        global $wpdb;

        $update = [];
        $format = [];

        $fields = ['first_name', 'last_name', 'phone', 'country', 'address', 'city', 'state', 'postal_code'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $update[$field] = sanitize_text_field($data[$field]);
                $format[] = '%s';
            }
        }

        if (isset($data['special_requests'])) {
            $update['special_requests'] = sanitize_textarea_field($data['special_requests']);
            $format[] = '%s';
        }

        if (empty($update)) {
            return;
        }

        $update['updated_at'] = current_time('mysql', true);
        $format[] = '%s';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $wpdb->prefix . 'hb_customers',
            $update,
            ['id' => $customer_id],
            $format,
            ['%d']
        );
    }

    public function save_meta(int $customer_id, array $custom_fields): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hb_customer_meta';

        foreach ($custom_fields as $key => $value) {
            $key = sanitize_key($key);
            $value = sanitize_text_field($value);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE customer_id = %d AND field_key = %s LIMIT 1",
                $customer_id,
                $key
            ));

            if ($existing) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->update($table, ['field_value' => $value], ['id' => (int) $existing], ['%s'], ['%d']);
            } else {
                $wpdb->insert($table, [
                    'customer_id' => $customer_id,
                    'field_key'   => $key,
                    'field_value' => $value,
                ], ['%d', '%s', '%s']);
            }
        }
    }

    public function get(int $customer_id): ?object
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_customers WHERE id = %d",
            $customer_id
        ));
    }
}
