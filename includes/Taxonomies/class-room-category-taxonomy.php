<?php

namespace HotelBooking\Taxonomies;

use HotelBooking\PostTypes\RoomPostType;

defined('ABSPATH') || exit;

final class RoomCategoryTaxonomy
{
    public const SLUG = 'hb_room_category';

    public function register(): void
    {
        add_action('init', [$this, 'register_taxonomy']);
    }

    public function register_taxonomy(): void
    {
        $labels = [
            'name'                       => _x('Room Categories', 'Taxonomy general name', 'hotel-booking'),
            'singular_name'              => _x('Room Category', 'Taxonomy singular name', 'hotel-booking'),
            'search_items'               => __('Search Categories', 'hotel-booking'),
            'all_items'                  => __('All Categories', 'hotel-booking'),
            'parent_item'                => __('Parent Category', 'hotel-booking'),
            'parent_item_colon'          => __('Parent Category:', 'hotel-booking'),
            'edit_item'                  => __('Edit Category', 'hotel-booking'),
            'update_item'                => __('Update Category', 'hotel-booking'),
            'add_new_item'               => __('Add New Category', 'hotel-booking'),
            'new_item_name'              => __('New Category Name', 'hotel-booking'),
            'menu_name'                  => __('Room Categories', 'hotel-booking'),
            'not_found'                  => __('No categories found.', 'hotel-booking'),
            'no_terms'                   => __('No categories', 'hotel-booking'),
            'back_to_items'              => __('&larr; Go to Categories', 'hotel-booking'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
            'query_var'         => false,
        ];

        register_taxonomy(self::SLUG, RoomPostType::SLUG, $args);
    }
}
