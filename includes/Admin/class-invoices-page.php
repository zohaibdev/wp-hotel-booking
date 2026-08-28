<?php

namespace HotelBooking\Admin;

defined('ABSPATH') || exit;

final class InvoicesPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'hotel-booking'));
        }

        $table = new InvoicesListTable();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Invoices', 'hotel-booking') . '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="hotel-booking-invoices">';
        $table->search_box(__('Search Invoices', 'hotel-booking'), 'hb-invoice-search');
        $table->display();
        echo '</form>';

        echo '</div>';
    }
}
