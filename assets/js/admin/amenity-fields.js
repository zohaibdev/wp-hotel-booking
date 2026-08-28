(function ($) {
    'use strict';

    // Pricing models whose quantity the guest chooses (mirrors
    // hb_quantity_driven_pricing_types() in PHP).
    var QUANTITY_TYPES = ['per_hour', 'per_drop'];

    function sync() {
        var $type = $('#hb_amenity_pricing_type');
        if (!$type.length) return;

        var pricingType = $type.val();
        var isFree = pricingType === 'free';
        var usesQuantity = QUANTITY_TYPES.indexOf(pricingType) !== -1;

        // A free amenity has no price to set.
        $('.hb-amenity-price-field').toggle(!isFree);

        // Hours/drops limits only apply to quantity-driven models.
        $('.hb-amenity-quantity-field').toggle(usesQuantity);

        // An included amenity is never opt-in, so the selectable choice is moot.
        var isIncluded = $('#hb_amenity_type').val() === 'included';
        $('.hb-amenity-selectable-field').toggle(!isIncluded);
    }

    $(function () {
        sync();
        $(document).on('change', '#hb_amenity_pricing_type, #hb_amenity_type', sync);

        // The add-term form is cleared and re-rendered over AJAX; re-apply after.
        $(document).ajaxComplete(function () {
            setTimeout(sync, 0);
        });

        // Keep max >= min without fighting the user mid-typing.
        $(document).on('change', '#hb_amenity_min_quantity, #hb_amenity_max_quantity', function () {
            var min = parseInt($('#hb_amenity_min_quantity').val(), 10) || 1;
            var max = parseInt($('#hb_amenity_max_quantity').val(), 10) || 1;
            if (max < min) {
                $('#hb_amenity_max_quantity').val(min);
            }
        });
    });
})(jQuery);
