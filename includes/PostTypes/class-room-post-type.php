<?php

namespace HotelBooking\PostTypes;

defined('ABSPATH') || exit;

final class RoomPostType
{
    public const SLUG = 'hb_room';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        $labels = [
            'name'                  => _x('Rooms', 'Post type general name', 'hotel-booking'),
            'singular_name'         => _x('Room', 'Post type singular name', 'hotel-booking'),
            'menu_name'             => _x('Rooms', 'Admin menu text', 'hotel-booking'),
            'add_new'               => __('Add New Room', 'hotel-booking'),
            'add_new_item'          => __('Add New Room', 'hotel-booking'),
            'new_item'              => __('New Room', 'hotel-booking'),
            'edit_item'             => __('Edit Room', 'hotel-booking'),
            'view_item'             => __('View Room', 'hotel-booking'),
            'all_items'             => __('Rooms', 'hotel-booking'),
            'search_items'          => __('Search Rooms', 'hotel-booking'),
            'not_found'             => __('No rooms found.', 'hotel-booking'),
            'not_found_in_trash'    => __('No rooms found in Trash.', 'hotel-booking'),
            'featured_image'        => __('Room Featured Image', 'hotel-booking'),
            'set_featured_image'    => __('Set room featured image', 'hotel-booking'),
            'remove_featured_image' => __('Remove room featured image', 'hotel-booking'),
            'use_featured_image'    => __('Use as room featured image', 'hotel-booking'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_rest'        => true,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail'],
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'delete_with_user'    => false,
        ];

        register_post_type(self::SLUG, $args);
    }
}
