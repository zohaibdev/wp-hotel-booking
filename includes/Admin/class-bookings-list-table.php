<?php

namespace HotelBooking\Admin;

defined('ABSPATH') || exit;

use function HotelBooking\hb_format_price;
use function HotelBooking\hb_get_booking_statuses;
use function HotelBooking\hb_get_payment_statuses;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class BookingsListTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'booking',
            'plural'   => 'bookings',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'booking_number' => __('Booking', 'hotel-booking'),
            'customer'       => __('Customer', 'hotel-booking'),
            'room'           => __('Room', 'hotel-booking'),
            'dates'          => __('Dates', 'hotel-booking'),
            'total'          => __('Total', 'hotel-booking'),
            'booking_status' => __('Status', 'hotel-booking'),
            'payment_status' => __('Payment', 'hotel-booking'),
            'created_at'     => __('Created', 'hotel-booking'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'booking_number' => ['booking_number', false],
            'total'          => ['total_amount', false],
            'created_at'     => ['created_at', true],
        ];
    }

    public function prepare_items(): void
    {
        global $wpdb;

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby_map = [
            'booking_number' => 'b.booking_number',
            'total_amount'   => 'b.total_amount',
            'created_at'     => 'b.created_at',
        ];

        $raw_orderby = sanitize_text_field(wp_unslash($_GET['orderby'] ?? 'created_at'));
        $orderby     = $orderby_map[$raw_orderby] ?? 'b.created_at';

        $order = strtoupper(sanitize_text_field(wp_unslash($_GET['order'] ?? 'DESC')));
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $where = '1=1';
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                ' AND (b.booking_number LIKE %s OR c.email LIKE %s OR CONCAT(c.first_name, " ", c.last_name) LIKE %s)',
                $like, $like, $like
            );
        }

        $status_filter = sanitize_text_field(wp_unslash($_GET['booking_status'] ?? ''));
        if ($status_filter !== '') {
            $where .= $wpdb->prepare(' AND b.booking_status = %s', $status_filter);
        }

        $payment_filter = sanitize_text_field(wp_unslash($_GET['payment_status'] ?? ''));
        if ($payment_filter !== '') {
            $where .= $wpdb->prepare(' AND b.payment_status = %s', $payment_filter);
        }

        $room_filter = absint($_GET['room_id'] ?? 0);
        if ($room_filter > 0) {
            $where .= $wpdb->prepare(' AND b.room_id = %d', $room_filter);
        }

        $table_book = $wpdb->prefix . 'hb_bookings';
        $table_cust = $wpdb->prefix . 'hb_customers';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_items = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_book} b
             LEFT JOIN {$table_cust} c ON c.id = b.customer_id
             WHERE {$where}"
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.*, c.first_name, c.last_name, c.email
                 FROM {$table_book} b
                 LEFT JOIN {$table_cust} c ON c.id = b.customer_id
                 WHERE {$where}
                 ORDER BY {$orderby} {$order}
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total_items / $per_page),
        ]);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    protected function column_default($item, $column_name): string
    {
        return '';
    }

    protected function column_booking_number($item): string
    {
        $url = admin_url('admin.php?page=hotel-booking-bookings&action=view&id=' . (int) $item->id);
        return '<a href="' . esc_url($url) . '"><strong>' . esc_html($item->booking_number) . '</strong></a>';
    }

    protected function column_customer($item): string
    {
        $name = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
        if ($name === '') {
            return '&mdash;';
        }
        $url = admin_url('admin.php?page=hotel-booking-customers&action=view&id=' . (int) $item->customer_id);
        return '<a href="' . esc_url($url) . '">' . esc_html($name) . '</a><br><small>' . esc_html($item->email ?? '') . '</small>';
    }

    protected function column_room($item): string
    {
        $title = get_the_title((int) $item->room_id);
        return $title ? esc_html($title) : '&mdash;';
    }

    protected function column_dates($item): string
    {
        $format = get_option('date_format');
        $in  = wp_date($format, strtotime($item->check_in));
        $out = wp_date($format, strtotime($item->check_out));
        return esc_html($in) . '<br>&rarr; ' . esc_html($out);
    }

    protected function column_total($item): string
    {
        return esc_html(hb_format_price((float) $item->total_amount));
    }

    protected function column_booking_status($item): string
    {
        $statuses = hb_get_booking_statuses();
        $label = $statuses[$item->booking_status] ?? $item->booking_status;
        $class = 'hb-badge hb-badge--' . sanitize_html_class($item->booking_status);
        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    protected function column_payment_status($item): string
    {
        $statuses = hb_get_payment_statuses();
        $label = $statuses[$item->payment_status] ?? $item->payment_status;
        $class = 'hb-badge hb-badge--' . sanitize_html_class($item->payment_status);
        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    protected function column_created_at($item): string
    {
        return esc_html(wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($item->created_at)
        ));
    }

    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        echo '<div class="alignleft actions">';

        $current_status = sanitize_text_field(wp_unslash($_GET['booking_status'] ?? ''));
        $statuses = array_merge(['' => __('All Statuses', 'hotel-booking')], hb_get_booking_statuses());
        echo '<select name="booking_status">';
        foreach ($statuses as $val => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($current_status, $val, false), esc_html($label));
        }
        echo '</select>';

        $current_payment = sanitize_text_field(wp_unslash($_GET['payment_status'] ?? ''));
        $payments = array_merge(['' => __('All Payments', 'hotel-booking')], hb_get_payment_statuses());
        echo '<select name="payment_status">';
        foreach ($payments as $val => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($current_payment, $val, false), esc_html($label));
        }
        echo '</select>';

        submit_button(__('Filter', 'hotel-booking'), '', 'filter_action', false);
        echo '</div>';
    }
}
