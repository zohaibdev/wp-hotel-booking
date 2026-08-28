<?php

namespace HotelBooking\Services;

defined('ABSPATH') || exit;

use function HotelBooking\hb_get_option;
use function HotelBooking\hb_round_currency;
use function HotelBooking\hb_parse_date;

/**
 * Resolves how much a guest pays now versus later.
 *
 * Every figure here is derived from the booking total and the stored admin
 * settings; nothing is ever taken from the request. The frontend can only choose
 * *which* plan applies, never the percentage or the amounts.
 */
final class PaymentPlanService
{
    public const PLAN_FULL    = 'full_payment';
    public const PLAN_PARTIAL = 'partial_payment';

    public function is_partial_enabled(): bool
    {
        return !empty(hb_get_option('hb_payments', 'enable_partial_payment', false));
    }

    /** Configured deposit percentage, clamped to a sane 1-99. */
    public function get_initial_percentage(): float
    {
        $pct = (float) hb_get_option('hb_payments', 'initial_payment_percentage', 50);
        return max(1.0, min(99.0, $pct));
    }

    public function get_balance_due_days(): int
    {
        return max(0, (int) hb_get_option('hb_payments', 'balance_due_days_before_check_in', 30));
    }

    /** Only 'partial_payment' when the admin has actually enabled it. */
    public function normalize_plan(string $requested): string
    {
        return ($requested === self::PLAN_PARTIAL && $this->is_partial_enabled())
            ? self::PLAN_PARTIAL
            : self::PLAN_FULL;
    }

    /**
     * Splits a booking total into the amount charged now and the balance.
     *
     * @return array{
     *     payment_plan:string, booking_total:float, initial_percentage:float,
     *     initial_amount:float, remaining_balance:float, balance_due_date:?string,
     *     is_partial:bool
     * }
     */
    public function resolve(float $booking_total, string $requested_plan, string $check_in, string $currency = 'USD'): array
    {
        $plan = $this->normalize_plan($requested_plan);
        $booking_total = hb_round_currency(max(0.0, $booking_total), $currency);

        if ($plan !== self::PLAN_PARTIAL || $booking_total <= 0) {
            return [
                'payment_plan'       => self::PLAN_FULL,
                'booking_total'      => $booking_total,
                'initial_percentage' => 100.0,
                'initial_amount'     => $booking_total,
                'remaining_balance'  => 0.0,
                'balance_due_date'   => null,
                'is_partial'         => false,
            ];
        }

        $percentage = $this->get_initial_percentage();

        $initial = hb_round_currency($booking_total * $percentage / 100, $currency);

        // Never charge more than the booking, and always leave something owing --
        // a rounding edge on a tiny total could otherwise consume the whole amount
        // and produce a "partial" payment with a zero balance.
        $initial = min($initial, $booking_total);
        $balance = hb_round_currency($booking_total - $initial, $currency);

        if ($balance <= 0) {
            return [
                'payment_plan'       => self::PLAN_FULL,
                'booking_total'      => $booking_total,
                'initial_percentage' => 100.0,
                'initial_amount'     => $booking_total,
                'remaining_balance'  => 0.0,
                'balance_due_date'   => null,
                'is_partial'         => false,
            ];
        }

        return [
            'payment_plan'       => self::PLAN_PARTIAL,
            'booking_total'      => $booking_total,
            'initial_percentage' => $percentage,
            'initial_amount'     => $initial,
            'remaining_balance'  => $balance,
            'balance_due_date'   => $this->calculate_balance_due_date($check_in),
            'is_partial'         => true,
        ];
    }

    /**
     * check_in minus the configured lead time, floored at today so a booking made
     * inside the window does not produce a due date in the past.
     *
     * Stored on the booking at creation time: later changes to the setting must
     * not move the date a guest was already told.
     */
    public function calculate_balance_due_date(string $check_in): ?string
    {
        $check_in_dt = hb_parse_date(substr($check_in, 0, 10));
        if (!$check_in_dt) {
            return null;
        }

        $due = $check_in_dt->modify('-' . $this->get_balance_due_days() . ' days');
        $today = new \DateTimeImmutable('today', wp_timezone());

        if ($due < $today) {
            $due = $today;
        }

        return $due->format('Y-m-d H:i:s');
    }

    /**
     * Options offered on the payment step, with server-calculated amounts so the
     * UI never has to derive them.
     */
    public function get_options(float $booking_total, string $check_in, string $currency = 'USD'): array
    {
        $options = [[
            'value'       => self::PLAN_FULL,
            'amount'      => hb_round_currency($booking_total, $currency),
            'balance'     => 0.0,
            'due_date'    => null,
            'percentage'  => 100.0,
        ]];

        if ($this->is_partial_enabled()) {
            $partial = $this->resolve($booking_total, self::PLAN_PARTIAL, $check_in, $currency);

            if ($partial['is_partial']) {
                $options[] = [
                    'value'      => self::PLAN_PARTIAL,
                    'amount'     => $partial['initial_amount'],
                    'balance'    => $partial['remaining_balance'],
                    'due_date'   => $partial['balance_due_date'],
                    'percentage' => $partial['initial_percentage'],
                ];
            }
        }

        return $options;
    }
}
