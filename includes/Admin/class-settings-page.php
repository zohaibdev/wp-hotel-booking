<?php

namespace HotelBooking\Admin;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;

final class SettingsPage
{
    private const OPTION_GROUPS = [
        'hb_general',
        'hb_booking',
        'hb_payments',
        'hb_emails',
        'hb_form_fields',
    ];

    private const FIELD_TYPES = [
        'text', 'email', 'phone', 'number', 'select',
        'radio', 'checkbox', 'textarea', 'date',
    ];

    private const TABS = [
        'general'     => 'General',
        'booking'     => 'Booking',
        'payments'    => 'Payments',
        'emails'      => 'Emails',
        'form_fields' => 'Form Fields',
    ];

    public function init(): void
    {
        add_action('admin_init', [$this, 'handle_save']);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'hotel-booking'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
        if (!array_key_exists($active_tab, self::TABS)) {
            $active_tab = 'general';
        }

        $tabs = [
            'general'     => __('General', 'hotel-booking'),
            'booking'     => __('Booking', 'hotel-booking'),
            'payments'    => __('Payments', 'hotel-booking'),
            'emails'      => __('Emails', 'hotel-booking'),
            'form_fields' => __('Form Fields', 'hotel-booking'),
        ];
        ?>
        <div class="wrap hb-settings-wrap">
            <h1><?php esc_html_e('Hotel Booking Settings', 'hotel-booking'); ?></h1>

            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'hotel-booking'); ?></p>
                </div>
            <?php endif; ?>

            <?php
            $test_result = isset($_GET['hb-test']) ? sanitize_text_field(wp_unslash($_GET['hb-test'])) : '';

            if ($test_result === 'sent') : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong><?php esc_html_e('Test email accepted for delivery.', 'hotel-booking'); ?></strong>
                        <?php esc_html_e('WordPress handed the message to your server successfully. If it does not arrive within a few minutes, check your spam folder — delivery beyond this point is handled by your host, not the plugin.', 'hotel-booking'); ?>
                    </p>
                </div>
            <?php elseif ($test_result === 'failed') :
                $error = get_transient('hb_test_email_error');
                delete_transient('hb_test_email_error');
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong><?php esc_html_e('Test email could not be sent.', 'hotel-booking'); ?></strong></p>
                    <?php if (!empty($error)) : ?>
                        <p><code><?php echo esc_html($error); ?></code></p>
                    <?php endif; ?>
                    <p><?php esc_html_e('wp_mail() rejected the message. This is almost always a server mail configuration problem — installing an SMTP plugin usually resolves it.', 'hotel-booking'); ?></p>
                </div>
            <?php elseif ($test_result === 'invalid') : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e('Please enter a valid email address for the test.', 'hotel-booking'); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper hb-settings-tabs">
                <?php foreach ($tabs as $slug => $label) : ?>
                    <a href="#hb-tab-<?php echo esc_attr($slug); ?>"
                       class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>"
                       data-tab="<?php echo esc_attr($slug); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="">
                <?php wp_nonce_field('hb_save_settings', '_hb_settings_nonce'); ?>
                <input type="hidden" name="hb_active_tab" id="hb_active_tab" value="<?php echo esc_attr($active_tab); ?>">

                <div class="hb-settings-panel" id="hb-tab-general" <?php echo $active_tab !== 'general' ? 'style="display:none;"' : ''; ?>>
                    <?php $this->render_general_tab(); ?>
                </div>

                <div class="hb-settings-panel" id="hb-tab-booking" <?php echo $active_tab !== 'booking' ? 'style="display:none;"' : ''; ?>>
                    <?php $this->render_booking_tab(); ?>
                </div>

                <div class="hb-settings-panel" id="hb-tab-payments" <?php echo $active_tab !== 'payments' ? 'style="display:none;"' : ''; ?>>
                    <?php $this->render_payments_tab(); ?>
                </div>

                <div class="hb-settings-panel" id="hb-tab-emails" <?php echo $active_tab !== 'emails' ? 'style="display:none;"' : ''; ?>>
                    <?php $this->render_emails_tab(); ?>
                </div>

