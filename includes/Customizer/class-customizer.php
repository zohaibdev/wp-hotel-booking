<?php

namespace HotelBooking\Customizer;

defined('ABSPATH') || exit;

final class Customizer
{
    private const PANEL_ID = 'hotel_booking';

    public function register(\WP_Customize_Manager $wp_customize): void
    {
        $wp_customize->add_panel(self::PANEL_ID, [
            'title'    => __('Hotel Booking', 'hotel-booking'),
            'priority' => 150,
        ]);

        $this->add_branding_section($wp_customize);
        $this->add_colors_section($wp_customize);
        $this->add_popup_section($wp_customize);
        $this->add_typography_section($wp_customize);
        $this->add_button_section($wp_customize);
    }

    public function inject_css(): void
    {
        $vars = [];

        $map = [
            'hb_color_primary'       => '--hb-primary',
            'hb_color_primary_hover' => '--hb-primary-hover',
            'hb_color_primary_light' => '--hb-primary-light',
            'hb_color_text'          => '--hb-text',
            'hb_color_text_light'    => '--hb-text-light',
            'hb_color_bg'            => '--hb-bg',
            'hb_color_bg_alt'        => '--hb-bg-alt',
            'hb_color_border'        => '--hb-border',
            'hb_color_success'       => '--hb-success',
            'hb_color_danger'        => '--hb-danger',
            'hb_popup_width'         => '--hb-popup-width',
            'hb_popup_radius'        => '--hb-radius',
            'hb_popup_overlay_bg'    => '--hb-overlay-bg',
            'hb_font_family'         => '--hb-font-family',
            'hb_font_heading'        => '--hb-font-heading',
            'hb_font_body'           => '--hb-font-body',
            'hb_btn_text_color'      => '--hb-btn-text',
        ];

        foreach ($map as $setting => $css_var) {
            $value = get_theme_mod($setting, '');
            if ($value === '') {
                continue;
            }
            $vars[] = sanitize_text_field($css_var) . ':' . sanitize_text_field($value);
        }

        if (empty($vars)) {
            return;
        }

        $css = ':root{' . implode(';', $vars) . '}';
        echo '<style id="hb-customizer-css">' . wp_strip_all_tags($css) . '</style>' . "\n";
    }

    private function add_branding_section(\WP_Customize_Manager $wp_customize): void
    {
        $wp_customize->add_section('hb_branding', [
            'title' => __('Branding', 'hotel-booking'),
            'panel' => self::PANEL_ID,
        ]);

        $wp_customize->add_setting('hb_branding_logo', [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'postMessage',
        ]);

        $wp_customize->add_control(new \WP_Customize_Image_Control($wp_customize, 'hb_branding_logo', [
            'label'   => __('Booking Popup Logo', 'hotel-booking'),
            'section' => 'hb_branding',
        ]));
    }

    private function add_colors_section(\WP_Customize_Manager $wp_customize): void
    {
        $wp_customize->add_section('hb_colors', [
            'title' => __('Colors', 'hotel-booking'),
            'panel' => self::PANEL_ID,
        ]);

        $colors = [
            'hb_color_primary'       => ['label' => __('Primary Color', 'hotel-booking'),       'default' => '#2563eb'],
            'hb_color_primary_hover' => ['label' => __('Primary Hover', 'hotel-booking'),       'default' => '#1d4ed8'],
            'hb_color_primary_light' => ['label' => __('Primary Light', 'hotel-booking'),       'default' => '#dbeafe'],
            'hb_color_text'          => ['label' => __('Text Color', 'hotel-booking'),          'default' => '#1e293b'],
            'hb_color_text_light'    => ['label' => __('Text Light', 'hotel-booking'),          'default' => '#64748b'],
            'hb_color_bg'            => ['label' => __('Background', 'hotel-booking'),          'default' => '#ffffff'],
            'hb_color_bg_alt'        => ['label' => __('Background Alt', 'hotel-booking'),      'default' => '#f8fafc'],
            'hb_color_border'        => ['label' => __('Border Color', 'hotel-booking'),        'default' => '#e2e8f0'],
            'hb_color_success'       => ['label' => __('Success Color', 'hotel-booking'),       'default' => '#16a34a'],
            'hb_color_danger'        => ['label' => __('Danger Color', 'hotel-booking'),        'default' => '#dc2626'],
        ];

        foreach ($colors as $id => $args) {
            $wp_customize->add_setting($id, [
                'default'           => $args['default'],
                'sanitize_callback' => 'sanitize_hex_color',
                'transport'         => 'postMessage',
            ]);

            $wp_customize->add_control(new \WP_Customize_Color_Control($wp_customize, $id, [
                'label'   => $args['label'],
                'section' => 'hb_colors',
            ]));
        }
    }

