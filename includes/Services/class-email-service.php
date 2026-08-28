<?php

namespace HotelBooking\Services;

use HotelBooking\Logging\Logger;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;
use function HotelBooking\hb_format_price;
use function HotelBooking\hb_get_booking_statuses;
use function HotelBooking\hb_get_payment_statuses_extended;

final class EmailService
{
    public function send_booking_confirmation(int $booking_id): bool
    {
        if (!hb_get_option('hb_emails', 'customer_booking_enabled', true)) {
            Logger::info('Customer confirmation email skipped: disabled in settings', [
                'booking_id' => $booking_id,
            ]);
            return false;
        }

        $data = $this->get_booking_data($booking_id);
        if (!$data) {
            Logger::error('Customer confirmation email skipped: booking data unavailable', [
                'booking_id' => $booking_id,
            ]);
            return false;
        }

        $to = $data['customer']->email ?? '';
        if (empty($to)) {
            Logger::error('Customer confirmation email skipped: booking has no customer email', [
                'booking_id'     => $booking_id,
                'booking_number' => $data['booking_number'],
            ]);
            return false;
        }

        $subject = $this->replace_placeholders(
            /* translators: {booking_number} is replaced with the booking number */
            __('Booking Confirmation #{booking_number}', 'hotel-booking'),
            $data
        );

        $body = $this->render('booking-confirmation-customer', $data);
        if ($body === '') {
            return false;
        }

        return $this->send($to, $subject, $body, 'customer_confirmation:' . $data['booking_number']);
    }

    public function send_admin_notification(int $booking_id): bool
    {
        if (!hb_get_option('hb_emails', 'admin_booking_enabled', true)) {
            Logger::info('Admin notification email skipped: disabled in settings', [
                'booking_id' => $booking_id,
            ]);
            return false;
        }

        $data = $this->get_booking_data($booking_id);
        if (!$data) {
            Logger::error('Admin notification email skipped: booking data unavailable', [
                'booking_id' => $booking_id,
            ]);
            return false;
        }

        // Fall back to the site admin address rather than dropping the notice, but
        // never to an unrelated recipient.
        $to = (string) hb_get_option('hb_emails', 'admin_notification_email', '');
        if (sanitize_email(trim($to)) === '') {
            $to = (string) get_option('admin_email');
        }

        if (empty($to)) {
            Logger::error('Admin notification email skipped: no admin recipient configured', [
                'booking_id' => $booking_id,
            ]);
            return false;
        }

        $subject = $this->replace_placeholders(
            /* translators: {booking_number} is replaced with the booking number */
            __('New Booking #{booking_number}', 'hotel-booking'),
            $data
        );

        $body = $this->render('new-booking-admin', $data);
        if ($body === '') {
            return false;
        }

        return $this->send($to, $subject, $body, 'admin_notification:' . $data['booking_number']);
    }

    public function send_payment_failed(int $booking_id): bool
    {
        $data = $this->get_booking_data($booking_id);
        if (!$data) {
            return false;
        }

        $to = $data['customer']->email ?? '';
        if (empty($to)) {
            return false;
        }

        $subject = $this->replace_placeholders(
            __('Payment Failed for Booking #{booking_number}', 'hotel-booking'),
            $data
        );

        ob_start();
        $this->load_template('payment-failed', $data);
        $body = ob_get_clean();

        return $this->send($to, $subject, $body);
    }

    /** Captured PHPMailer reason from the wp_mail_failed hook, per send. */
    private string $last_mail_error = '';

    public function get_last_error(): string
    {
        return $this->last_mail_error;
    }

    /**
     * Builds a valid From address. An empty or malformed configured value would
     * otherwise produce `From: Name <>`, which makes PHPMailer throw and wp_mail
     * return false with no visible reason -- a silent, total mail failure.
     */
    private function resolve_from_email(): string
    {
        $candidates = [
            (string) hb_get_option('hb_emails', 'from_email', ''),
            (string) get_option('admin_email'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = sanitize_email(trim($candidate));
            if ($candidate !== '' && is_email($candidate)) {
                return $candidate;
            }
        }

        // Last resort mirrors WordPress core's own default sender.
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost';
        return 'wordpress@' . preg_replace('/^www\./i', '', $host);
    }

    /**
     * A display name containing a comma, angle bracket or quote splits the header
     * and breaks delivery, so quote it when needed and strip control characters.
     */
    private function encode_from_name(string $name): string
    {
        $name = trim(preg_replace('/[\r\n\t]+/', ' ', wp_strip_all_tags($name)));

        if ($name === '') {
            return '';
        }

        if (preg_match('/[",<>:;@()\[\]\\\\]/', $name)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"';
        }

        return $name;
    }

    private function send(string $to, string $subject, string $body, string $context = ''): bool
    {
        $this->last_mail_error = '';

        $to = sanitize_email(trim($to));
        if ($to === '' || !is_email($to)) {
            Logger::error('Email not sent: recipient address is missing or invalid', [
                'context' => $context,
            ]);
            return false;
        }

        $from_email = $this->resolve_from_email();
        $from_name  = $this->encode_from_name(
            (string) hb_get_option('hb_emails', 'from_name', get_bloginfo('name'))
        );

        // Header injection guard: a newline in the subject would let the rest of
        // the line be interpreted as additional headers.
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject));

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $headers[] = $from_name !== ''
            ? "From: {$from_name} <{$from_email}>"
            : "From: {$from_email}";

