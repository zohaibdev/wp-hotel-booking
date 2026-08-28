<?php

namespace HotelBooking\Admin;

defined('ABSPATH') || exit;

use function HotelBooking\hb_format_price;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class InvoicesListTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'invoice',
            'plural'   => 'invoices',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'invoice_number' => __('Invoice', 'hotel-booking'),
            'booking_number' => __('Booking', 'hotel-booking'),
            'customer'       => __('Customer', 'hotel-booking'),
            'total'          => __('Total', 'hotel-booking'),
            'status'         => __('Status', 'hotel-booking'),
            'issued_at'      => __('Issued', 'hotel-booking'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'invoice_number' => ['invoice_number', false],
            'total'          => ['total', false],
            'issued_at'      => ['issued_at', true],
        ];
    }

    public function prepare_items(): void
    {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $orderby_map = [
            'invoice_number' => 'i.invoice_number',
            'total'          => 'i.total',
            'issued_at'      => 'i.issued_at',
        ];

        $raw_orderby = sanitize_text_field(wp_unslash($_GET['orderby'] ?? 'issued_at'));
        $orderby = $orderby_map[$raw_orderby] ?? 'i.issued_at';

        $order = strtoupper(sanitize_text_field(wp_unslash($_GET['order'] ?? 'DESC')));
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $where = '1=1';
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                ' AND (i.invoice_number LIKE %s OR b.booking_number LIKE %s OR c.email LIKE %s OR CONCAT(c.first_name, " ", c.last_name) LIKE %s)',
                $like, $like, $like, $like
            );
        }

        $status_filter = sanitize_text_field(wp_unslash($_GET['invoice_status'] ?? ''));
        if ($status_filter !== '') {
            $where .= $wpdb->prepare(' AND i.status = %s', $status_filter);
        }

        $table_inv = $wpdb->prefix . 'hb_invoices';
        $table_book = $wpdb->prefix . 'hb_bookings';
        $table_cust = $wpdb->prefix . 'hb_customers';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_items = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_inv} i
             LEFT JOIN {$table_book} b ON b.id = i.booking_id
             LEFT JOIN {$table_cust} c ON c.id = b.customer_id
             WHERE {$where}"
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.*, b.booking_number, b.customer_id, c.first_name, c.last_name, c.email
                 FROM {$table_inv} i
                 LEFT JOIN {$table_book} b ON b.id = i.booking_id
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

    protected function column_invoice_number($item): string
    {
        return '<strong>' . esc_html($item->invoice_number) . '</strong>';
    }

    protected function column_booking_number($item): string
    {
        if (empty($item->booking_number)) {
            return '&mdash;';
        }
        $url = admin_url('admin.php?page=hotel-booking-bookings&action=view&id=' . (int) $item->booking_id);
        return '<a href="' . esc_url($url) . '">' . esc_html($item->booking_number) . '</a>';
    }

    protected function column_customer($item): string
    {
        $name = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
        if ($name === '') {
            return '&mdash;';
        }
        return esc_html($name) . '<br><small>' . esc_html($item->email ?? '') . '</small>';
    }

    protected function column_total($item): string
    {
        return esc_html(hb_format_price((float) $item->total));
    }

    protected function column_status($item): string
    {
        $statuses = [
            'issued' => __('Issued', 'hotel-booking'),
            'paid'   => __('Paid', 'hotel-booking'),
            'void'   => __('Void', 'hotel-booking'),
        ];
        $label = $statuses[$item->status] ?? $item->status;
        $class = 'hb-badge hb-badge--' . sanitize_html_class($item->status);
        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    protected function column_issued_at($item): string
    {
        if (empty($item->issued_at)) {
            return '&mdash;';
        }
        return esc_html(wp_date(get_option('date_format'), strtotime($item->issued_at)));
    }

    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        $current_status = sanitize_text_field(wp_unslash($_GET['invoice_status'] ?? ''));
        $statuses = [
            ''       => __('All Statuses', 'hotel-booking'),
            'issued' => __('Issued', 'hotel-booking'),
            'paid'   => __('Paid', 'hotel-booking'),
            'void'   => __('Void', 'hotel-booking'),
        ];

        echo '<div class="alignleft actions">';
        echo '<select name="invoice_status">';
        foreach ($statuses as $val => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($val),
                selected($current_status, $val, false),
                esc_html($label)
            );
        }
        echo '</select>';
        submit_button(__('Filter', 'hotel-booking'), '', 'filter_action', false);
        echo '</div>';
    }
}
