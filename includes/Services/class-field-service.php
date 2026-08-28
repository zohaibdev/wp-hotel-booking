<?php

namespace HotelBooking\Services;

defined('ABSPATH') || exit;

final class FieldService
{
    public function get_enabled_fields(): array
    {
        $form_fields = get_option('hb_form_fields', []);
        $fields = $form_fields['fields'] ?? [];

        $enabled = array_filter($fields, static fn(array $f) => !empty($f['enabled']));

        usort($enabled, static fn(array $a, array $b) => ($a['sort_order'] ?? 999) <=> ($b['sort_order'] ?? 999));

        return $enabled;
    }

    public function validate_fields(array $data): array
    {
        $fields = $this->get_enabled_fields();
        $errors = [];
        $clean = [];

        $system_keys = [
            'first_name', 'last_name', 'email', 'phone',
            'country', 'address', 'city', 'state', 'postal_code', 'special_requests',
        ];

        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $data[$key] ?? '';

            if ($field['type'] === 'textarea') {
                $value = sanitize_textarea_field($value);
            } elseif ($field['type'] === 'email') {
                $value = sanitize_email($value);
            } else {
                $value = sanitize_text_field($value);
            }

            if (!empty($field['required']) && $value === '') {
                $errors[] = sprintf(
                    /* translators: %s: field label */
                    __('%s is required.', 'hotel-booking'),
                    $field['label']
                );
                continue;
            }

            if ($field['type'] === 'email' && $value !== '' && !is_email($value)) {
                $errors[] = sprintf(
                    /* translators: %s: field label */
                    __('%s must be a valid email address.', 'hotel-booking'),
                    $field['label']
                );
                continue;
            }

            if (in_array($key, $system_keys, true)) {
                $clean['system'][$key] = $value;
            } else {
                $clean['custom'][$key] = $value;
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true, 'data' => $clean];
    }

    public function get_fields_for_frontend(): array
    {
        $fields = $this->get_enabled_fields();
        $output = [];

        foreach ($fields as $field) {
            $output[] = [
                'key'         => $field['key'],
                'label'       => $field['label'],
                'type'        => $field['type'],
                'required'    => !empty($field['required']),
                'placeholder' => $field['placeholder'] ?? '',
                'options'     => $field['options'] ?? '',
            ];
        }

        return $output;
    }
}