    private function add_popup_section(\WP_Customize_Manager $wp_customize): void
    {
        $wp_customize->add_section('hb_popup', [
            'title' => __('Popup', 'hotel-booking'),
            'panel' => self::PANEL_ID,
        ]);

        $wp_customize->add_setting('hb_popup_width', [
            'default'           => '640px',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ]);

        $wp_customize->add_control('hb_popup_width', [
            'label'       => __('Popup Max Width', 'hotel-booking'),
            'description' => __('e.g. 640px, 720px', 'hotel-booking'),
            'section'     => 'hb_popup',
            'type'        => 'text',
        ]);

        $wp_customize->add_setting('hb_popup_radius', [
            'default'           => '12px',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ]);

        $wp_customize->add_control('hb_popup_radius', [
            'label'       => __('Popup Border Radius', 'hotel-booking'),
            'description' => __('e.g. 12px, 8px, 0px', 'hotel-booking'),
            'section'     => 'hb_popup',
            'type'        => 'text',
        ]);

        $wp_customize->add_setting('hb_popup_overlay_bg', [
            'default'           => 'rgba(0, 0, 0, 0.5)',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ]);

        $wp_customize->add_control('hb_popup_overlay_bg', [
            'label'       => __('Overlay Background', 'hotel-booking'),
            'description' => __('RGBA value, e.g. rgba(0, 0, 0, 0.5)', 'hotel-booking'),
            'section'     => 'hb_popup',
            'type'        => 'text',
        ]);
    }

    private function add_typography_section(\WP_Customize_Manager $wp_customize): void
    {
        $wp_customize->add_section('hb_typography', [
            'title' => __('Typography', 'hotel-booking'),
            'panel' => self::PANEL_ID,
        ]);

        $wp_customize->add_setting('hb_font_family', [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ]);

        $wp_customize->add_control('hb_font_family', [
            'label'       => __('Font Family', 'hotel-booking'),
            'description' => __('Leave empty to use system fonts', 'hotel-booking'),
            'section'     => 'hb_typography',
            'type'        => 'text',
        ]);

        $wp_customize->add_setting('hb_font_heading', [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ]);

        $wp_customize->add_control('hb_font_heading', [
            'label'       => __('Heading Font Size', 'hotel-booking'),
            'description' => __('e.g. 1.25rem, 20px', 'hotel-booking'),
            'section'     => 'hb_typography',
            'type'        => 'text',
        ]);

        $wp_customize->add_setting('hb_font_body', [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ]);

        $wp_customize->add_control('hb_font_body', [
            'label'       => __('Body Font Size', 'hotel-booking'),
            'description' => __('e.g. 0.9375rem, 15px', 'hotel-booking'),
            'section'     => 'hb_typography',
            'type'        => 'text',
        ]);
    }

    private function add_button_section(\WP_Customize_Manager $wp_customize): void
    {
        $wp_customize->add_section('hb_button', [
            'title' => __('Button', 'hotel-booking'),
            'panel' => self::PANEL_ID,
        ]);

        $wp_customize->add_setting('hb_btn_text_color', [
            'default'           => '#ffffff',
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'postMessage',
        ]);

        $wp_customize->add_control(new \WP_Customize_Color_Control($wp_customize, 'hb_btn_text_color', [
            'label'   => __('Button Text Color', 'hotel-booking'),
            'section' => 'hb_button',
        ]));
    }
}
