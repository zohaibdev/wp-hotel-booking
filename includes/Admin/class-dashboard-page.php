<?php

namespace HotelBooking\Admin;

use HotelBooking\PostTypes\RoomPostType;

defined('ABSPATH') || exit;

use function HotelBooking\hb_format_price;
use function HotelBooking\hb_get_booking_statuses;

final class DashboardPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'hotel-booking'));
        }

        global $wpdb;
        $bookings_table = $wpdb->prefix . 'hb_bookings';
        $customers_table = $wpdb->prefix . 'hb_customers';

        $stats = $this->get_stats($bookings_table, $customers_table);
        $recent_bookings = $this->get_recent_bookings($bookings_table);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Hotel Booking Dashboard', 'hotel-booking'); ?></h1>

            <div class="hb-dashboard-stats">
                <?php $this->render_stat_card(
                    __('Total Bookings', 'hotel-booking'),
                    (string) $stats['total_bookings'],
                    'dashicons-calendar-alt'
                ); ?>
                <?php $this->render_stat_card(
                    __('Confirmed', 'hotel-booking'),
                    (string) $stats['confirmed_bookings'],
                    'dashicons-yes-alt'
                ); ?>
                <?php $this->render_stat_card(
                    __('Pending', 'hotel-booking'),
                    (string) $stats['pending_bookings'],
                    'dashicons-clock'
                ); ?>
                <?php $this->render_stat_card(
                    __('Revenue', 'hotel-booking'),
                    hb_format_price($stats['total_revenue']),
                    'dashicons-money-alt'
                ); ?>
                <?php $this->render_stat_card(
                    __('Customers', 'hotel-booking'),
                    (string) $stats['total_customers'],
                    'dashicons-groups'
                ); ?>
                <?php $this->render_stat_card(
                    __('Rooms', 'hotel-booking'),
                    (string) $stats['total_rooms'],
                    'dashicons-building'
                ); ?>
            </div>

            <div class="hb-dashboard-recent">
                <h2><?php esc_html_e('Recent Bookings', 'hotel-booking'); ?></h2>
                <?php if (empty($recent_bookings)) : ?>
                    <p><?php esc_html_e('No bookings yet.', 'hotel-booking'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Booking #', 'hotel-booking'); ?></th>
                                <th><?php esc_html_e('Room', 'hotel-booking'); ?></th>
                                <th><?php esc_html_e('Check-in', 'hotel-booking'); ?></th>
                                <th><?php esc_html_e('Check-out', 'hotel-booking'); ?></th>
                                <th><?php esc_html_e('Total', 'hotel-booking'); ?></th>
                                <th><?php esc_html_e('Status', 'hotel-booking'); ?></th>
                                <th><?php esc_html_e('Created', 'hotel-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking) : ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=hotel-booking-bookings&action=view&id=' . $booking->id)); ?>">
                                            <?php echo esc_html($booking->booking_number); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(get_the_title($booking->room_id) ?: __('(deleted)', 'hotel-booking')); ?></td>
                                    <td><?php echo esc_html(wp_date(get_option('date_format'), strtotime($booking->check_in))); ?></td>
                                    <td><?php echo esc_html(wp_date(get_option('date_format'), strtotime($booking->check_out))); ?></td>
                                    <td><?php echo esc_html(hb_format_price((float) $booking->total_amount)); ?></td>
                                    <td>
                                        <?php
                                        $statuses = hb_get_booking_statuses();
                                        $class = 'hb-badge hb-badge-' . sanitize_html_class($booking->booking_status);
                                        $label = $statuses[$booking->booking_status] ?? $booking->booking_status;
                                        ?>
                                        <span class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></span>
                                    </td>
                                    <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_at))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <p class="hb-credit">
                <?php
                printf(
                    /* translators: %s: linked plugin author name */
                    esc_html__('Hotel Booking — developed by %s', 'hotel-booking'),
                    '<a href="' . esc_url(HB_AUTHOR_URL) . '" target="_blank" rel="noopener noreferrer">'
                        . esc_html(HB_AUTHOR) . '</a>'
                );
                ?>
            </p>
        </div>

        <style>
            .hb-credit {
                margin-top: 24px;
                padding-top: 14px;
                border-top: 1px solid #dcdcde;
                color: #646970;
                font-size: 13px;
            }
            .hb-dashboard-stats {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 16px;
                margin: 20px 0;
            }
            .hb-stat-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
            }
            .hb-stat-card .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
                color: #2271b1;
                margin-bottom: 8px;
            }
            .hb-stat-card .hb-stat-value {
                font-size: 28px;
                font-weight: 600;
                line-height: 1.2;
                margin: 4px 0;
            }
            .hb-stat-card .hb-stat-label {
                color: #50575e;
                font-size: 13px;
            }
            .hb-dashboard-recent {
                margin-top: 20px;
            }
        </style>
        <?php
    }

    private function render_stat_card(string $label, string $value, string $icon): void
    {
        ?>
        <div class="hb-stat-card">
            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            <div class="hb-stat-value"><?php echo esc_html($value); ?></div>
            <div class="hb-stat-label"><?php echo esc_html($label); ?></div>
        </div>
        <?php
    }

    private function get_stats(string $bookings_table, string $customers_table): array
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $total_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table}");
        $confirmed_bookings = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$bookings_table} WHERE booking_status = %s", 'confirmed')
        );
        $pending_bookings = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$bookings_table} WHERE booking_status = %s", 'pending')
        );
        $total_revenue = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_amount), 0) FROM {$bookings_table} WHERE payment_status = %s",
                'paid'
            )
        );
        $total_customers = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$customers_table}");
        // phpcs:enable

        $room_count = wp_count_posts(RoomPostType::SLUG);
        $total_rooms = ($room_count->publish ?? 0) + ($room_count->draft ?? 0);

        return [
            'total_bookings'     => $total_bookings,
            'confirmed_bookings' => $confirmed_bookings,
            'pending_bookings'   => $pending_bookings,
            'total_revenue'      => $total_revenue,
            'total_customers'    => $total_customers,
            'total_rooms'        => $total_rooms,
        ];
    }

    private function get_recent_bookings(string $bookings_table): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        return $wpdb->get_results(
            "SELECT id, booking_number, room_id, total_amount, booking_status, check_in, check_out, created_at
             FROM {$bookings_table}
             ORDER BY created_at DESC
             LIMIT 10"
        ) ?: [];
    }
}
