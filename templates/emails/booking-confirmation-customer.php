<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;max-width:100%;">
    <tr>
        <td style="background:#2563eb;padding:24px;text-align:center;">
            <?php if (!empty($hotel_logo)) : ?>
                <img src="<?php echo esc_url($hotel_logo); ?>" alt="<?php echo esc_attr($hotel_name); ?>" style="max-height:48px;margin-bottom:8px;">
            <?php endif; ?>
            <h1 style="color:#fff;margin:0;font-size:22px;"><?php esc_html_e('Booking Confirmed!', 'hotel-booking'); ?></h1>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 24px;">
            <p style="font-size:16px;color:#1e293b;margin:0 0 8px;">
                <?php printf(
                    /* translators: %s: customer first name */
                    esc_html__('Dear %s,', 'hotel-booking'),
                    esc_html($customer_name ?: __('Guest', 'hotel-booking'))
                ); ?>
            </p>
            <p style="font-size:15px;color:#475569;margin:0 0 24px;">
                <?php esc_html_e('Thank you for your reservation. Here are your booking details:', 'hotel-booking'); ?>
            </p>

            <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:6px;font-size:14px;color:#1e293b;">
                <tr style="background:#f8fafc;">
                    <td style="font-weight:600;width:140px;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Booking #', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html($booking_number); ?></td>
                </tr>
                <tr>
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Room', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html($room_name); ?></td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Check-in', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html($check_in); ?></td>
                </tr>
                <tr>
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Check-out', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html($check_out); ?></td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Guests', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html($adults); ?> <?php esc_html_e('Adult(s)', 'hotel-booking'); ?>, <?php echo esc_html($children); ?> <?php esc_html_e('Child(ren)', 'hotel-booking'); ?></td>
                </tr>
                <?php if (!empty($amenity_details)) : ?>
                <tr>
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Amenities', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html(implode(', ', $amenity_details)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Room Subtotal', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html($room_subtotal); ?></td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Amenities Subtotal', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html($amenities_total); ?></td>
                </tr>
                <?php if (!empty($has_tax)) : ?>
                <tr>
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Tax', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;"><?php echo esc_html($tax); ?></td>
                </tr>
                <?php endif; ?>
                <tr style="background:#f8fafc;">
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Total', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;font-weight:700;font-size:16px;"><?php echo esc_html($total); ?></td>
                </tr>
                <tr>
                    <td style="font-weight:600;<?php echo !empty($transaction_id) ? 'border-bottom:1px solid #e2e8f0;' : ''; ?>"><?php esc_html_e('Payment', 'hotel-booking'); ?></td>
                    <td style="<?php echo !empty($transaction_id) ? 'border-bottom:1px solid #e2e8f0;' : ''; ?>"><?php echo esc_html($payment_method); ?> — <?php echo esc_html($payment_status); ?></td>
                </tr>
                <?php if (!empty($transaction_id)) : ?>
                <tr style="background:#f8fafc;">
                    <td style="font-weight:600;<?php echo !empty($is_partial) ? 'border-bottom:1px solid #e2e8f0;' : ''; ?>"><?php esc_html_e('Transaction ID', 'hotel-booking'); ?></td>
                    <td style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;word-break:break-all;<?php echo !empty($is_partial) ? 'border-bottom:1px solid #e2e8f0;' : ''; ?>">
                        <?php echo esc_html($transaction_id); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($is_partial)) : ?>
                <tr>
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;color:#16a34a;"><?php esc_html_e('Paid Now', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;font-weight:700;color:#16a34a;"><?php echo esc_html($paid_now); ?></td>
                </tr>
                <tr style="background:#fffbeb;">
                    <td style="font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Remaining Balance', 'hotel-booking'); ?></td>
                    <td style="border-bottom:1px solid #e2e8f0;font-weight:700;"><?php echo esc_html($remaining_balance); ?></td>
                </tr>
                <tr style="background:#fffbeb;">
                    <td style="font-weight:600;"><?php esc_html_e('Balance Due By', 'hotel-booking'); ?></td>
                    <td style="font-weight:600;"><?php echo esc_html($balance_due_date); ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <?php if (!empty($is_partial)) : ?>
                <p style="font-size:14px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;margin:20px 0 0;">
                    <?php
                    printf(
                        /* translators: 1: remaining balance, 2: due date */
                        esc_html__('This booking is secured with a deposit. The remaining balance of %1$s is due by %2$s.', 'hotel-booking'),
                        '<strong>' . esc_html($remaining_balance) . '</strong>',
                        '<strong>' . esc_html($balance_due_date) . '</strong>'
                    );
                    ?>
                </p>
            <?php endif; ?>

            <p style="font-size:14px;color:#64748b;margin:24px 0 0;">
                <?php esc_html_e('If you have any questions, please do not hesitate to contact us.', 'hotel-booking'); ?>
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
