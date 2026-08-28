<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;max-width:100%;">
    <tr>
        <td style="background:#1e293b;padding:24px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:20px;"><?php esc_html_e('New Booking Received', 'hotel-booking'); ?></h1>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 24px;">
            <p style="font-size:15px;color:#475569;margin:0 0 20px;">
                <?php printf(
                    /* translators: %s: booking number */
                    esc_html__('A new booking (%s) has been received. Details below:', 'hotel-booking'),
                    '<strong>' . esc_html($booking_number) . '</strong>'
                ); ?>
            </p>

            <h3 style="font-size:14px;color:#1e293b;margin:0 0 8px;text-transform:uppercase;letter-spacing:0.5px;">
                <?php esc_html_e('Customer', 'hotel-booking'); ?>
            </h3>
            <p style="font-size:14px;color:#475569;margin:0 0 16px;">
                <?php echo esc_html($customer_name); ?><br>
                <a href="mailto:<?php echo esc_attr($customer_email); ?>" style="color:#2563eb;"><?php echo esc_html($customer_email); ?></a><br>
                <?php if (!empty($customer_phone)) : ?>
                    <?php echo esc_html($customer_phone); ?>
                <?php endif; ?>
            </p>

            <h3 style="font-size:14px;color:#1e293b;margin:0 0 8px;text-transform:uppercase;letter-spacing:0.5px;">
                <?php esc_html_e('Booking Details', 'hotel-booking'); ?>
            </h3>
            <table width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#1e293b;margin-bottom:16px;">
                <tr><td style="font-weight:600;width:120px;"><?php esc_html_e('Room', 'hotel-booking'); ?></td><td><?php echo esc_html($room_name); ?></td></tr>
                <tr><td style="font-weight:600;"><?php esc_html_e('Check-in', 'hotel-booking'); ?></td><td><?php echo esc_html($check_in); ?></td></tr>
                <tr><td style="font-weight:600;"><?php esc_html_e('Check-out', 'hotel-booking'); ?></td><td><?php echo esc_html($check_out); ?></td></tr>
                <tr><td style="font-weight:600;"><?php esc_html_e('Guests', 'hotel-booking'); ?></td><td><?php echo esc_html($adults); ?> <?php esc_html_e('adult(s)', 'hotel-booking'); ?>, <?php echo esc_html($children); ?> <?php esc_html_e('child(ren)', 'hotel-booking'); ?></td></tr>
                <?php if (!empty($amenity_details)) : ?>
                <tr><td style="font-weight:600;vertical-align:top;"><?php esc_html_e('Amenities', 'hotel-booking'); ?></td><td><?php echo esc_html(implode(', ', $amenity_details)); ?></td></tr>
                <?php endif; ?>
                <tr><td style="font-weight:600;"><?php esc_html_e('Total', 'hotel-booking'); ?></td><td style="font-weight:700;"><?php echo esc_html($total); ?></td></tr>
                <tr><td style="font-weight:600;"><?php esc_html_e('Payment', 'hotel-booking'); ?></td><td><?php echo esc_html($payment_method); ?> (<?php echo esc_html($payment_status); ?>)</td></tr>
                <?php if (!empty($transaction_id)) : ?>
                <tr><td style="font-weight:600;"><?php esc_html_e('Transaction ID', 'hotel-booking'); ?></td><td style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;word-break:break-all;"><?php echo esc_html($transaction_id); ?></td></tr>
                <?php endif; ?>
                <tr><td style="font-weight:600;"><?php esc_html_e('Payment Plan', 'hotel-booking'); ?></td><td><?php echo !empty($is_partial) ? esc_html__('Partial payment (deposit)', 'hotel-booking') : esc_html__('Full payment', 'hotel-booking'); ?></td></tr>
                <?php if (!empty($is_partial)) : ?>
                <tr><td style="font-weight:600;"><?php esc_html_e('Paid Now', 'hotel-booking'); ?></td><td style="color:#16a34a;font-weight:700;"><?php echo esc_html($paid_now); ?></td></tr>
                <tr><td style="font-weight:600;"><?php esc_html_e('Remaining Balance', 'hotel-booking'); ?></td><td style="font-weight:700;"><?php echo esc_html($remaining_balance); ?></td></tr>
                <tr><td style="font-weight:600;"><?php esc_html_e('Balance Due By', 'hotel-booking'); ?></td><td><?php echo esc_html($balance_due_date); ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($terms_accepted)) : ?>
                <tr><td style="font-weight:600;"><?php esc_html_e('Terms Accepted', 'hotel-booking'); ?></td><td><?php echo esc_html($terms_accepted_at); ?></td></tr>
                <?php endif; ?>
                <tr><td style="font-weight:600;"><?php esc_html_e('Status', 'hotel-booking'); ?></td><td><?php echo esc_html($booking_status); ?></td></tr>
            </table>

            <?php if (!empty($booking->special_requests)) : ?>
            <h3 style="font-size:14px;color:#1e293b;margin:0 0 8px;text-transform:uppercase;letter-spacing:0.5px;">
                <?php esc_html_e('Special Requests', 'hotel-booking'); ?>
            </h3>
            <p style="font-size:14px;color:#475569;margin:0 0 16px;padding:12px;background:#f8fafc;border-radius:4px;">
                <?php echo esc_html($booking->special_requests); ?>
            </p>
            <?php endif; ?>

            <p style="margin:20px 0 0;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=hotel-booking-bookings&action=view&id=' . $booking->id)); ?>"
                   style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600;">
                    <?php esc_html_e('View Booking', 'hotel-booking'); ?>
                </a>
            </p>
        </td>
    </tr>
    <tr>
        <td style="background:#f8fafc;padding:16px 24px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;">
            &copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html($hotel_name); ?>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
