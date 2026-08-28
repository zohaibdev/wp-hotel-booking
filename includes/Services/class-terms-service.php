<?php

namespace HotelBooking\Services;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;

/**
 * Terms & Conditions step configuration and acceptance recording.
 *
 * Nothing here is hardcoded: whether the step runs, the checkbox wording and the
 * body content all come from settings.
 */
final class TermsService
{
    public function is_enabled(): bool
    {
        return !empty(hb_get_option('hb_booking', 'enable_terms_step', false));
    }

    public function get_checkbox_text(): string
    {
        $text = trim((string) hb_get_option('hb_booking', 'terms_checkbox_text', ''));

        return $text !== ''
            ? $text
            : __('I agree to the Terms & Conditions.', 'hotel-booking');
    }

    /** Raw stored content; callers are responsible for escaping on output. */
    public function get_content(): string
    {
        return (string) hb_get_option('hb_booking', 'terms_content', '');
    }

    /** Content rendered for the guest-facing step. */
    public function get_rendered_content(): string
    {
        $content = $this->get_content();

        if (trim($content) === '') {
            return '';
        }

        return wp_kses_post(wpautop($content));
    }

    /**
     * Short fingerprint of the exact wording in force, stored on the booking so a
     * later edit to the terms cannot rewrite what a guest actually agreed to.
     */
    public function get_version_hash(): string
    {
        $material = $this->get_checkbox_text() . '|' . $this->get_content();

        return substr(hash('sha256', $material), 0, 32);
    }

    /**
     * Server-side gate. Acceptance is only meaningful while the step is enabled;
     * when it is off, a booking is allowed through without it.
     *
     * @return array{valid:bool, error:string}
     */
    public function validate_acceptance(bool $accepted): array
    {
        if (!$this->is_enabled()) {
            return ['valid' => true, 'error' => ''];
        }

        if (!$accepted) {
            return [
                'valid' => false,
                'error' => __('You must accept the Terms & Conditions to continue.', 'hotel-booking'),
            ];
        }

        return ['valid' => true, 'error' => ''];
    }

    /** Values to persist alongside the booking. */
    public function get_acceptance_record(bool $accepted): array
    {
        if (!$this->is_enabled() || !$accepted) {
            return [
                'terms_accepted'    => 0,
                'terms_accepted_at' => null,
                'terms_version'     => '',
            ];
        }

        return [
            'terms_accepted'    => 1,
            'terms_accepted_at' => current_time('mysql', true),
            'terms_version'     => $this->get_version_hash(),
        ];
    }

    /** Config handed to the frontend for the Terms step. */
    public function get_frontend_config(): array
    {
        return [
            'enabled'       => $this->is_enabled(),
            'checkbox_text' => $this->get_checkbox_text(),
            'content'       => $this->get_rendered_content(),
        ];
    }
}
