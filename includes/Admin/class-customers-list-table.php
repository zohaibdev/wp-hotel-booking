<?php

namespace HotelBooking\Admin;

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class CustomersListTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'customer',
            'plural'   => 'customers',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'name'       => __('Name', 'hotel-booking'),
            'email'      => __('Email', 'hotel-booking'),
            'phone'      => __('Phone', 'hotel-booking'),
            'bookings'   => __('Bookings', 'hotel-booking'),
            'created_at' => __('Registered', 'hotel-booking'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'name'       => ['last_name', false],
            'email'      => ['email', false],
            'created_at' => ['created_at', true],
        ];
    }

    public function prepare_items(): void
    {
        global $wpdb;

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby_map = [
            'last_name'  => 'c.last_name',
            'email'      => 'c.email',
            'created_at' => 'c.created_at',
        ];

        $raw_orderby = sanitize_text_field(wp_unslash($_GET['orderby'] ?? 'created_at'));
        $orderby     = $orderby_map[$raw_orderby] ?? 'c.created_at';

        $order = strtoupper(sanitize_text_field(wp_unslash($_GET['order'] ?? 'DESC')));
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $table_cust = $wpdb->prefix . 'hb_customers';
        $table_book = $wpdb->prefix . 'hb_bookings';

        $where = '1=1';
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                ' AND (c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR CONCAT(c.first_name, " ", c.last_name) LIKE %s)',
                $like, $like, $like, $like
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_items = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_cust} c WHERE {$where}"
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, COUNT(b.id) AS booking_count
                 FROM {$table_cust} c
                 LEFT JOIN {$table_book} b ON b.customer_id = c.id
                 WHERE {$where}
                 GROUP BY c.id
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

    protected function column_name($item): string
    {
        $name = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
        if ($name === '') {
            $name = __('(no name)', 'hotel-booking');
        }
        $url = admin_url('admin.php?page=hotel-booking-customers&action=view&id=' . (int) $item->id);
        return '<a href="' . esc_url($url) . '"><strong>' . esc_html($name) . '</strong></a>';
    }

    protected function column_email($item): string
    {
        return esc_html($item->email ?? '');
    }

    protected function column_phone($item): string
    {
        return esc_html($item->phone ?? '&mdash;');
    }

    protected function column_bookings($item): string
    {
        $count = (int) ($item->booking_count ?? 0);
        if ($count === 0) {
            return '0';
        }
        $url = admin_url('admin.php?page=hotel-booking-customers&action=view&id=' . (int) $item->id);
        return '<a href="' . esc_url($url) . '">' . esc_html((string) $count) . '</a>';
    }

    protected function column_created_at($item): string
    {
        if (empty($item->created_at)) {
            return '&mdash;';
        }
        return esc_html(wp_date(get_option('date_format'), strtotime($item->created_at)));
    }
}
