<?php

namespace HotelBooking\Admin;

use HotelBooking\PostTypes\RoomPostType;
use HotelBooking\Services\BookingFieldsService;

defined('ABSPATH') || exit;

final class RoomMetabox
{
    private const NONCE_ACTION = 'hb_room_meta_nonce';
    private const NONCE_FIELD  = '_hb_room_nonce';

    public function init(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . RoomPostType::SLUG, [$this, 'save'], 10, 2);
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            'hb_room_details',
            __('Room Details', 'hotel-booking'),
            [$this, 'render_details'],
            RoomPostType::SLUG,
            'normal',
            'high'
        );

        add_meta_box(
            'hb_room_booking_fields',
            __('Room Booking Fields', 'hotel-booking'),
            [$this, 'render_booking_fields'],
            RoomPostType::SLUG,
            'normal',
            'default'
        );

        add_meta_box(
            'hb_room_gallery',
            __('Room Gallery', 'hotel-booking'),
            [$this, 'render_gallery'],
            RoomPostType::SLUG,
            'normal',
            'default'
        );
    }

    public function render_details(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $inventory       = (int) get_post_meta($post->ID, '_hb_inventory', true) ?: 1;
        $max_adults      = (int) get_post_meta($post->ID, '_hb_max_adults', true) ?: 1;
        $max_children    = (int) get_post_meta($post->ID, '_hb_max_children', true) ?: 0;
        $max_total       = (int) get_post_meta($post->ID, '_hb_max_total_guests', true) ?: 1;
        $base_price      = get_post_meta($post->ID, '_hb_base_price', true);
        $base_price      = $base_price !== '' ? (float) $base_price : '';
        $price_unit      = get_post_meta($post->ID, '_hb_price_unit', true) ?: 'per_night';
        $room_status     = get_post_meta($post->ID, '_hb_room_status', true) ?: 'active';
        ?>
        <table class="form-table hb-room-details">
            <tr>
                <th><label for="hb_inventory"><?php esc_html_e('Inventory (Number of Rooms)', 'hotel-booking'); ?></label></th>
                <td>
                    <input type="number" id="hb_inventory" name="hb_inventory"
                           value="<?php echo esc_attr($inventory); ?>" min="1" step="1" class="small-text" required>
                    <p class="description"><?php esc_html_e('Total number of this room type available for booking.', 'hotel-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="hb_max_adults"><?php esc_html_e('Maximum Adults', 'hotel-booking'); ?></label></th>
                <td>
                    <input type="number" id="hb_max_adults" name="hb_max_adults"
                           value="<?php echo esc_attr($max_adults); ?>" min="1" step="1" class="small-text" required>
                </td>
            </tr>
            <tr>
                <th><label for="hb_max_children"><?php esc_html_e('Maximum Children', 'hotel-booking'); ?></label></th>
                <td>
                    <input type="number" id="hb_max_children" name="hb_max_children"
                           value="<?php echo esc_attr($max_children); ?>" min="0" step="1" class="small-text" required>
                </td>
            </tr>
            <tr>
                <th><label for="hb_max_total_guests"><?php esc_html_e('Maximum Total Guests', 'hotel-booking'); ?></label></th>
                <td>
                    <input type="number" id="hb_max_total_guests" name="hb_max_total_guests"
                           value="<?php echo esc_attr($max_total); ?>" min="1" step="1" class="small-text" required>
                    <p class="description"><?php esc_html_e('Maximum combined adults + children per room.', 'hotel-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="hb_base_price"><?php esc_html_e('Base Price', 'hotel-booking'); ?></label></th>
                <td>
                    <input type="number" id="hb_base_price" name="hb_base_price"
                           value="<?php echo esc_attr($base_price); ?>" min="0" step="0.01" class="regular-text" required>
                    <select id="hb_price_unit" name="hb_price_unit">
                        <option value="per_night" <?php selected($price_unit, 'per_night'); ?>>
                            <?php esc_html_e('Per Night', 'hotel-booking'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="hb_room_status"><?php esc_html_e('Status', 'hotel-booking'); ?></label></th>
                <td>
                    <select id="hb_room_status" name="hb_room_status">
                        <option value="active" <?php selected($room_status, 'active'); ?>>
                            <?php esc_html_e('Active', 'hotel-booking'); ?>
                        </option>
                        <option value="inactive" <?php selected($room_status, 'inactive'); ?>>
                            <?php esc_html_e('Inactive', 'hotel-booking'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Per-room overrides for the first-step fields. Each defers to the global
     * setting unless explicitly forced on or off for this room.
     */
    public function render_booking_fields(\WP_Post $post): void
    {
        $service = new BookingFieldsService();

        $labels = [
            'checkin_time'  => __('Check-in Time', 'hotel-booking'),
            'checkout_time' => __('Check-out Time', 'hotel-booking'),
            'adults'        => __('Adults', 'hotel-booking'),
            'children'      => __('Children', 'hotel-booking'),
        ];

        $choices = [
            BookingFieldsService::OVERRIDE_GLOBAL   => __('Use Global Setting', 'hotel-booking'),
            BookingFieldsService::OVERRIDE_ENABLED  => __('Enable', 'hotel-booking'),
            BookingFieldsService::OVERRIDE_DISABLED => __('Disable', 'hotel-booking'),
        ];

        $effective = $service->get_visibility($post->ID);
        ?>
        <p class="description">
            <?php esc_html_e('Control which fields guests are asked for when booking this room. "Use Global Setting" follows Hotel Booking → Settings → Booking.', 'hotel-booking'); ?>
        </p>
        <table class="form-table hb-room-fields">
            <?php foreach ($labels as $field => $label) :
                $current = $service->get_room_override($post->ID, $field);
                $global_on = $service->is_globally_enabled($field);
                ?>
                <tr>
                    <th><label for="hb_field_<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th>
                    <td>
                        <select id="hb_field_<?php echo esc_attr($field); ?>"
                                name="hb_field_<?php echo esc_attr($field); ?>">
                            <?php foreach ($choices as $value => $choice_label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
                                    <?php
                                    echo esc_html(
                                        $value === BookingFieldsService::OVERRIDE_GLOBAL
                                            ? sprintf(
                                                /* translators: %s: Enabled or Disabled */
                                                __('Use Global Setting (%s)', 'hotel-booking'),
                                                $global_on ? __('Enabled', 'hotel-booking') : __('Disabled', 'hotel-booking')
                                            )
                                            : $choice_label
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hb-field-effective">
                            <?php
                            echo $effective[$field]
                                ? '<span class="hb-status-active">' . esc_html__('Shown', 'hotel-booking') . '</span>'
                                : '<span class="hb-status-inactive">' . esc_html__('Hidden', 'hotel-booking') . '</span>';
                            ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    public function render_gallery(\WP_Post $post): void
    {
        $gallery_ids = get_post_meta($post->ID, '_hb_gallery', true);
        $gallery_ids = is_array($gallery_ids) ? $gallery_ids : [];
        ?>
        <div class="hb-gallery-container">
            <div class="hb-gallery-images" id="hb-gallery-images">
                <?php foreach ($gallery_ids as $image_id) :
                    $image_id = (int) $image_id;
                    $thumb = wp_get_attachment_image_url($image_id, 'thumbnail');
                    if (!$thumb) continue;
                    ?>
                    <div class="hb-gallery-item" data-id="<?php echo esc_attr($image_id); ?>">
                        <img src="<?php echo esc_url($thumb); ?>" alt="">
                        <button type="button" class="hb-gallery-remove" aria-label="<?php esc_attr_e('Remove image', 'hotel-booking'); ?>">&times;</button>
                        <input type="hidden" name="hb_gallery[]" value="<?php echo esc_attr($image_id); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button hb-gallery-add" id="hb-gallery-add">
                <?php esc_html_e('Add Gallery Images', 'hotel-booking'); ?>
            </button>
            <p class="description"><?php esc_html_e('Drag images to reorder. Click the X to remove.', 'hotel-booking'); ?></p>
        </div>
        <?php
    }

    public function save(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'hb_inventory'        => ['meta' => '_hb_inventory',        'type' => 'int',    'min' => 1],
            'hb_max_adults'       => ['meta' => '_hb_max_adults',       'type' => 'int',    'min' => 1],
            'hb_max_children'     => ['meta' => '_hb_max_children',     'type' => 'int',    'min' => 0],
            'hb_max_total_guests' => ['meta' => '_hb_max_total_guests', 'type' => 'int',    'min' => 1],
            'hb_base_price'       => ['meta' => '_hb_base_price',       'type' => 'float',  'min' => 0],
        ];

        foreach ($fields as $field_name => $config) {
            if (!isset($_POST[$field_name])) {
                continue;
            }

            $value = $config['type'] === 'float'
                ? (float) $_POST[$field_name]
                : (int) $_POST[$field_name];

            if ($value < $config['min']) {
                $value = $config['min'];
            }

            update_post_meta($post_id, $config['meta'], $value);
        }

        if (isset($_POST['hb_price_unit'])) {
            $price_unit = sanitize_text_field(wp_unslash($_POST['hb_price_unit']));
            if ($price_unit === 'per_night') {
                update_post_meta($post_id, '_hb_price_unit', $price_unit);
            }
        }

        if (isset($_POST['hb_room_status'])) {
            $status = sanitize_text_field(wp_unslash($_POST['hb_room_status']));
            if (in_array($status, ['active', 'inactive'], true)) {
                update_post_meta($post_id, '_hb_room_status', $status);
            }
        }

        $overrides = [
            BookingFieldsService::OVERRIDE_GLOBAL,
            BookingFieldsService::OVERRIDE_ENABLED,
            BookingFieldsService::OVERRIDE_DISABLED,
        ];

        foreach (BookingFieldsService::FIELDS as $field) {
            $key = 'hb_field_' . $field;

            if (!isset($_POST[$key])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash($_POST[$key]));

            if (in_array($value, $overrides, true)) {
                update_post_meta($post_id, BookingFieldsService::meta_key($field), $value);
            }
        }

        if (isset($_POST['hb_gallery'])) {
            $gallery = array_map('absint', (array) $_POST['hb_gallery']);
            $gallery = array_filter($gallery);
            update_post_meta($post_id, '_hb_gallery', $gallery);
        } else {
            update_post_meta($post_id, '_hb_gallery', []);
        }
    }
}