        $reply_to = sanitize_email(trim((string) hb_get_option('hb_emails', 'reply_to_email', '')));
        if ($reply_to !== '' && is_email($reply_to) && $reply_to !== $from_email) {
            $headers[] = "Reply-To: {$reply_to}";
        }

        // wp_mail swallows the PHPMailer exception and just returns false; this is
        // the only way to learn why delivery failed.
        $capture = function ($wp_error) {
            $this->last_mail_error = is_wp_error($wp_error) ? $wp_error->get_error_message() : 'Unknown error';
        };
        add_action('wp_mail_failed', $capture);

        try {
            $result = wp_mail($to, $subject, $body, $headers);
        } catch (\Throwable $e) {
            $result = false;
            $this->last_mail_error = $e->getMessage();
        } finally {
            remove_action('wp_mail_failed', $capture);
        }

        if ($result) {
            Logger::info('Email handed to wp_mail successfully', [
                'context'    => $context,
                'to_domain'  => substr((string) strrchr($to, '@'), 1),
                'from_email' => $from_email,
                'subject'    => $subject,
            ]);
        } else {
            Logger::error('wp_mail returned false -- the message was not accepted for delivery', [
                'context'      => $context,
                'to_domain'    => substr((string) strrchr($to, '@'), 1),
                'from_email'   => $from_email,
                'subject'      => $subject,
                'mailer_error' => $this->last_mail_error,
            ]);
        }

