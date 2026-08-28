(function () {
    'use strict';

    // Named so the Terms step can be inserted without renumbering call sites.
    var STEP = {
        DATES: 1,
        ROOM: 2,
        AMENITIES: 3,
        DETAILS: 4,
        TERMS: 5,
        PAYMENT: 6,
        CONFIRM: 7,
    };

    function termsEnabled() {
        return !!(window.hbBooking && window.hbBooking.terms_enabled);
    }

    // Walks past the Terms step when the admin has it switched off.
    function resolveStep(step, direction) {
        if (step === STEP.TERMS && !termsEnabled()) {
            return direction < 0 ? STEP.DETAILS : STEP.PAYMENT;
        }
        return step;
    }

    var state = {
        roomId: 0,
        currentStep: 1,
        sessionId: '',
        checkIn: '',
        checkOut: '',
        checkInTime: '',
        checkOutTime: '',
        adults: 1,
        children: 0,
        nights: 0,
        room: null,
        // Which room id state.room actually holds, so a page with several Book Now
        // buttons can never render one room's cache under another room's id.
        roomLoadedFor: 0,
        roomSubtotal: 0,
        amenities: [],
        amenitiesLoadedFor: 0,
        amenitiesLoadedAdults: 0,
        selectedAmenities: [],
        // amenity id -> chosen quantity, for the quantity-driven pricing models.
        amenityQuantities: {},
        amenitiesSubtotal: 0,
        totalAmount: 0,
        customerData: {},
        paymentMethod: '',
        termsAccepted: false,
        paymentPlan: 'full_payment',
        // Which first-step fields this room collects; resolved from the server.
        fieldConfig: { checkin_time: true, checkout_time: true, adults: true, children: true },
    };

    var overlay, popup;

    function init() {
        overlay = document.getElementById('hb-popup-overlay');
        popup = overlay ? overlay.querySelector('.hb-popup') : null;
        if (!overlay || !popup) return;

        bindButtons();
        bindNumberControls();
        bindClose();
        bindTermsCheckbox();
        setDefaultTimes();
        setMinDates();
    }

    function bindButtons() {
        document.querySelectorAll('.hb-book-now-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.roomId = parseInt(this.getAttribute('data-room-id'), 10);
                state.sessionId = HBUtils.generateSessionId();
                openPopup();
            });
        });

        document.querySelectorAll('.hb-next').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var step = parseInt(this.getAttribute('data-step'), 10);
                handleNext(step, this);
            });
        });

        document.querySelectorAll('.hb-prev').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var step = parseInt(this.getAttribute('data-step'), 10);
                goToStep(step - 1);
            });
        });
    }

    function bindNumberControls() {
        document.querySelectorAll('.hb-num-minus, .hb-num-plus').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = this.getAttribute('data-target');
                var input = document.getElementById(targetId);
                if (!input) return;

                var val = parseInt(input.value, 10) || 0;
                var min = parseInt(input.min, 10) || 0;
                var max = parseInt(input.max, 10) || 99;

                if (this.classList.contains('hb-num-minus')) {
                    val = Math.max(min, val - 1);
                } else {
                    val = Math.min(max, val + 1);
                }
                input.value = val;
            });
        });
    }

    function bindClose() {
        document.getElementById('hb-popup-close').addEventListener('click', closePopup);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closePopup();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display !== 'none') {
                closePopup();
            }
        });

        var closeConfirm = document.getElementById('hb-close-confirm');
        if (closeConfirm) {
            closeConfirm.addEventListener('click', closePopup);
        }
    }

    function setDefaultTimes() {
        var settings = window.hbBooking || {};
        var ciTime = document.getElementById('hb-check-in-time');
        var coTime = document.getElementById('hb-check-out-time');

        if (ciTime && settings.default_check_in_time) {
            ciTime.value = settings.default_check_in_time;
        }
        if (coTime && settings.default_check_out_time) {
            coTime.value = settings.default_check_out_time;
        }

        // Final visibility is decided by applyFieldVisibility() once the room's
        // configuration arrives; hide up front so the fields never flash.
        toggleField('hb-check-in-time-wrap', !!settings.allow_custom_check_in_time);
        toggleField('hb-check-out-time-wrap', !!settings.allow_custom_check_out_time);
    }

    function setMinDates() {
        var today = new Date().toISOString().split('T')[0];
        var ciInput = document.getElementById('hb-check-in');
        var coInput = document.getElementById('hb-check-out');

        if (ciInput) ciInput.min = today;
        if (coInput) coInput.min = today;

        if (ciInput) {
            ciInput.addEventListener('change', function () {
                if (coInput) {
                    var nextDay = new Date(this.value);
                    nextDay.setDate(nextDay.getDate() + 1);
                    coInput.min = nextDay.toISOString().split('T')[0];
                    if (coInput.value && coInput.value <= this.value) {
                        coInput.value = coInput.min;
                    }
                }
            });
        }
    }

    function openPopup() {
        resetState();
        overlay.style.display = 'flex';
        requestAnimationFrame(function () {
            overlay.classList.add('hb-visible');
        });
        document.body.style.overflow = 'hidden';
        loadRoomLimits();
    }

    function closePopup() {
        overlay.classList.remove('hb-visible');
        setTimeout(function () {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }

    function resetState() {
        state.currentStep = 1;
        state.room = null;
        state.roomLoadedFor = 0;
        state.nights = 0;
        state.roomSubtotal = 0;
        state.amenities = [];
        state.amenitiesLoadedFor = 0;
        state.amenitiesLoadedAdults = 0;
        state.selectedAmenities = [];
        state.amenityQuantities = {};
        state.amenitiesSubtotal = 0;
        state.totalAmount = 0;
        state.customerData = {};
        state.paymentMethod = '';
        state.termsAccepted = false;
        state.paymentPlan = 'full_payment';

        var termsBox = document.getElementById('hb-terms-checkbox');
        if (termsBox) termsBox.checked = false;
        var termsBtn = document.getElementById('hb-terms-continue');
        if (termsBtn) termsBtn.disabled = true;

        goToStep(STEP.DATES);

        for (var i = 1; i <= 7; i++) {
            HBUtils.clearError(i);
        }
    }

    // Resolves with the room, fetching only when it is not already cached for
    // the current room id. Step 1 and Step 2 previously fetched it twice and
    // raced each other.
    function ensureRoom() {
        if (state.room && state.roomLoadedFor === state.roomId) {
            return Promise.resolve(state.room);
        }

        var requestedFor = state.roomId;

        return HBUtils.apiRequest('rooms/' + requestedFor, 'GET').then(function (room) {
            // Discard a late response for a room the user has since navigated away from.
            if (requestedFor !== state.roomId) {
                return state.room;
            }
            state.room = room;
            state.roomLoadedFor = requestedFor;
            return room;
        });
    }

    // Shows exactly the fields this room collects. Visibility is decided by the
    // server; hiding here is presentation only -- the backend independently
    // discards values for fields it has switched off.
    function applyFieldVisibility(room) {
        var config = (room && room.field_config) || {};
        var fields = config.fields || {};
        var defaults = config.defaults || {};

        state.fieldConfig = {
            checkin_time: fields.checkin_time !== false,
            checkout_time: fields.checkout_time !== false,
            adults: fields.adults !== false,
            children: fields.children !== false,
        };

        toggleField('hb-check-in-time-wrap', state.fieldConfig.checkin_time
            && !!(window.hbBooking && window.hbBooking.allow_custom_check_in_time));
        toggleField('hb-check-out-time-wrap', state.fieldConfig.checkout_time
            && !!(window.hbBooking && window.hbBooking.allow_custom_check_out_time));
        toggleField('hb-adults-wrap', state.fieldConfig.adults);
        toggleField('hb-children-wrap', state.fieldConfig.children);

        // With neither guest field collected there is nothing to ask, so the whole
        // section goes -- heading, grid and spacing included. Hiding only the
        // inputs would leave an orphaned "Who is coming?" above empty space.
        toggleField('hb-guests-fieldset', state.fieldConfig.adults || state.fieldConfig.children);

        // Mirror the server's fallbacks into the inputs so the values submitted
        // match what the booking will actually use.
        if (!state.fieldConfig.adults && defaults.adults) {
            setInputValue('hb-adults', defaults.adults);
        }
        if (!state.fieldConfig.children) {
            setInputValue('hb-children', defaults.children || 0);
        }
        if (!state.fieldConfig.checkin_time && defaults.check_in_time) {
            setInputValue('hb-check-in-time', defaults.check_in_time);
        }
        if (!state.fieldConfig.checkout_time && defaults.check_out_time) {
            setInputValue('hb-check-out-time', defaults.check_out_time);
        }
    }

    function toggleField(id, visible) {
        var el = document.getElementById(id);
        if (el) el.hidden = !visible;
    }

    function setInputValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function loadRoomLimits() {
        ensureRoom().then(function (room) {
            if (!room) return;

            applyFieldVisibility(room);

            var adultsInput = document.getElementById('hb-adults');
            var childrenInput = document.getElementById('hb-children');

            if (adultsInput) {
                adultsInput.max = room.max_adults;
                if (parseInt(adultsInput.value, 10) > room.max_adults) {
                    adultsInput.value = room.max_adults;
                }
            }
            if (childrenInput) {
                childrenInput.max = room.max_children;
                if (parseInt(childrenInput.value, 10) > room.max_children) {
                    childrenInput.value = room.max_children;
                }
            }
        }).catch(function () {
            // Non-fatal: the availability check re-validates capacity server-side.
        });
    }

    function handleNext(step, btn) {
        HBUtils.clearError(step);

        switch (step) {
            case STEP.DATES:     handleStep1(btn); break;
            case STEP.ROOM:      handleStep2(btn); break;
            case STEP.AMENITIES: handleStep3(btn); break;
            case STEP.DETAILS:   handleStep4(btn); break;
            case STEP.TERMS:     handleTermsStep(btn); break;
            case STEP.PAYMENT:   handlePaymentStep(btn); break;
        }
    }

    function handleStep1(btn) {
        var checkIn = document.getElementById('hb-check-in').value;
        var checkOut = document.getElementById('hb-check-out').value;
        var adults = parseInt(document.getElementById('hb-adults').value, 10);
        var children = parseInt(document.getElementById('hb-children').value, 10);

        if (!checkIn || !checkOut) {
            HBUtils.showError(1, 'Please select check-in and check-out dates.');
            return;
        }

        if (checkOut <= checkIn) {
            HBUtils.showError(1, 'Check-out date must be after check-in date.');
            return;
        }

        state.checkIn = checkIn;
        state.checkOut = checkOut;
        // A hidden field contributes nothing; the server fills it in.
        state.checkInTime = state.fieldConfig.checkin_time
            ? document.getElementById('hb-check-in-time').value : '';
        state.checkOutTime = state.fieldConfig.checkout_time
            ? document.getElementById('hb-check-out-time').value : '';
        state.adults = state.fieldConfig.adults ? adults : 0;
        state.children = state.fieldConfig.children ? children : 0;

        HBUtils.setLoading(btn, true);

        HBUtils.apiRequest('availability/check', 'POST', {
            room_id: state.roomId,
            check_in: state.checkIn,
            check_out: state.checkOut,
            adults: state.adults,
            children: state.children,
            session_id: state.sessionId,
        }).then(function (data) {
            state.nights = parseInt(data.nights, 10) || 0;
            state.roomSubtotal = parseFloat(data.room_subtotal) || 0;

            // Adopt the counts the server actually booked with. When a guest field
            // is hidden this is where the configured fallback arrives, and it is
            // what per-person amenity defaults must be based on.
            if (data.adults) state.adults = parseInt(data.adults, 10) || state.adults;
            if (data.children !== undefined) state.children = parseInt(data.children, 10) || 0;

            // Only advance once the room data is in hand, otherwise Step 2 paints
            // with empty title/price/nights and fills in later (or never, if the
            // request fails).
            return ensureRoom();
        }).then(function (room) {
            HBUtils.setLoading(btn, false);

            if (!room) {
                HBUtils.showError(1, 'Could not load room details.');
                return;
            }

            renderRoomStep(room);
            updateSummary();
            goToStep(2);
        }).catch(function (err) {
            HBUtils.setLoading(btn, false);
            HBUtils.showError(1, err.message || 'Availability check failed.');
        });
    }

    function renderRoomStep(room) {
        var imageContainer = document.getElementById('hb-room-image');
        if (room.featured_image) {
            imageContainer.innerHTML = '<img src="' + HBUtils.escapeHtml(room.featured_image) + '" alt="' + HBUtils.escapeHtml(room.title) + '">';
        } else {
            imageContainer.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--hb-text-light);">No image</div>';
        }

        var categories = Array.isArray(room.categories) ? room.categories : [];

        document.getElementById('hb-room-title').textContent = room.title || '';
        document.getElementById('hb-room-categories').textContent = categories.join(', ');
        document.getElementById('hb-room-description').innerHTML = room.excerpt || '';
        document.getElementById('hb-room-capacity').textContent =
            room.max_adults + ' Adults, ' + room.max_children + ' Children';
        document.getElementById('hb-room-price').textContent = room.formatted_price || '';
        document.getElementById('hb-room-nights').textContent = state.nights;
        document.getElementById('hb-room-subtotal').textContent = HBUtils.formatPrice(state.roomSubtotal);
    }

    function handleStep2() {
        loadAmenities();
        goToStep(3);
    }

    function loadAmenities() {
        var listEl = document.getElementById('hb-amenities-list');

        // Already fetched for this room and party size: re-render from state so
        // navigating back and forward preserves the guest's selections. The adult
        // count is part of the key because it sets per-person defaults and caps.
        if (state.amenitiesLoadedFor === state.roomId && state.amenitiesLoadedAdults === state.adults) {
            renderAmenities();
            restoreAmenitySelection();
            updateSummary();
            return;
        }

        listEl.innerHTML = '<div class="hb-loading">Loading amenities...</div>';

        var requestedFor = state.roomId;
        var requestedAdults = state.adults;

        HBUtils.apiRequest(
            'rooms/' + requestedFor + '/amenities?adults=' + encodeURIComponent(requestedAdults),
            'GET'
        ).then(function (data) {
            if (requestedFor !== state.roomId) {
                return;
            }
            state.amenities = data.amenities || [];
            state.amenitiesLoadedFor = requestedFor;
            state.amenitiesLoadedAdults = requestedAdults;
            // A changed party size invalidates per-person quantities.
            state.amenityQuantities = {};
            state.selectedAmenities = [];
            renderAmenities();
            // Seeds the always-included free amenities into state.
            updateSelectedAmenities();
            updateSummary();
        }).catch(function (err) {
            listEl.innerHTML = '<p>' + HBUtils.escapeHtml(err && err.message ? err.message : 'Could not load amenities.') + '</p>';
        });
    }

    // Re-check the boxes matching state.selectedAmenities after a re-render.
    function restoreAmenitySelection() {
        var listEl = document.getElementById('hb-amenities-list');
        var selectedIds = state.selectedAmenities.map(function (a) { return a.id; });

        listEl.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            var id = parseInt(cb.value, 10);
            var isSelected = selectedIds.indexOf(id) !== -1;
            cb.checked = isSelected;
            var item = cb.closest('.hb-amenity-item');
            if (item) {
                item.classList.toggle('hb-selected', isSelected);
            }
        });

        syncQuantityVisibility();
    }

    function isIncluded(amenity) {
        // is_included is authoritative; is_free covers amenities saved before the
        // included/optional distinction existed.
        return amenity.is_included === true || (amenity.is_included === undefined && amenity.is_free === true);
    }

    // The guest's chosen quantity if they have set one, otherwise the server's
    // default -- which for per-person amenities is the adult count, not 1.
    function quantityFor(amenity) {
        if (!amenity.uses_quantity) return 1;

        var min = amenity.quantity_min || amenity.min_quantity || 1;
        var max = amenity.quantity_max || amenity.max_quantity || 99;
        var fallback = amenity.quantity_default || min;

        var stored = state.amenityQuantities[amenity.id];
        var value = (stored === undefined || stored === null) ? fallback : stored;

        return Math.max(min, Math.min(max, value));
    }

    function amenityMarkup(amenity) {
        var included = isIncluded(amenity);
        var free = amenity.pricing_type === 'free';

        // A free amenity the guest must opt into is "Free", not "Included" --
        // labelling it Included while it sits under Optional Add-ons with an
        // unticked box tells the guest two contradictory things.
        var priceLabel;
        if (free) {
            priceLabel = included ? 'Included' : 'Free';
        } else {
            priceLabel = HBUtils.formatPrice(amenity.price)
                + (amenity.price_suffix ? ' ' + amenity.price_suffix : '');
        }

        var cls = 'hb-amenity-item';
        if (free) cls += ' hb-free';
        if (included) cls += ' hb-included';

        // One aligned row: [ info | unit price | quantity | total ].
        // The stepper sits OUTSIDE the label -- inside it, clicking +/- would
        // also toggle the amenity's checkbox.
        var html = '<div class="' + cls + '" data-amenity-id="' + amenity.id + '">';
        html += '<div class="hb-amenity-row">';

        html += '<label class="hb-amenity-main">';

        if (included) {
            // Part of the room; shown as a tick rather than an interactive control.
            html += '<span class="hb-amenity-check hb-amenity-check-static" aria-hidden="true">&#10003;</span>';
        } else {
            html += '<span class="hb-amenity-check"><input type="checkbox" value="' + amenity.id + '"'
                 + ' aria-label="' + HBUtils.escapeHtml(amenity.name) + '"></span>';
        }

        if (amenity.icon) {
            html += '<span class="hb-amenity-icon">' + HBUtils.escapeHtml(amenity.icon) + '</span>';
        }

        html += '<span class="hb-amenity-info">';
        html += '<span class="hb-amenity-name">' + HBUtils.escapeHtml(amenity.name) + '</span>';
        if (amenity.description) {
            html += '<span class="hb-amenity-desc">' + HBUtils.escapeHtml(amenity.description) + '</span>';
        }
        html += '</span>';
        html += '</label>';

        // Wrapper is display:contents on desktop so these become grid cells of
        // the row, and a single flex line on mobile.
        html += '<div class="hb-amenity-meta">';

        html += '<span class="hb-amenity-price-tag' + (free ? ' hb-free-tag' : '') + '">'
             + priceLabel + '</span>';

        // Slots are always emitted -- even when empty -- so quantities and totals
        // line up in the same columns across every row in the list.
        html += '<div class="hb-amenity-qty" data-amenity-id="' + amenity.id + '">';

        if (amenity.uses_quantity) {
            var qty = quantityFor(amenity);
            var qtyLabel = HBUtils.escapeHtml(amenity.quantity_label || 'Quantity');

            html += '<span class="hb-number-control hb-number-control-sm">';
            html += '<button type="button" class="hb-num-btn hb-qty-minus"'
                 + ' aria-label="' + qtyLabel + ': decrease">&minus;</button>';
            html += '<input type="number" class="hb-qty-input" value="' + qty + '"'
                 + ' min="' + (amenity.quantity_min || amenity.min_quantity || 1) + '"'
                 + ' max="' + (amenity.quantity_max || amenity.max_quantity || 99) + '"'
                 + ' aria-label="' + qtyLabel + '" readonly>';
            html += '<button type="button" class="hb-num-btn hb-qty-plus"'
                 + ' aria-label="' + qtyLabel + ': increase">+</button>';
            html += '</span>';
        }

        html += '</div>';

        html += '<span class="hb-amenity-line-total" data-line-total="' + amenity.id + '"></span>';
        html += '</div>';

        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderAmenities() {
        var listEl = document.getElementById('hb-amenities-list');

        if (state.amenities.length === 0) {
            listEl.innerHTML = '<p style="color:var(--hb-text-light);">No amenities available for this room.</p>';
            return;
        }

        var included = state.amenities.filter(isIncluded);
        var optional = state.amenities.filter(function (a) { return !isIncluded(a); });

        var html = '';

        if (included.length) {
            html += '<div class="hb-amenity-group">';
            html += '<h4 class="hb-amenity-group-title">Included Amenities</h4>';
            html += included.map(amenityMarkup).join('');
            html += '</div>';
        }

        if (optional.length) {
            html += '<div class="hb-amenity-group">';
            html += '<h4 class="hb-amenity-group-title">Optional Add-ons</h4>';
            html += optional.map(amenityMarkup).join('');
            html += '</div>';
        }

        listEl.innerHTML = html;
        bindAmenityControls(listEl);
        syncQuantityVisibility();
    }

    function bindAmenityControls(listEl) {
        listEl.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var item = this.closest('.hb-amenity-item');
                if (item) item.classList.toggle('hb-selected', this.checked);
                updateSelectedAmenities();
                syncQuantityVisibility();
                updateSummary();
            });
        });

        listEl.querySelectorAll('.hb-amenity-qty').forEach(function (wrap) {
            var id = parseInt(wrap.getAttribute('data-amenity-id'), 10);
            var input = wrap.querySelector('.hb-qty-input');
            var minus = wrap.querySelector('.hb-qty-minus');
            var plus = wrap.querySelector('.hb-qty-plus');

            // Amenities without a quantity model still emit an empty slot, to
            // keep the columns aligned; there is nothing to bind on those.
            if (!input || !minus || !plus) return;

            function apply(delta) {
                var min = parseInt(input.min, 10) || 1;
                var max = parseInt(input.max, 10) || 99;
                var next = Math.max(min, Math.min(max, (parseInt(input.value, 10) || min) + delta));
                input.value = next;
                state.amenityQuantities[id] = next;
                updateSelectedAmenities();
                // Refreshes this row's line total; without it the figure beside
                // the stepper keeps the previous quantity's amount.
                syncQuantityVisibility();
                updateSummary();
            }

            minus.addEventListener('click', function () { apply(-1); });
            plus.addEventListener('click', function () { apply(1); });
        });
    }

    // Quantity steppers and line totals are only meaningful once an amenity is
    // actually chosen, so they are emptied rather than shown with stale numbers.
    // The surrounding slots stay in place to keep the columns aligned.
    function syncQuantityVisibility() {
        var listEl = document.getElementById('hb-amenities-list');
        if (!listEl) return;

        state.amenities.forEach(function (amenity) {
            var item = listEl.querySelector('.hb-amenity-item[data-amenity-id="' + amenity.id + '"]');
            if (!item) return;

            var selected = isIncluded(amenity) || state.selectedAmenities.some(function (a) {
                return a.id === amenity.id;
            });

            var stepper = item.querySelector('.hb-number-control');
            if (stepper) {
                stepper.hidden = !selected;
            }

            var lineTotal = item.querySelector('[data-line-total]');
            if (!lineTotal) return;

            // A free amenity has no total worth showing, and an unselected one
            // has not been chosen yet.
            if (!selected || amenity.pricing_type === 'free') {
                lineTotal.textContent = '';
                return;
            }

            var cost = calculateAmenityCost(amenity);

            // Only worth printing when it differs from the unit price already
            // shown alongside -- otherwise it just repeats the same figure.
            var unit = parseFloat(amenity.price) || 0;
            lineTotal.textContent = (amenity.uses_quantity || cost !== unit)
                ? HBUtils.formatPrice(cost)
                : '';
        });
    }

    function updateSelectedAmenities() {
        state.selectedAmenities = [];
        var listEl = document.getElementById('hb-amenities-list');

        // Included amenities have no checkbox to read, so they come from state.
        // The server adds them regardless; sending them keeps the displayed total
        // and the charged total in step.
        state.amenities.forEach(function (amenity) {
            if (isIncluded(amenity)) {
                state.selectedAmenities.push(amenity);
            }
        });

        if (listEl) {
            listEl.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
                var id = parseInt(cb.value, 10);
                var amenity = state.amenities.find(function (a) { return a.id === id; });
                if (amenity && !isIncluded(amenity)) {
                    state.selectedAmenities.push(amenity);
                }
            });
        }
    }

    // Payload shape the REST endpoints expect.
    function getAmenitySelection() {
        return state.selectedAmenities.map(function (a) {
            return { id: a.id, quantity: quantityFor(a) };
        });
    }

    function updateSummary() {
        var amenityCost = 0;
        state.selectedAmenities.forEach(function (a) {
            amenityCost += calculateAmenityCost(a);
        });

        state.amenitiesSubtotal = Math.round(amenityCost * 100) / 100;
        state.totalAmount = Math.round((state.roomSubtotal + state.amenitiesSubtotal) * 100) / 100;

        var summaryRoom = document.getElementById('hb-summary-room-cost');
        var summaryAmenity = document.getElementById('hb-summary-amenity-cost');
        var summaryTotal = document.getElementById('hb-summary-total-3');

        if (summaryRoom) summaryRoom.textContent = HBUtils.formatPrice(state.roomSubtotal);
        if (summaryAmenity) summaryAmenity.textContent = HBUtils.formatPrice(state.amenitiesSubtotal);
        if (summaryTotal) summaryTotal.textContent = HBUtils.formatPrice(state.totalAmount);
    }

    // Display-only estimate. The server recalculates every figure independently
    // and its result is what is charged.
    function calculateAmenityCost(amenity) {
        var price = parseFloat(amenity.price) || 0;
        var nights = state.nights;
        var qty = quantityFor(amenity);

        // Mirrors PricingService::calculate_amenity_cost(). Person-scoped models
        // bill the chosen headcount, which defaults to the adult count but can be
        // reduced by the guest.
        switch (amenity.pricing_type) {
            case 'fixed': return price;
            case 'per_night': return price * nights;
            case 'per_person': return price * qty;
            case 'per_person_per_night': return price * qty * nights;
            case 'per_hour': return price * qty;
            case 'per_drop': return price * qty;
            default: return 0;
        }
    }

    function handleStep3() {
        goToStep(STEP.DETAILS);
        if (typeof window.HBCustomerForm !== 'undefined') {
            window.HBCustomerForm.render(state);
        }
    }

    function handleStep4(btn) {
        if (typeof window.HBCustomerForm !== 'undefined') {
            var result = window.HBCustomerForm.validate();
            if (!result.valid) {
                HBUtils.showError(STEP.DETAILS, result.error);
                return;
            }
            state.customerData = result.data;
        }

        HBUtils.setLoading(btn, true);

        HBUtils.apiRequest('reservations/hold', 'POST', {
            room_id: state.roomId,
            check_in: state.checkIn,
            check_out: state.checkOut,
            adults: state.adults,
            children: state.children,
            session_id: state.sessionId,
        }).then(function () {
            HBUtils.setLoading(btn, false);

            if (termsEnabled()) {
                goToStep(STEP.TERMS);
                return;
            }

            enterPaymentStep();
        }).catch(function (err) {
            HBUtils.setLoading(btn, false);
            HBUtils.showError(STEP.DETAILS, err.message || 'Could not reserve room.');
        });
    }

    function handleTermsStep() {
        var box = document.getElementById('hb-terms-checkbox');

        if (!box || !box.checked) {
            HBUtils.showError(STEP.TERMS, 'Please accept the Terms & Conditions to continue.');
            return;
        }

        state.termsAccepted = true;
        HBUtils.clearError(STEP.TERMS);
        enterPaymentStep();
    }

    function enterPaymentStep() {
        goToStep(STEP.PAYMENT);
        if (typeof window.HBPayment !== 'undefined') {
            window.HBPayment.render(state);
        }
    }

    function handlePaymentStep(btn) {
        if (typeof window.HBPayment !== 'undefined') {
            window.HBPayment.process(state, btn);
        }
    }

    function bindTermsCheckbox() {
        var box = document.getElementById('hb-terms-checkbox');
        var btn = document.getElementById('hb-terms-continue');
        if (!box || !btn) return;

        box.addEventListener('change', function () {
            btn.disabled = !this.checked;
            state.termsAccepted = this.checked;
            if (this.checked) {
                HBUtils.clearError(STEP.TERMS);
            }
        });
    }

    function goToStep(step) {
        step = resolveStep(step, step < state.currentStep ? -1 : 1);
        state.currentStep = step;

        document.querySelectorAll('.hb-panel').forEach(function (p) {
            p.classList.remove('active');
        });
        var panel = document.getElementById('hb-panel-' + step);
        if (panel) panel.classList.add('active');

        document.querySelectorAll('.hb-step').forEach(function (s) {
            var sStep = parseInt(s.getAttribute('data-step'), 10);
            s.classList.remove('active', 'completed');
            if (sStep === step) {
                s.classList.add('active');
            } else if (sStep < step) {
                s.classList.add('completed');
            }
        });

        // Amenities and Payment use a two-column layout, so the dialog widens
        // for those steps only.
        if (overlay) {
            overlay.classList.toggle('hb-wide', step === STEP.AMENITIES || step === STEP.PAYMENT);
        }

        // Each step scrolls from the top rather than inheriting the last position.
        var panels = document.querySelector('.hb-panels');
        if (panels) panels.scrollTop = 0;
    }

    // Expose for Payment/Customer modules
    window.HBPopup = {
        state: state,
        goToStep: goToStep,
        STEP: STEP,
        // The amenity payload the server expects: ids plus chosen quantities.
        getAmenitySelection: getAmenitySelection,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
