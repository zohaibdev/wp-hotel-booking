<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;max-width:100%;">
    <tr>
        <td style="background:#dc2626;padding:24px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:22px;"><?php esc_html_e('Payment Failed', 'hotel-booking'); ?></h1>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 24px;">
            <p style="font-size:16px;color:#1e293b;margin:0 0 8px;">
                <?php printf(
                    esc_html__('Dear %s,', 'hotel-booking'),
                    esc_html($customer_name ?: __('Guest', 'hotel-booking'))
                ); ?>
            </p>
            <p style="font-size:15px;color:#475569;margin:0 0 24px;">
                <?php printf(
                    /* translators: %s: booking number */
                    esc_html__('Unfortunately, the payment for your booking %s could not be processed. Please try again or contact us for assistance.', 'hotel-booking'),
                    '<strong>' . esc_html($booking_number) . '</strong>'
                ); ?>
            </p>

            <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:6px;font-size:14px;color:#1e293b;">
                <tr>
                    <td style="font-weight:600;width:140px;"><?php esc_html_e('Room', 'hotel-booking'); ?></td>
                    <td><?php echo esc_html($room_name); ?></td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="font-weight:600;"><?php esc_html_e('Amount', 'hotel-booking'); ?></td>
                    <td><?php echo esc_html($total); ?></td>
                </tr>
            </table>

            <p style="font-size:14px;color:#64748b;margin:24px 0 0;">
                <?php esc_html_e('If you believe this is an error, please contact our front desk.', 'hotel-booking'); ?>
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
