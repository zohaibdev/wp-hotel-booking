<?php

namespace HotelBooking\Taxonomies;

use HotelBooking\PostTypes\RoomPostType;

defined('ABSPATH') || exit;

final class AmenityTaxonomy
{
    public const SLUG = 'hb_amenity';

    public function register(): void
    {
        add_action('init', [$this, 'register_taxonomy']);
    }

    public function register_taxonomy(): void
    {
        $labels = [
            'name'                       => _x('Amenities', 'Taxonomy general name', 'hotel-booking'),
            'singular_name'              => _x('Amenity', 'Taxonomy singular name', 'hotel-booking'),
            'search_items'               => __('Search Amenities', 'hotel-booking'),
            'all_items'                  => __('All Amenities', 'hotel-booking'),
            'edit_item'                  => __('Edit Amenity', 'hotel-booking'),
            'update_item'                => __('Update Amenity', 'hotel-booking'),
            'add_new_item'               => __('Add New Amenity', 'hotel-booking'),
            'new_item_name'              => __('New Amenity Name', 'hotel-booking'),
            'menu_name'                  => __('Amenities', 'hotel-booking'),
            'not_found'                  => __('No amenities found.', 'hotel-booking'),
            'no_terms'                   => __('No amenities', 'hotel-booking'),
            'back_to_items'              => __('&larr; Go to Amenities', 'hotel-booking'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
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