                <div class="hb-settings-panel" id="hb-tab-form_fields" <?php echo $active_tab !== 'form_fields' ? 'style="display:none;"' : ''; ?>>
                    <?php $this->render_form_fields_tab(); ?>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function render_general_tab(): void
    {
        $general = get_option('hb_general', []);
        ?>
        <div class="hb-settings-section">
            <h2><?php esc_html_e('General Settings', 'hotel-booking'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="hotel_name"><?php esc_html_e('Hotel Name', 'hotel-booking'); ?></label></th>
                    <td><input type="text" id="hotel_name" name="hb_general[hotel_name]"
                               value="<?php echo esc_attr($general['hotel_name'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="hotel_logo"><?php esc_html_e('Hotel Logo URL', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="url" id="hotel_logo" name="hb_general[hotel_logo]"
                               value="<?php echo esc_url($general['hotel_logo'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('URL to your hotel logo image.', 'hotel-booking'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="currency"><?php esc_html_e('Currency', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="text" id="currency" name="hb_general[currency]"
                               value="<?php echo esc_attr($general['currency'] ?? 'USD'); ?>" class="small-text" maxlength="3">
                    </td>
                </tr>
                <tr>
                    <th><label for="currency_symbol"><?php esc_html_e('Currency Symbol', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="text" id="currency_symbol" name="hb_general[currency_symbol]"
                               value="<?php echo esc_attr($general['currency_symbol'] ?? '$'); ?>" class="small-text" maxlength="5">
                    </td>
                </tr>
                <tr>
                    <th><label for="currency_position"><?php esc_html_e('Currency Position', 'hotel-booking'); ?></label></th>
                    <td>
                        <select id="currency_position" name="hb_general[currency_position]">
                            <option value="before" <?php selected($general['currency_position'] ?? 'before', 'before'); ?>>
                                <?php esc_html_e('Before amount ($100)', 'hotel-booking'); ?>
                            </option>
                            <option value="after" <?php selected($general['currency_position'] ?? 'before', 'after'); ?>>
                                <?php esc_html_e('After amount (100$)', 'hotel-booking'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="delete_data_on_uninstall"><?php esc_html_e('Delete Data on Uninstall', 'hotel-booking'); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="delete_data_on_uninstall" name="hb_general[delete_data_on_uninstall]"
                                   value="1" <?php checked(!empty($general['delete_data_on_uninstall'])); ?>>
                            <?php esc_html_e('Remove all plugin data when uninstalling (bookings, customers, settings, etc.)', 'hotel-booking'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function render_booking_tab(): void
    {
        $booking = get_option('hb_booking', []);
        ?>
        <div class="hb-settings-section">
            <h2><?php esc_html_e('Booking Settings', 'hotel-booking'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="default_check_in_time"><?php esc_html_e('Default Check-in Time', 'hotel-booking'); ?></label></th>
                    <td><input type="time" id="default_check_in_time" name="hb_booking[default_check_in_time]"
                               value="<?php echo esc_attr($booking['default_check_in_time'] ?? '15:00'); ?>"></td>
                </tr>
                <tr>
                    <th><label for="default_check_out_time"><?php esc_html_e('Default Check-out Time', 'hotel-booking'); ?></label></th>
                    <td><input type="time" id="default_check_out_time" name="hb_booking[default_check_out_time]"
                               value="<?php echo esc_attr($booking['default_check_out_time'] ?? '11:00'); ?>"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Custom Check-in Time', 'hotel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hb_booking[allow_custom_check_in_time]" value="1"
                                <?php checked(!empty($booking['allow_custom_check_in_time'])); ?>>
                            <?php esc_html_e('Allow guests to select a custom check-in time', 'hotel-booking'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Custom Check-out Time', 'hotel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hb_booking[allow_custom_check_out_time]" value="1"
                                <?php checked(!empty($booking['allow_custom_check_out_time'])); ?>>
                            <?php esc_html_e('Allow guests to select a custom check-out time', 'hotel-booking'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="minimum_stay"><?php esc_html_e('Minimum Stay (nights)', 'hotel-booking'); ?></label></th>
                    <td><input type="number" id="minimum_stay" name="hb_booking[minimum_stay]"
                               value="<?php echo esc_attr($booking['minimum_stay'] ?? 1); ?>" min="1" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="temporary_hold_minutes"><?php esc_html_e('Reservation Hold (minutes)', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="number" id="temporary_hold_minutes" name="hb_booking[temporary_hold_minutes]"
                               value="<?php echo esc_attr($booking['temporary_hold_minutes'] ?? 10); ?>" min="1" max="60" class="small-text">
                        <p class="description"><?php esc_html_e('How long to hold inventory while a guest completes payment.', 'hotel-booking'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="hb-settings-section">
            <h2><?php esc_html_e('Booking Field Visibility', 'hotel-booking'); ?></h2>
            <p class="description">
                <?php esc_html_e('Choose which fields guests are asked for on the first step. Individual rooms can override any of these.', 'hotel-booking'); ?>
            </p>
            <table class="form-table">
                <?php
                $visibility_fields = [
                    'checkin_time'  => __('Check-in Time', 'hotel-booking'),
                    'checkout_time' => __('Check-out Time', 'hotel-booking'),
                    'adults'        => __('Adults', 'hotel-booking'),
                    'children'      => __('Children', 'hotel-booking'),
                ];

                foreach ($visibility_fields as $field => $label) :
                    $key = 'enable_' . $field;
                    // Absent means the option predates this feature: keep showing.
                    $enabled = !array_key_exists($key, $booking) || !empty($booking[$key]);
                    ?>
                    <tr>
                        <th><?php echo esc_html($label); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="hb_booking[<?php echo esc_attr($key); ?>]" value="1"
                                    <?php checked($enabled); ?>>
                                <?php
                                printf(
                                    /* translators: %s: field label */
                                    esc_html__('Ask guests for %s', 'hotel-booking'),
                                    esc_html($label)
                                );
                                ?>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="description">
                <?php esc_html_e('When a field is switched off the booking still uses a sensible value: the room\'s configured occupancy for adults, zero children, and the default check-in/check-out times above.', 'hotel-booking'); ?>
            </p>
        </div>

        <div class="hb-settings-section">
            <h2><?php esc_html_e('Terms &amp; Conditions', 'hotel-booking'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Terms Step', 'hotel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hb_booking[enable_terms_step]" value="1"
                                <?php checked(!empty($booking['enable_terms_step'])); ?>>
                            <?php esc_html_e('Show a Terms & Conditions step before payment', 'hotel-booking'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('When disabled, the guest goes straight from their details to payment.', 'hotel-booking'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="terms_checkbox_text"><?php esc_html_e('Checkbox Text', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="text" id="terms_checkbox_text" name="hb_booking[terms_checkbox_text]"
                               value="<?php echo esc_attr($booking['terms_checkbox_text'] ?? __('I agree to the Terms & Conditions.', 'hotel-booking')); ?>"
                               class="large-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="terms_content"><?php esc_html_e('Terms &amp; Conditions Content', 'hotel-booking'); ?></label></th>
                    <td>
                        <?php
                        wp_editor(
                            (string) ($booking['terms_content'] ?? ''),
                            'terms_content',
                            [
                                'textarea_name' => 'hb_booking[terms_content]',
                                'textarea_rows' => 12,
                                'media_buttons' => false,
                                'teeny'         => true,
                            ]
                        );
                        ?>
                        <p class="description"><?php esc_html_e('Shown to the guest on the Terms step. Leave empty to show only the agreement checkbox.', 'hotel-booking'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function render_payments_tab(): void
    {
        $payments = get_option('hb_payments', []);
        ?>
        <div class="hb-settings-section">
            <h2><?php esc_html_e('Stripe Settings', 'hotel-booking'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Enable Stripe', 'hotel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hb_payments[stripe_enabled]" value="1"
                                <?php checked(!empty($payments['stripe_enabled'])); ?>>
                            <?php esc_html_e('Enable Stripe payment gateway', 'hotel-booking'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="stripe_mode"><?php esc_html_e('Stripe Mode', 'hotel-booking'); ?></label></th>
                    <td>
                        <select id="stripe_mode" name="hb_payments[stripe_mode]">
                            <option value="test" <?php selected($payments['stripe_mode'] ?? 'test', 'test'); ?>>
                                <?php esc_html_e('Test Mode', 'hotel-booking'); ?>
                            </option>
                            <option value="live" <?php selected($payments['stripe_mode'] ?? 'test', 'live'); ?>>
                                <?php esc_html_e('Live Mode', 'hotel-booking'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="stripe_test_publishable_key"><?php esc_html_e('Test Publishable Key', 'hotel-booking'); ?></label></th>
                    <td><input type="text" id="stripe_test_publishable_key" name="hb_payments[stripe_test_publishable_key]"
                               value="<?php echo esc_attr($payments['stripe_test_publishable_key'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="stripe_test_secret_key"><?php esc_html_e('Test Secret Key', 'hotel-booking'); ?></label></th>
                    <td><input type="password" id="stripe_test_secret_key" name="hb_payments[stripe_test_secret_key]"
                               value="<?php echo esc_attr($payments['stripe_test_secret_key'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="stripe_live_publishable_key"><?php esc_html_e('Live Publishable Key', 'hotel-booking'); ?></label></th>
                    <td><input type="text" id="stripe_live_publishable_key" name="hb_payments[stripe_live_publishable_key]"
                               value="<?php echo esc_attr($payments['stripe_live_publishable_key'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="stripe_live_secret_key"><?php esc_html_e('Live Secret Key', 'hotel-booking'); ?></label></th>
                    <td><input type="password" id="stripe_live_secret_key" name="hb_payments[stripe_live_secret_key]"
                               value="<?php echo esc_attr($payments['stripe_live_secret_key'] ?? ''); ?>" class="regular-text"></td>
                </tr>
            </table>
        </div>

        <div class="hb-settings-section">
            <h2><?php esc_html_e('Offline Payment', 'hotel-booking'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Enable Offline Payment', 'hotel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hb_payments[offline_payment_enabled]" value="1"
                                <?php checked(!empty($payments['offline_payment_enabled'])); ?>>
                            <?php esc_html_e('Allow guests to pay at the hotel', 'hotel-booking'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="offline_payment_title"><?php esc_html_e('Title', 'hotel-booking'); ?></label></th>
                    <td><input type="text" id="offline_payment_title" name="hb_payments[offline_payment_title]"
                               value="<?php echo esc_attr($payments['offline_payment_title'] ?? __('Pay at Hotel', 'hotel-booking')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="offline_payment_description"><?php esc_html_e('Description', 'hotel-booking'); ?></label></th>
                    <td><textarea id="offline_payment_description" name="hb_payments[offline_payment_description]"
                                  class="large-text" rows="3"><?php echo esc_textarea($payments['offline_payment_description'] ?? __('Pay at the hotel upon arrival.', 'hotel-booking')); ?></textarea></td>
                </tr>
            </table>
        </div>

        <div class="hb-settings-section">
            <h2><?php esc_html_e('Partial Payment', 'hotel-booking'); ?></h2>
            <p class="description">
                <?php esc_html_e('Let guests pay a deposit now and settle the balance closer to arrival. Full payment is always offered.', 'hotel-booking'); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Enable Partial Payment', 'hotel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hb_payments[enable_partial_payment]" value="1"
                                <?php checked(!empty($payments['enable_partial_payment'])); ?>>
                            <?php esc_html_e('Offer a deposit option at checkout', 'hotel-booking'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="initial_payment_percentage"><?php esc_html_e('Initial Payment Percentage', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="number" id="initial_payment_percentage" name="hb_payments[initial_payment_percentage]"
                               value="<?php echo esc_attr($payments['initial_payment_percentage'] ?? 50); ?>"
                               min="1" max="99" step="1" class="small-text"> %
                        <p class="description"><?php esc_html_e('Percentage of the booking total charged at the time of booking (1-99).', 'hotel-booking'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="balance_due_days_before_check_in"><?php esc_html_e('Balance Due Days Before Check-in', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="number" id="balance_due_days_before_check_in" name="hb_payments[balance_due_days_before_check_in]"
                               value="<?php echo esc_attr($payments['balance_due_days_before_check_in'] ?? 30); ?>"
                               min="0" step="1" class="small-text">
                        <p class="description"><?php esc_html_e('The balance due date is calculated from each booking\'s check-in date and stored with the booking, so changing this later never moves an existing due date.', 'hotel-booking'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function render_emails_tab(): void
    {
        $emails = get_option('hb_emails', []);
        ?>
        <div class="hb-settings-section">
            <h2><?php esc_html_e('Email Settings', 'hotel-booking'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="admin_notification_email"><?php esc_html_e('Admin Notification Email', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="email" id="admin_notification_email" name="hb_emails[admin_notification_email]"
                               value="<?php echo esc_attr($emails['admin_notification_email'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Email address to receive new booking notifications.', 'hotel-booking'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="from_name"><?php esc_html_e('From Name', 'hotel-booking'); ?></label></th>
                    <td><input type="text" id="from_name" name="hb_emails[from_name]"
                               value="<?php echo esc_attr($emails['from_name'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="from_email"><?php esc_html_e('From Email', 'hotel-booking'); ?></label></th>
                    <td><input type="email" id="from_email" name="hb_emails[from_email]"
                               value="<?php echo esc_attr($emails['from_email'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="reply_to_email"><?php esc_html_e('Reply-To Email', 'hotel-booking'); ?></label></th>
                    <td><input type="email" id="reply_to_email" name="hb_emails[reply_to_email]"
                               value="<?php echo esc_attr($emails['reply_to_email'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Email Notifications', 'hotel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hb_emails[customer_booking_enabled]" value="1"
                                <?php checked(!empty($emails['customer_booking_enabled'])); ?>>
                            <?php esc_html_e('Send booking confirmation to customer', 'hotel-booking'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="hb_emails[admin_booking_enabled]" value="1"
                                <?php checked(!empty($emails['admin_booking_enabled'])); ?>>
                            <?php esc_html_e('Send new booking notification to admin', 'hotel-booking'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="hb-settings-section">
            <h2><?php esc_html_e('Email Delivery Test', 'hotel-booking'); ?></h2>
            <p class="description">
                <?php esc_html_e('Booking emails are sent with wp_mail(), which relies on your host having a working mail transport. Send a test message to confirm delivery works before blaming the booking flow.', 'hotel-booking'); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="hb_test_email_to"><?php esc_html_e('Send test email to', 'hotel-booking'); ?></label></th>
                    <td>
                        <input type="email" id="hb_test_email_to" name="hb_test_email_to"
                               value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
                        <?php submit_button(
                            __('Send Test Email', 'hotel-booking'),
                            'secondary',
                            'hb_send_test_email',
                            false
                        ); ?>
                        <p class="description">
                            <?php esc_html_e('Uses the From Name, From Email and Reply-To configured above. Saves any pending changes first.', 'hotel-booking'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function render_form_fields_tab(): void
    {
        $form_fields = get_option('hb_form_fields', []);
        $fields = $form_fields['fields'] ?? [];
        ?>
        <div class="hb-settings-section">
            <h2><?php esc_html_e('Customer Booking Fields', 'hotel-booking'); ?></h2>
            <p class="description">
                <?php esc_html_e('Configure the fields shown on the customer details step. System fields cannot be removed but can be disabled. Drag to reorder.', 'hotel-booking'); ?>
            </p>

            <table class="widefat hb-form-fields-table" id="hb-form-fields-table">
                <thead>
                    <tr>
                        <th class="hb-col-sort"></th>
                        <th><?php esc_html_e('Label', 'hotel-booking'); ?></th>
                        <th><?php esc_html_e('Key', 'hotel-booking'); ?></th>
                        <th><?php esc_html_e('Type', 'hotel-booking'); ?></th>
                        <th><?php esc_html_e('Required', 'hotel-booking'); ?></th>
                        <th><?php esc_html_e('Enabled', 'hotel-booking'); ?></th>
                        <th><?php esc_html_e('Actions', 'hotel-booking'); ?></th>
                    </tr>
                </thead>
                <tbody id="hb-form-fields-body">
                    <?php foreach ($fields as $i => $field) : ?>
                        <?php $this->render_field_row($i, $field); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="hb-add-field">
                    <?php esc_html_e('+ Add Custom Field', 'hotel-booking'); ?>
                </button>
            </p>
        </div>

        <template id="hb-field-row-template">
            <?php $this->render_field_row('__INDEX__', [
                'key'         => '',
                'label'       => '',
                'type'        => 'text',
                'required'    => false,
                'enabled'     => true,
                'placeholder' => '',
                'options'     => '',
                'sort_order'  => 999,
                'system'      => false,
            ]); ?>
        </template>

        <script>
        (function() {
            var fieldIndex = <?php echo count($fields); ?>;
            var tbody = document.getElementById('hb-form-fields-body');

            document.getElementById('hb-add-field').addEventListener('click', function() {
                var template = document.getElementById('hb-field-row-template').innerHTML;
                template = template.replace(/__INDEX__/g, fieldIndex);
                var temp = document.createElement('tbody');
                temp.innerHTML = template;
                var row = temp.querySelector('tr');
                tbody.appendChild(row);
                fieldIndex++;
            });

            tbody.addEventListener('click', function(e) {
                if (e.target.classList.contains('hb-remove-field')) {
                    e.target.closest('tr').remove();
                }
            });
        })();
        </script>
        <?php
    }

    private function render_field_row(int|string $index, array $field): void
    {
        $is_system = !empty($field['system']);
        $prefix = "hb_form_fields[fields][{$index}]";
        ?>
        <tr class="hb-field-row" <?php echo $is_system ? 'data-system="1"' : ''; ?>>
            <td class="hb-col-sort"><span class="dashicons dashicons-menu" style="cursor:grab;"></span></td>
            <td>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[label]"
                       value="<?php echo esc_attr($field['label'] ?? ''); ?>" class="regular-text"
                       <?php echo $is_system ? 'readonly' : ''; ?>>
            </td>
            <td>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[key]"
                       value="<?php echo esc_attr($field['key'] ?? ''); ?>" class="regular-text"
                       <?php echo $is_system ? 'readonly' : ''; ?>
                       pattern="[a-z0-9_]+" title="<?php esc_attr_e('Lowercase letters, numbers, underscores only', 'hotel-booking'); ?>">
            </td>
            <td>
                <select name="<?php echo esc_attr($prefix); ?>[type]" <?php echo $is_system ? 'disabled' : ''; ?>>
                    <?php foreach (self::FIELD_TYPES as $type) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($field['type'] ?? 'text', $type); ?>>
                            <?php echo esc_html(ucfirst($type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($is_system) : ?>
                    <input type="hidden" name="<?php echo esc_attr($prefix); ?>[type]"
                           value="<?php echo esc_attr($field['type'] ?? 'text'); ?>">
                <?php endif; ?>
            </td>
            <td>
                <input type="checkbox" name="<?php echo esc_attr($prefix); ?>[required]" value="1"
                    <?php checked(!empty($field['required'])); ?>>
            </td>
            <td>
                <input type="checkbox" name="<?php echo esc_attr($prefix); ?>[enabled]" value="1"
                    <?php checked(!empty($field['enabled'])); ?>>
            </td>
            <td>
                <?php if (!$is_system) : ?>
                    <button type="button" class="button-link hb-remove-field" style="color:#b32d2e;">
                        <?php esc_html_e('Remove', 'hotel-booking'); ?>
                    </button>
                <?php else : ?>
                    <span class="description"><?php esc_html_e('System', 'hotel-booking'); ?></span>
                <?php endif; ?>
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[system]"
                       value="<?php echo $is_system ? '1' : '0'; ?>">
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[sort_order]"
                       value="<?php echo esc_attr($field['sort_order'] ?? $index); ?>">
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[placeholder]"
                       value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>">
            </td>
        </tr>
        <?php
    }

    public function handle_save(): void
    {
        if (!isset($_POST['_hb_settings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_hb_settings_nonce'])), 'hb_save_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = isset($_POST['hb_active_tab']) ? sanitize_text_field(wp_unslash($_POST['hb_active_tab'])) : 'general';
        if (!array_key_exists($tab, self::TABS)) {
            $tab = 'general';
        }

        // All tab panels are rendered and submitted together, so persist every group.
        $this->save_general();
        $this->save_booking();
        $this->save_payments();
        $this->save_emails();
        $this->save_form_fields();

        $args = [
            'page'             => 'hotel-booking-settings',
            'tab'              => $tab,
            'settings-updated' => 'true',
        ];

        // Sent after saving so the test uses the sender settings just entered.
        if (isset($_POST['hb_send_test_email'])) {
            $to = sanitize_email(wp_unslash($_POST['hb_test_email_to'] ?? ''));

            if ($to === '' || !is_email($to)) {
                $args['hb-test'] = 'invalid';
            } else {
                $result = (new \HotelBooking\Services\EmailService())->send_test_email($to);
                $args['hb-test'] = $result['success'] ? 'sent' : 'failed';
                $args['tab'] = 'emails';

                if (!$result['success'] && $result['error'] !== '') {
                    // Surfaced once on the next screen; the full detail is in the log.
                    set_transient('hb_test_email_error', $result['error'], 60);
                }
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function save_general(): void
    {
        $input = isset($_POST['hb_general']) && is_array($_POST['hb_general']) ? $_POST['hb_general'] : [];
        $existing = get_option('hb_general', []);

        $sanitized = [
            'hotel_name'              => sanitize_text_field(wp_unslash($input['hotel_name'] ?? '')),
            'hotel_logo'              => esc_url_raw(wp_unslash($input['hotel_logo'] ?? '')),
            'currency'                => strtoupper(sanitize_text_field(wp_unslash($input['currency'] ?? 'USD'))),
            'currency_symbol'         => sanitize_text_field(wp_unslash($input['currency_symbol'] ?? '$')),
            'currency_position'       => in_array($input['currency_position'] ?? '', ['before', 'after'], true)
                                            ? $input['currency_position'] : 'before',
            'delete_data_on_uninstall' => !empty($input['delete_data_on_uninstall']),
        ];

        update_option('hb_general', array_merge($existing, $sanitized));
    }

    private function save_booking(): void
    {
        $input = isset($_POST['hb_booking']) && is_array($_POST['hb_booking']) ? $_POST['hb_booking'] : [];

        $sanitized = [
            'default_check_in_time'     => $this->sanitize_time($input['default_check_in_time'] ?? '15:00'),
            'default_check_out_time'    => $this->sanitize_time($input['default_check_out_time'] ?? '11:00'),
            'allow_custom_check_in_time'  => !empty($input['allow_custom_check_in_time']),
            'allow_custom_check_out_time' => !empty($input['allow_custom_check_out_time']),
            'minimum_stay'              => max(1, (int) ($input['minimum_stay'] ?? 1)),
            'temporary_hold_minutes'    => max(1, min(60, (int) ($input['temporary_hold_minutes'] ?? 10))),
            'enable_terms_step'         => !empty($input['enable_terms_step']),
            'enable_checkin_time'       => !empty($input['enable_checkin_time']),
            'enable_checkout_time'      => !empty($input['enable_checkout_time']),
            'enable_adults'             => !empty($input['enable_adults']),
            'enable_children'           => !empty($input['enable_children']),
            'terms_checkbox_text'       => sanitize_text_field(wp_unslash($input['terms_checkbox_text'] ?? '')),
            // wp_kses_post keeps the formatting an editor produces while stripping
            // scripts and other unsafe markup.
            'terms_content'             => wp_kses_post(wp_unslash($input['terms_content'] ?? '')),
        ];

        update_option('hb_booking', array_merge(get_option('hb_booking', []), $sanitized));
    }

    private function save_payments(): void
    {
        $input = isset($_POST['hb_payments']) && is_array($_POST['hb_payments']) ? $_POST['hb_payments'] : [];

        $sanitized = [
            'stripe_enabled'              => !empty($input['stripe_enabled']),
            'stripe_mode'                 => in_array($input['stripe_mode'] ?? '', ['test', 'live'], true)
                                                ? $input['stripe_mode'] : 'test',
            'stripe_test_publishable_key' => sanitize_text_field(wp_unslash($input['stripe_test_publishable_key'] ?? '')),
            'stripe_test_secret_key'      => sanitize_text_field(wp_unslash($input['stripe_test_secret_key'] ?? '')),
            'stripe_live_publishable_key' => sanitize_text_field(wp_unslash($input['stripe_live_publishable_key'] ?? '')),
            'stripe_live_secret_key'      => sanitize_text_field(wp_unslash($input['stripe_live_secret_key'] ?? '')),
            'offline_payment_enabled'     => !empty($input['offline_payment_enabled']),
            'offline_payment_title'       => sanitize_text_field(wp_unslash($input['offline_payment_title'] ?? '')),
            'offline_payment_description' => sanitize_textarea_field(wp_unslash($input['offline_payment_description'] ?? '')),
            'enable_partial_payment'      => !empty($input['enable_partial_payment']),
            'initial_payment_percentage'  => max(1, min(99, (int) ($input['initial_payment_percentage'] ?? 50))),
            'balance_due_days_before_check_in' => max(0, (int) ($input['balance_due_days_before_check_in'] ?? 30)),
        ];

        update_option('hb_payments', array_merge(get_option('hb_payments', []), $sanitized));
    }

    private function save_emails(): void
    {
        $input = isset($_POST['hb_emails']) && is_array($_POST['hb_emails']) ? $_POST['hb_emails'] : [];

        $sanitized = [
            'admin_notification_email' => sanitize_email(wp_unslash($input['admin_notification_email'] ?? '')),
            'from_name'                => sanitize_text_field(wp_unslash($input['from_name'] ?? '')),
            'from_email'               => sanitize_email(wp_unslash($input['from_email'] ?? '')),
            'reply_to_email'           => sanitize_email(wp_unslash($input['reply_to_email'] ?? '')),
            'customer_booking_enabled' => !empty($input['customer_booking_enabled']),
            'admin_booking_enabled'    => !empty($input['admin_booking_enabled']),
        ];

        update_option('hb_emails', array_merge(get_option('hb_emails', []), $sanitized));
    }

    private function save_form_fields(): void
    {
        $input = isset($_POST['hb_form_fields']['fields']) && is_array($_POST['hb_form_fields']['fields'])
            ? $_POST['hb_form_fields']['fields'] : [];

        $sanitized_fields = [];
        $order = 1;

        foreach ($input as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = sanitize_key(wp_unslash($field['key'] ?? ''));
            if (empty($key)) {
                continue;
            }

            $type = sanitize_text_field(wp_unslash($field['type'] ?? 'text'));
            if (!in_array($type, self::FIELD_TYPES, true)) {
                $type = 'text';
            }

            $sanitized_fields[] = [
                'key'         => $key,
                'label'       => sanitize_text_field(wp_unslash($field['label'] ?? '')),
                'type'        => $type,
                'required'    => !empty($field['required']),
                'enabled'     => !empty($field['enabled']),
                'placeholder' => sanitize_text_field(wp_unslash($field['placeholder'] ?? '')),
                'sort_order'  => $order++,
                'system'      => !empty($field['system']),
            ];
        }

        update_option('hb_form_fields', ['fields' => $sanitized_fields]);
    }

    private function sanitize_time(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time;
        }
        return '12:00';
    }
}