        return (bool) $result;
    }

    /**
     * Sends a plain diagnostic message using the configured sender settings, so an
     * administrator can tell a plugin problem apart from a host with no working
     * mail transport. Returns [success, error].
     */
    public function send_test_email(string $to): array
    {
        $body = '<p>' . esc_html__('This is a test email from the Hotel Booking plugin.', 'hotel-booking') . '</p>'
            . '<p>' . esc_html__('If you received this, your site can send mail and booking notifications should arrive.', 'hotel-booking') . '</p>';

        $ok = $this->send($to, __('Hotel Booking test email', 'hotel-booking'), $body, 'test');

        return ['success' => $ok, 'error' => $this->last_mail_error];
    }

    private function get_booking_data(int $booking_id): ?array
    {
        global $wpdb;

        $booking_service = new BookingService();
        $booking = $booking_service->get($booking_id);
        if (!$booking) {
            return null;
        }

        $customer_service = new CustomerService();
        $customer = $booking->customer_id ? $customer_service->get((int) $booking->customer_id) : null;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $amenities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hb_booking_amenities WHERE booking_id = %d",
            $booking_id
        ));

        $invoice_service = new InvoiceService();
        $invoice = $invoice_service->get_by_booking($booking_id);

        // Real gateway reference, so the confirmation screen, both emails and the
        // admin detail page all quote the same identifier.
        $transaction_id = (new PaymentService())->get_transaction_id($booking_id);

        $room_title = get_the_title($booking->room_id) ?: __('(deleted room)', 'hotel-booking');

        $amenity_names = [];
        $amenity_details = [];
        foreach ($amenities as $a) {
            $amenity_names[] = $a->name;
            if ((float) $a->subtotal > 0) {
                $amenity_details[] = $a->name . ': ' . hb_format_price((float) $a->subtotal);
            } else {
                $amenity_details[] = $a->name . ': ' . __('Included', 'hotel-booking');
            }
        }

        $statuses = hb_get_booking_statuses();
        $payment_statuses = hb_get_payment_statuses_extended();

        $is_partial = ($booking->payment_plan ?? 'full_payment') === 'partial_payment'
            && (float) ($booking->remaining_balance ?? 0) > 0;

        $balance_due = !empty($booking->balance_due_date)
            ? wp_date(get_option('date_format'), strtotime($booking->balance_due_date))
            : '';

        return [
            'booking'          => $booking,
            'customer'         => $customer,
            'amenities'        => $amenities,
            'invoice'          => $invoice,
            'room_name'        => $room_title,
            'booking_number'   => $booking->booking_number,
            'customer_name'    => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
            'customer_email'   => $customer->email ?? '',
            'check_in'         => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->check_in)),
            'check_out'        => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->check_out)),
            'adults'           => (int) $booking->adults,
            'children'         => (int) $booking->children,
            'amenity_list'     => implode(', ', $amenity_names),
            'amenity_details'  => $amenity_details,
            'customer_phone'   => $customer->phone ?? '',
            'room_subtotal'    => hb_format_price((float) $booking->room_subtotal),
            'amenities_total'  => hb_format_price((float) $booking->amenities_subtotal),
            'has_tax'          => (float) $booking->tax_amount > 0,
            'transaction_id'   => $transaction_id,

            // Payment plan, so both templates can show what was paid and what is
            // still owed without recomputing anything.
            'is_partial'        => $is_partial,
            'payment_plan'      => $booking->payment_plan ?? 'full_payment',
            'paid_now'          => hb_format_price((float) ($booking->initial_payment_amount ?? $booking->total_amount)),
            'remaining_balance' => hb_format_price((float) ($booking->remaining_balance ?? 0)),
            'balance_due_date'  => $balance_due,
            'terms_accepted'    => !empty($booking->terms_accepted),
            'terms_accepted_at' => !empty($booking->terms_accepted_at)
                ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->terms_accepted_at))
                : '',
            'subtotal'         => hb_format_price((float) $booking->room_subtotal + (float) $booking->amenities_subtotal),
            'tax'              => hb_format_price((float) $booking->tax_amount),
            'total'            => hb_format_price((float) $booking->total_amount),
            'payment_method'   => $booking->payment_method === 'stripe' ? __('Credit/Debit Card', 'hotel-booking') : __('Pay at Hotel', 'hotel-booking'),
            'payment_status'   => $payment_statuses[$booking->payment_status] ?? $booking->payment_status,
            'booking_status'   => $statuses[$booking->booking_status] ?? $booking->booking_status,
            'hotel_name'       => hb_get_option('hb_general', 'hotel_name', get_bloginfo('name')),
            'hotel_logo'       => hb_get_option('hb_general', 'hotel_logo', ''),
        ];
    }

    private function replace_placeholders(string $text, array $data): string
    {
        $replacements = [
            '{booking_number}' => $data['booking_number'] ?? '',
            '{customer_name}'  => $data['customer_name'] ?? '',
            '{customer_email}' => $data['customer_email'] ?? '',
            '{room_name}'      => $data['room_name'] ?? '',
            '{check_in}'       => $data['check_in'] ?? '',
            '{check_out}'      => $data['check_out'] ?? '',
            '{adults}'         => (string) ($data['adults'] ?? 0),
            '{children}'       => (string) ($data['children'] ?? 0),
            '{amenities}'      => $data['amenity_list'] ?? '',
            '{subtotal}'       => $data['subtotal'] ?? '',
            '{tax}'            => $data['tax'] ?? '',
            '{total}'          => $data['total'] ?? '',
            '{payment_method}' => $data['payment_method'] ?? '',
            '{payment_status}' => $data['payment_status'] ?? '',
            '{transaction_id}' => $data['transaction_id'] ?? '',
            '{hotel_name}'     => $data['hotel_name'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Renders a template to a string. A missing template or a PHP error inside one
     * must not abort the caller mid-buffer, so the buffer is always closed and the
     * failure is logged and reported as an empty body.
     */
    private function render(string $template_name, array $data): string
    {
        $file = HB_PLUGIN_DIR . "templates/emails/{$template_name}.php";

        if (!file_exists($file)) {
            Logger::error('Email template not found', [
                'template' => $template_name,
                'path'     => $file,
            ]);
            return '';
        }

        ob_start();

        try {
            // phpcs:ignore WordPress.PHP.DontExtract
            extract($data, EXTR_SKIP);
            include $file;
            $body = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            Logger::error('Email template failed to render', [
                'template' => $template_name,
                'error'    => $e->getMessage(),
            ]);
            return '';
        }

        if (trim($body) === '') {
            Logger::error('Email template rendered an empty body', ['template' => $template_name]);
            return '';
        }

        return $body;
    }
}
