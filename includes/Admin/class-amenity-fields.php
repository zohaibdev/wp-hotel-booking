<?php

namespace HotelBooking\Admin;

use HotelBooking\Taxonomies\AmenityTaxonomy;

defined('ABSPATH') || exit;

use function HotelBooking\hb_format_price;
use function HotelBooking\hb_get_amenity_pricing_types;
use function HotelBooking\hb_get_amenity_types;
use function HotelBooking\hb_get_amenity_config;
use function HotelBooking\hb_amenity_uses_quantity;
use function HotelBooking\hb_quantity_driven_pricing_types;

final class AmenityFields
{
    public function init(): void
    {
        $taxonomy = AmenityTaxonomy::SLUG;

        add_action("{$taxonomy}_add_form_fields", [$this, 'render_add_fields']);
        add_action("{$taxonomy}_edit_form_fields", [$this, 'render_edit_fields'], 10, 2);
        add_action("created_{$taxonomy}", [$this, 'save_fields']);
        add_action("edited_{$taxonomy}", [$this, 'save_fields']);

        add_filter("manage_edit-{$taxonomy}_columns", [$this, 'add_columns']);
        add_filter("manage_{$taxonomy}_custom_column", [$this, 'render_column'], 10, 3);
    }

    public function render_add_fields(): void
    {
        ?>
        <div class="form-field">
            <label for="hb_amenity_description"><?php esc_html_e('Amenity Description', 'hotel-booking'); ?></label>
            <textarea id="hb_amenity_description" name="hb_amenity_description" rows="3"></textarea>
        </div>
        <div class="form-field">
            <label for="hb_amenity_icon"><?php esc_html_e('Icon (CSS class or emoji)', 'hotel-booking'); ?></label>
            <input type="text" id="hb_amenity_icon" name="hb_amenity_icon" value="">
            <p class="description"><?php esc_html_e('Enter a dashicon class, font-awesome class, or emoji.', 'hotel-booking'); ?></p>
        </div>
        <div class="form-field">
            <label for="hb_amenity_pricing_type"><?php esc_html_e('Pricing Type', 'hotel-booking'); ?></label>
            <select id="hb_amenity_pricing_type" name="hb_amenity_pricing_type">
                <?php foreach (hb_get_amenity_pricing_types() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field hb-amenity-price-field">
            <label for="hb_amenity_price"><?php esc_html_e('Price', 'hotel-booking'); ?></label>
            <input type="number" id="hb_amenity_price" name="hb_amenity_price" value="0" min="0" step="0.01">
        </div>
        <div class="form-field hb-amenity-quantity-field">
            <label for="hb_amenity_min_quantity"><?php esc_html_e('Minimum Quantity', 'hotel-booking'); ?></label>
            <input type="number" id="hb_amenity_min_quantity" name="hb_amenity_min_quantity" value="1" min="1" step="1">
            <p class="description"><?php esc_html_e('Fewest hours/drops a guest may choose.', 'hotel-booking'); ?></p>
        </div>
        <div class="form-field hb-amenity-quantity-field">
            <label for="hb_amenity_max_quantity"><?php esc_html_e('Maximum Quantity', 'hotel-booking'); ?></label>
            <input type="number" id="hb_amenity_max_quantity" name="hb_amenity_max_quantity" value="24" min="1" step="1">
        </div>
        <div class="form-field">
            <label for="hb_amenity_type"><?php esc_html_e('Amenity Type', 'hotel-booking'); ?></label>
            <select id="hb_amenity_type" name="hb_amenity_type">
                <?php foreach (hb_get_amenity_types() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 'optional'); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Included amenities are added to every booking automatically. Optional amenities are offered to the guest.', 'hotel-booking'); ?></p>
        </div>
        <div class="form-field hb-amenity-selectable-field">
            <label>
                <input type="checkbox" name="hb_amenity_customer_selectable" value="1" checked>
                <?php esc_html_e('Guest can select this amenity', 'hotel-booking'); ?>
            </label>
            <p class="description"><?php esc_html_e('Uncheck to hide an optional amenity from the booking form without deleting it.', 'hotel-booking'); ?></p>
        </div>
        <div class="form-field">
            <label for="hb_amenity_status"><?php esc_html_e('Status', 'hotel-booking'); ?></label>
            <select id="hb_amenity_status" name="hb_amenity_status">
                <option value="active"><?php esc_html_e('Active', 'hotel-booking'); ?></option>
                <option value="inactive"><?php esc_html_e('Inactive', 'hotel-booking'); ?></option>
            </select>
        </div>
        <?php
    }

    public function render_edit_fields(\WP_Term $term): void
    {
        $description  = get_term_meta($term->term_id, '_hb_description', true) ?: '';
        $icon         = get_term_meta($term->term_id, '_hb_icon', true) ?: '';
        $pricing_type = get_term_meta($term->term_id, '_hb_pricing_type', true) ?: 'free';
        $price        = get_term_meta($term->term_id, '_hb_price', true);
        $price        = $price !== '' && $price !== false ? (float) $price : 0;
        $status       = get_term_meta($term->term_id, '_hb_status', true) ?: 'active';
        $config       = hb_get_amenity_config($term->term_id);
        ?>
        <tr class="form-field">
            <th><label for="hb_amenity_description"><?php esc_html_e('Amenity Description', 'hotel-booking'); ?></label></th>
            <td>
                <textarea id="hb_amenity_description" name="hb_amenity_description" rows="3"><?php echo esc_textarea($description); ?></textarea>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="hb_amenity_icon"><?php esc_html_e('Icon', 'hotel-booking'); ?></label></th>
            <td>
                <input type="text" id="hb_amenity_icon" name="hb_amenity_icon"
                       value="<?php echo esc_attr($icon); ?>">
                <p class="description"><?php esc_html_e('Enter a dashicon class, font-awesome class, or emoji.', 'hotel-booking'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="hb_amenity_pricing_type"><?php esc_html_e('Pricing Type', 'hotel-booking'); ?></label></th>
            <td>
                <select id="hb_amenity_pricing_type" name="hb_amenity_pricing_type">
                    <?php foreach (hb_get_amenity_pricing_types() as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($pricing_type, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="form-field hb-amenity-price-field">
            <th><label for="hb_amenity_price"><?php esc_html_e('Price', 'hotel-booking'); ?></label></th>
            <td>
                <input type="number" id="hb_amenity_price" name="hb_amenity_price"
                       value="<?php echo esc_attr($price); ?>" min="0" step="0.01">
            </td>
        </tr>
        <tr class="form-field hb-amenity-quantity-field">
            <th><label for="hb_amenity_min_quantity"><?php esc_html_e('Minimum Quantity', 'hotel-booking'); ?></label></th>
            <td>
                <input type="number" id="hb_amenity_min_quantity" name="hb_amenity_min_quantity"
                       value="<?php echo esc_attr($config['min_quantity']); ?>" min="1" step="1">
                <p class="description"><?php esc_html_e('Fewest hours/drops a guest may choose.', 'hotel-booking'); ?></p>
            </td>
        </tr>
        <tr class="form-field hb-amenity-quantity-field">
            <th><label for="hb_amenity_max_quantity"><?php esc_html_e('Maximum Quantity', 'hotel-booking'); ?></label></th>
            <td>
                <input type="number" id="hb_amenity_max_quantity" name="hb_amenity_max_quantity"
                       value="<?php echo esc_attr($config['max_quantity']); ?>" min="1" step="1">
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="hb_amenity_type"><?php esc_html_e('Amenity Type', 'hotel-booking'); ?></label></th>
            <td>
                <select id="hb_amenity_type" name="hb_amenity_type">
                    <?php foreach (hb_get_amenity_types() as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($config['amenity_type'], $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Included amenities are added to every booking automatically. Optional amenities are offered to the guest.', 'hotel-booking'); ?></p>
            </td>
        </tr>
        <tr class="form-field hb-amenity-selectable-field">
            <th><?php esc_html_e('Guest Selectable', 'hotel-booking'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="hb_amenity_customer_selectable" value="1"
                        <?php checked($config['customer_selectable']); ?>>
                    <?php esc_html_e('Guest can select this amenity', 'hotel-booking'); ?>
                </label>
                <p class="description"><?php esc_html_e('Uncheck to hide an optional amenity from the booking form without deleting it.', 'hotel-booking'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="hb_amenity_status"><?php esc_html_e('Status', 'hotel-booking'); ?></label></th>
            <td>
                <select id="hb_amenity_status" name="hb_amenity_status">
                    <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'hotel-booking'); ?></option>
                    <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive', 'hotel-booking'); ?></option>
                </select>
            </td>
        </tr>
        <?php
    }

    public function save_fields(int $term_id): void
    {
        if (!current_user_can('manage_categories')) {
            return;
        }

        if (isset($_POST['hb_amenity_description'])) {
            update_term_meta($term_id, '_hb_description', sanitize_textarea_field(wp_unslash($_POST['hb_amenity_description'])));
        }

        if (isset($_POST['hb_amenity_icon'])) {
            update_term_meta($term_id, '_hb_icon', sanitize_text_field(wp_unslash($_POST['hb_amenity_icon'])));
        }

        if (isset($_POST['hb_amenity_pricing_type'])) {
            $pricing_type = sanitize_text_field(wp_unslash($_POST['hb_amenity_pricing_type']));
            if (array_key_exists($pricing_type, hb_get_amenity_pricing_types())) {
                update_term_meta($term_id, '_hb_pricing_type', $pricing_type);
            }
        }

        if (isset($_POST['hb_amenity_price'])) {
            $price = max(0, (float) $_POST['hb_amenity_price']);
            update_term_meta($term_id, '_hb_price', $price);
        }

        if (isset($_POST['hb_amenity_status'])) {
            $status = sanitize_text_field(wp_unslash($_POST['hb_amenity_status']));
            if (in_array($status, ['active', 'inactive'], true)) {
                update_term_meta($term_id, '_hb_status', $status);
            }
        }

        if (isset($_POST['hb_amenity_type'])) {
            $type = sanitize_text_field(wp_unslash($_POST['hb_amenity_type']));
            if (array_key_exists($type, hb_get_amenity_types())) {
                update_term_meta($term_id, '_hb_amenity_type', $type);
            }
        }

        // Checkboxes only post when ticked, so this must be written on every save
        // or unticking would silently keep the previous value.
        if (isset($_POST['hb_amenity_pricing_type']) || isset($_POST['hb_amenity_type'])) {
            update_term_meta($term_id, '_hb_customer_selectable', empty($_POST['hb_amenity_customer_selectable']) ? 0 : 1);
        }

        $min = isset($_POST['hb_amenity_min_quantity']) ? max(1, (int) $_POST['hb_amenity_min_quantity']) : 1;
        $max = isset($_POST['hb_amenity_max_quantity']) ? max(1, (int) $_POST['hb_amenity_max_quantity']) : 1;

        if ($max < $min) {
            $max = $min;
        }

        if (isset($_POST['hb_amenity_min_quantity'])) {
            update_term_meta($term_id, '_hb_min_quantity', $min);
        }
        if (isset($_POST['hb_amenity_max_quantity'])) {
            update_term_meta($term_id, '_hb_max_quantity', $max);
        }
    }

    public function add_columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $value) {
            $new[$key] = $value;
            if ($key === 'name') {
                $new['hb_pricing'] = __('Pricing', 'hotel-booking');
                $new['hb_status']  = __('Status', 'hotel-booking');
            }
        }
        return $new;
    }

    public function render_column(string $content, string $column_name, int $term_id): string
    {
        if ($column_name === 'hb_pricing') {
            $type  = get_term_meta($term_id, '_hb_pricing_type', true) ?: 'free';
            $price = get_term_meta($term_id, '_hb_price', true);

            if ($type === 'free') {
                return esc_html__('Free', 'hotel-booking');
            }

            $label = hb_get_amenity_pricing_types()[$type] ?? $type;
            return esc_html(hb_format_price((float) $price) . ' — ' . $label);
        }

        if ($column_name === 'hb_status') {
            $status = get_term_meta($term_id, '_hb_status', true) ?: 'active';
            $class = $status === 'active' ? 'hb-status-active' : 'hb-status-inactive';
            return '<span class="' . esc_attr($class) . '">' . esc_html(ucfirst($status)) . '</span>';
        }

        return $content;
    }
}
