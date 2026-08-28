<?php
defined('ABSPATH') || exit;

$hb_terms = (new \HotelBooking\Services\TermsService())->get_frontend_config();
$hb_terms_enabled = !empty($hb_terms['enabled']);
?>
<div class="hb-popup-overlay" id="hb-popup-overlay" role="dialog" aria-modal="true"
     aria-label="<?php esc_attr_e('Book a Room', 'hotel-booking'); ?>" style="display:none;">
    <div class="hb-popup">
        <!-- Compact header: title, close, progress -->
        <div class="hb-popup-header">
            <div class="hb-popup-titlebar">
                <h2 class="hb-popup-title" id="hb-popup-title"><?php esc_html_e('Book your stay', 'hotel-booking'); ?></h2>
                <button type="button" class="hb-popup-close" id="hb-popup-close"
                        aria-label="<?php esc_attr_e('Close', 'hotel-booking'); ?>">&times;</button>
            </div>

        <!-- Progress Steps -->
        <div class="hb-steps">
            <div class="hb-step active" data-step="1">
                <span class="hb-step-number">1</span>
                <span class="hb-step-label"><?php esc_html_e('Dates', 'hotel-booking'); ?></span>
            </div>
            <div class="hb-step-line"></div>
            <div class="hb-step" data-step="2">
                <span class="hb-step-number">2</span>
                <span class="hb-step-label"><?php esc_html_e('Room', 'hotel-booking'); ?></span>
            </div>
            <div class="hb-step-line"></div>
            <div class="hb-step" data-step="3">
                <span class="hb-step-number">3</span>
                <span class="hb-step-label"><?php esc_html_e('Amenities', 'hotel-booking'); ?></span>
            </div>
            <div class="hb-step-line"></div>
            <div class="hb-step" data-step="4">
                <span class="hb-step-number">4</span>
                <span class="hb-step-label"><?php esc_html_e('Details', 'hotel-booking'); ?></span>
            </div>
            <?php if ($hb_terms_enabled) : ?>
                <div class="hb-step-line"></div>
                <div class="hb-step" data-step="5">
                    <span class="hb-step-number">5</span>
                    <span class="hb-step-label"><?php esc_html_e('Terms', 'hotel-booking'); ?></span>
                </div>
            <?php endif; ?>
            <div class="hb-step-line"></div>
            <div class="hb-step" data-step="6">
                <span class="hb-step-number"><?php echo $hb_terms_enabled ? '6' : '5'; ?></span>
                <span class="hb-step-label"><?php esc_html_e('Payment', 'hotel-booking'); ?></span>
            </div>
            <div class="hb-step-line"></div>
            <div class="hb-step" data-step="7">
                <span class="hb-step-number"><?php echo $hb_terms_enabled ? '7' : '6'; ?></span>
                <span class="hb-step-label"><?php esc_html_e('Confirm', 'hotel-booking'); ?></span>
            </div>
        </div>
        </div><!-- /.hb-popup-header -->

        <!-- Step Panels -->
        <div class="hb-panels">
            <!-- Step 1: Dates & Guests -->
            <div class="hb-panel active" id="hb-panel-1">
                <h3 class="hb-panel-title"><?php esc_html_e('When are you staying?', 'hotel-booking'); ?></h3>

                <div class="hb-fieldset">
                    <div class="hb-form-grid">
                        <div class="hb-field">
                            <label for="hb-check-in"><?php esc_html_e('Check-in Date', 'hotel-booking'); ?></label>
                            <input type="date" id="hb-check-in" name="check_in" required>
                        </div>
                        <div class="hb-field" id="hb-check-in-time-wrap">
                            <label for="hb-check-in-time"><?php esc_html_e('Check-in Time', 'hotel-booking'); ?></label>
                            <input type="time" id="hb-check-in-time" name="check_in_time">
                        </div>
                        <div class="hb-field">
                            <label for="hb-check-out"><?php esc_html_e('Check-out Date', 'hotel-booking'); ?></label>
                            <input type="date" id="hb-check-out" name="check_out" required>
                        </div>
                        <div class="hb-field" id="hb-check-out-time-wrap">
                            <label for="hb-check-out-time"><?php esc_html_e('Check-out Time', 'hotel-booking'); ?></label>
                            <input type="time" id="hb-check-out-time" name="check_out_time">
                        </div>
                    </div>
                </div>

                <div class="hb-fieldset" id="hb-guests-fieldset">
                    <h3 class="hb-panel-title"><?php esc_html_e('Who is coming?', 'hotel-booking'); ?></h3>
                    <div class="hb-form-grid">
                        <div class="hb-field" id="hb-adults-wrap">
                            <label for="hb-adults"><?php esc_html_e('Adults', 'hotel-booking'); ?></label>
                            <div class="hb-number-control">
                                <button type="button" class="hb-num-btn hb-num-minus" data-target="hb-adults"
                                        aria-label="<?php esc_attr_e('Fewer adults', 'hotel-booking'); ?>">&minus;</button>
                                <input type="number" id="hb-adults" name="adults" value="1" min="1" max="10" readonly>
                                <button type="button" class="hb-num-btn hb-num-plus" data-target="hb-adults"
                                        aria-label="<?php esc_attr_e('More adults', 'hotel-booking'); ?>">+</button>
                            </div>
                        </div>
                        <div class="hb-field" id="hb-children-wrap">
                            <label for="hb-children"><?php esc_html_e('Children', 'hotel-booking'); ?></label>
                            <div class="hb-number-control">
                                <button type="button" class="hb-num-btn hb-num-minus" data-target="hb-children"
                                        aria-label="<?php esc_attr_e('Fewer children', 'hotel-booking'); ?>">&minus;</button>
                                <input type="number" id="hb-children" name="children" value="0" min="0" max="10" readonly>
                                <button type="button" class="hb-num-btn hb-num-plus" data-target="hb-children"
                                        aria-label="<?php esc_attr_e('More children', 'hotel-booking'); ?>">+</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hb-error" id="hb-error-1" role="alert"></div>
                <div class="hb-panel-footer">
                    <button type="button" class="hb-btn hb-btn-primary hb-next" data-step="1">
                        <?php esc_html_e('Check Availability', 'hotel-booking'); ?>
                        <span class="hb-spinner" style="display:none;"></span>
                    </button>
                </div>
            </div>

            <!-- Step 2: Room -->
            <div class="hb-panel" id="hb-panel-2">
                <h3 class="hb-panel-title"><?php esc_html_e('Your room', 'hotel-booking'); ?></h3>
                <div class="hb-room-card">
                    <div class="hb-room-image" id="hb-room-image"></div>
                    <div class="hb-room-info">
                        <h3 id="hb-room-title"></h3>
                        <div class="hb-room-categories" id="hb-room-categories"></div>
                        <div class="hb-room-description" id="hb-room-description"></div>
                        <div class="hb-room-details-grid">
                            <div class="hb-room-detail">
                                <span class="hb-detail-label"><?php esc_html_e('Capacity', 'hotel-booking'); ?></span>
                                <span class="hb-detail-value" id="hb-room-capacity"></span>
                            </div>
                            <div class="hb-room-detail">
                                <span class="hb-detail-label"><?php esc_html_e('Price / Night', 'hotel-booking'); ?></span>
                                <span class="hb-detail-value" id="hb-room-price"></span>
                            </div>
                            <div class="hb-room-detail">
                                <span class="hb-detail-label"><?php esc_html_e('Nights', 'hotel-booking'); ?></span>
                                <span class="hb-detail-value" id="hb-room-nights"></span>
                            </div>
                            <div class="hb-room-detail">
                                <span class="hb-detail-label"><?php esc_html_e('Room Subtotal', 'hotel-booking'); ?></span>
                                <span class="hb-detail-value hb-price" id="hb-room-subtotal"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hb-error" id="hb-error-2" role="alert"></div>
                <div class="hb-panel-footer">
                    <button type="button" class="hb-btn hb-btn-secondary hb-prev" data-step="2">
                        <?php esc_html_e('Back', 'hotel-booking'); ?>
                    </button>
                    <button type="button" class="hb-btn hb-btn-primary hb-next" data-step="2">
                        <?php esc_html_e('Continue', 'hotel-booking'); ?>
                    </button>
                </div>
            </div>

            <!-- Step 3: Amenities -->
            <div class="hb-panel" id="hb-panel-3">
                <h3 class="hb-panel-title"><?php esc_html_e('Add to your stay', 'hotel-booking'); ?></h3>

                <div class="hb-split">
                    <div class="hb-split-main">
                        <div class="hb-amenities-list" id="hb-amenities-list">
                            <div class="hb-loading"><?php esc_html_e('Loading amenities...', 'hotel-booking'); ?></div>
                        </div>
                    </div>

                    <aside class="hb-split-aside">
                        <div class="hb-booking-summary hb-summary-card" id="hb-summary-3">
                            <h4 class="hb-summary-title"><?php esc_html_e('Summary', 'hotel-booking'); ?></h4>
                            <div class="hb-summary-line">
                                <span><?php esc_html_e('Room', 'hotel-booking'); ?></span>
                                <span id="hb-summary-room-cost"></span>
                            </div>
                            <div class="hb-summary-line">
                                <span><?php esc_html_e('Add-ons', 'hotel-booking'); ?></span>
                                <span id="hb-summary-amenity-cost"></span>
                            </div>
                            <div class="hb-summary-line hb-summary-total">
                                <span><?php esc_html_e('Total', 'hotel-booking'); ?></span>
                                <span id="hb-summary-total-3"></span>
                            </div>
                        </div>
                    </aside>
                </div>

                <div class="hb-error" id="hb-error-3" role="alert"></div>
                <div class="hb-panel-footer">
                    <button type="button" class="hb-btn hb-btn-secondary hb-prev" data-step="3">
                        <?php esc_html_e('Back', 'hotel-booking'); ?>
                    </button>
                    <button type="button" class="hb-btn hb-btn-primary hb-next" data-step="3">
                        <?php esc_html_e('Continue', 'hotel-booking'); ?>
                    </button>
                </div>
            </div>

            <!-- Step 4: Customer Details (Phase 5) -->
            <div class="hb-panel" id="hb-panel-4">
                <h3 class="hb-panel-title"><?php esc_html_e('Your details', 'hotel-booking'); ?></h3>
                <div class="hb-customer-form" id="hb-customer-form"></div>
                <div class="hb-error" id="hb-error-4" role="alert"></div>
                <div class="hb-panel-footer">
                    <button type="button" class="hb-btn hb-btn-secondary hb-prev" data-step="4">
                        <?php esc_html_e('Back', 'hotel-booking'); ?>
                    </button>
                    <button type="button" class="hb-btn hb-btn-primary hb-next" data-step="4">
                        <?php esc_html_e('Continue to Payment', 'hotel-booking'); ?>
                        <span class="hb-spinner" style="display:none;"></span>
                    </button>
                </div>
            </div>

            <!-- Step 5: Terms & Conditions (optional, admin-controlled) -->
            <?php if ($hb_terms_enabled) : ?>
            <div class="hb-panel" id="hb-panel-5">
                <h3 class="hb-panel-title"><?php esc_html_e('Terms &amp; Conditions', 'hotel-booking'); ?></h3>

                <?php if (trim((string) $hb_terms['content']) !== '') : ?>
                    <div class="hb-terms-content" id="hb-terms-content" tabindex="0">
                        <?php echo wp_kses_post($hb_terms['content']); ?>
                    </div>
                <?php endif; ?>

                <label class="hb-terms-agree">
                    <input type="checkbox" id="hb-terms-checkbox">
                    <span><?php echo esc_html($hb_terms['checkbox_text']); ?></span>
                </label>

                <div class="hb-error" id="hb-error-5" role="alert"></div>
                <div class="hb-panel-footer">
                    <button type="button" class="hb-btn hb-btn-secondary hb-prev" data-step="5">
                        <?php esc_html_e('Back', 'hotel-booking'); ?>
                    </button>
                    <button type="button" class="hb-btn hb-btn-primary hb-next" data-step="5" id="hb-terms-continue" disabled>
                        <?php esc_html_e('Continue to Payment', 'hotel-booking'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Step 6: Payment -->
            <div class="hb-panel" id="hb-panel-6">
                <h3 class="hb-panel-title"><?php esc_html_e('Checkout', 'hotel-booking'); ?></h3>

                <div class="hb-split">
                    <div class="hb-split-main">
                        <div class="hb-payment-plans" id="hb-payment-plans"></div>
                        <div class="hb-payment-methods" id="hb-payment-methods"></div>
                        <!-- Stable Stripe mount container: never rebuilt while mounted. -->
                        <div class="hb-payment-form" id="hb-payment-form"></div>
                        <div class="hb-error" id="hb-error-6" role="alert"></div>
                    </div>

                    <aside class="hb-split-aside">
                        <div class="hb-booking-summary hb-summary-card" id="hb-summary-6"></div>
                    </aside>
                </div>
                <div class="hb-panel-footer">
                    <button type="button" class="hb-btn hb-btn-secondary hb-prev" data-step="6">
                        <?php esc_html_e('Back', 'hotel-booking'); ?>
                    </button>
                    <button type="button" class="hb-btn hb-btn-primary hb-next" data-step="6" id="hb-pay-btn">
                        <?php esc_html_e('Complete Booking', 'hotel-booking'); ?>
                        <span class="hb-spinner" style="display:none;"></span>
                    </button>
                </div>
            </div>

            <!-- Step 7: Confirmation -->
            <div class="hb-panel" id="hb-panel-7">
                <div class="hb-confirmation" id="hb-confirmation">
                    <div class="hb-confirm-icon">&#10003;</div>
                    <h2><?php esc_html_e('Booking Confirmed!', 'hotel-booking'); ?></h2>
                    <p class="hb-confirm-message"><?php esc_html_e('Your booking has been successfully confirmed.', 'hotel-booking'); ?></p>
                    <p class="hb-confirm-number" id="hb-confirm-number"></p>
                    <div class="hb-confirm-details" id="hb-confirm-details"></div>

                    <div class="hb-confirm-balance" id="hb-confirm-balance" style="display:none;">
                        <div class="hb-balance-row">
                            <span><?php esc_html_e('Total Booking Amount', 'hotel-booking'); ?></span>
                            <span id="hb-balance-total"></span>
                        </div>
                        <div class="hb-balance-row hb-balance-paid">
                            <span><?php esc_html_e('Paid Now', 'hotel-booking'); ?></span>
                            <span id="hb-balance-paid"></span>
                        </div>
                        <div class="hb-balance-row hb-balance-due">
                            <span><?php esc_html_e('Remaining Balance', 'hotel-booking'); ?></span>
                            <span id="hb-balance-remaining"></span>
                        </div>
                        <div class="hb-balance-row">
                            <span><?php esc_html_e('Balance Due By', 'hotel-booking'); ?></span>
                            <span id="hb-balance-date"></span>
                        </div>
                    </div>

                    <div class="hb-confirm-txn" id="hb-confirm-txn" style="display:none;">
                        <span class="hb-confirm-txn-label"><?php esc_html_e('Transaction ID', 'hotel-booking'); ?></span>
                        <span class="hb-confirm-txn-row">
                            <code class="hb-confirm-txn-id" id="hb-confirm-txn-id"></code>
                            <button type="button" class="hb-copy-btn" id="hb-copy-txn"
                                    data-copy-label="<?php esc_attr_e('Copy', 'hotel-booking'); ?>"
                                    data-copied-label="<?php esc_attr_e('Copied', 'hotel-booking'); ?>"
                                    aria-label="<?php esc_attr_e('Copy transaction ID', 'hotel-booking'); ?>">
                                <?php esc_html_e('Copy', 'hotel-booking'); ?>
                            </button>
                        </span>
                    </div>

                    <p class="hb-confirm-email" id="hb-confirm-email"></p>
                </div>
                <div class="hb-panel-footer">
                    <button type="button" class="hb-btn hb-btn-primary" id="hb-close-confirm">
                        <?php esc_html_e('Close', 'hotel-booking'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
