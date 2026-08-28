<?php

namespace HotelBooking\Admin;

defined('ABSPATH') || exit;

final class CustomersPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'hotel-booking'));
        }

        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));
        $id     = absint($_GET['id'] ?? 0);

        if ($action === 'view' && $id > 0) {
            (new CustomerDetailPage())->render($id);
            return;
        }

        $table = new CustomersListTable();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Customers', 'hotel-booking') . '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="hotel-booking-customers">';
        $table->search_box(__('Search Customers', 'hotel-booking'), 'hb-customer-search');
        $table->display();
        echo '</form>';

        echo '</div>';
    }
}
