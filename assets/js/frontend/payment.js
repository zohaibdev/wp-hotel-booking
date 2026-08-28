(function () {
    'use strict';

    var stripe = null;
    var elements = null;
    var cardElement = null;
    // The card Element is created once and reused; these track its attachment
    // and readiness so it is never confirmed against while detached or loading.
    var cardMounted = false;
    var cardReady = false;
    // The exact node the Element is mounted into, so a rebuilt container is
    // detected without inspecting Stripe's own (async) iframe.
    var cardMountNode = null;

    // Panel numbers, kept in sync with booking-popup.js's STEP map.
    var PAY_STEP = 6;
    var CONFIRM_STEP = 7;

    // Server-calculated plan options for the current booking.
    var planOptions = [];

    window.HBCustomerForm = {
        render: function (state) {
            var container = document.getElementById('hb-customer-form');
            container.innerHTML = '<div class="hb-loading">Loading form...</div>';

            HBUtils.apiRequest('fields', 'GET').then(function (data) {
                var fields = data.fields || [];
                renderFields(container, fields);
            }).catch(function () {
                container.innerHTML = '<p>Could not load form fields.</p>';
            });
        },

        validate: function () {
            var container = document.getElementById('hb-customer-form');
            var inputs = container.querySelectorAll('[data-field-key]');
            var data = {};
            var firstError = null;

            inputs.forEach(function (input) {
                input.classList.remove('hb-invalid');
                var key = input.getAttribute('data-field-key');
                var required = input.hasAttribute('data-required');
                var val = input.value.trim();

                data[key] = val;

                if (required && !val) {
                    input.classList.add('hb-invalid');
                    if (!firstError) {
                        firstError = input.getAttribute('data-label') + ' is required.';
                        input.focus();
                    }
                }

                if (input.type === 'email' && val && !isValidEmail(val)) {
                    input.classList.add('hb-invalid');
                    if (!firstError) {
                        firstError = 'Please enter a valid email address.';
                        input.focus();
                    }
                }
            });

            if (firstError) {
                return { valid: false, error: firstError };
            }

            return { valid: true, data: data };
        },
    };

    window.HBPayment = {
        render: function (state) {
            var methodsContainer = document.getElementById('hb-payment-methods');
            var formContainer = document.getElementById('hb-payment-form');
            var summaryContainer = document.getElementById('hb-summary-6');

            var html = '';
            var settings = window.hbBooking || {};
            var hasMethod = false;

            if (settings.stripe_enabled && settings.stripe_publishable_key) {
                hasMethod = true;
                html += '<label class="hb-payment-method hb-selected" data-method="stripe">';
                html += '<input type="radio" name="hb_payment_method" value="stripe" checked>';
                html += '<span class="hb-payment-method-info">';
                html += '<h4>Credit / Debit Card</h4>';
                html += '<p>Pay securely with your card</p>';
                html += '</span></label>';
            }

            if (settings.offline_enabled) {
                var checked = !hasMethod ? 'checked' : '';
                var cls = !hasMethod ? 'hb-payment-method hb-selected' : 'hb-payment-method';
                hasMethod = true;
                html += '<label class="' + cls + '" data-method="offline">';
                html += '<input type="radio" name="hb_payment_method" value="offline" ' + checked + '>';
                html += '<span class="hb-payment-method-info">';
                html += '<h4>' + HBUtils.escapeHtml(settings.offline_title || 'Pay at Hotel') + '</h4>';
                html += '<p>' + HBUtils.escapeHtml(settings.offline_description || '') + '</p>';
                html += '</span></label>';
            }

            if (!hasMethod) {
                html = '<p>No payment methods are currently available.</p>';
            } else {
                html = '<h4 class="hb-plans-title">Payment Method</h4>' + html;
            }

            methodsContainer.innerHTML = html;

            // Bind radio buttons
            methodsContainer.querySelectorAll('input[type="radio"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    methodsContainer.querySelectorAll('.hb-payment-method').forEach(function (m) {
                        m.classList.remove('hb-selected');
                    });
                    this.closest('.hb-payment-method').classList.add('hb-selected');
                    renderPaymentForm(this.value, formContainer);
                });
            });

            // Render initial payment form
            var selectedMethod = methodsContainer.querySelector('input[type="radio"]:checked');
            if (selectedMethod) {
                renderPaymentForm(selectedMethod.value, formContainer);
            }

            renderSummary(state);
            loadPaymentPlans(state);
        },

        process: function (state, btn) {
            var selectedRadio = document.querySelector('input[name="hb_payment_method"]:checked');
            if (!selectedRadio) {
                HBUtils.showError(PAY_STEP,'Please select a payment method.');
                return;
            }

            var method = selectedRadio.value;
            state.paymentMethod = method;

            HBUtils.clearError(PAY_STEP);
            HBUtils.setLoading(btn, true);

            if (method === 'stripe') {
                processStripe(state, btn);
            } else if (method === 'offline') {
                processOffline(state, btn);
            }
        },
    };

    function renderSummary(state) {
        var el = document.getElementById('hb-summary-6');
        if (!el) return;

        var html = '<div class="hb-summary-line"><span>Room (' + state.nights + ' night'
            + (state.nights > 1 ? 's' : '') + ')</span><span>'
            + HBUtils.formatPrice(state.roomSubtotal) + '</span></div>';

        if (state.amenitiesSubtotal > 0) {
            html += '<div class="hb-summary-line"><span>Amenities</span><span>'
                + HBUtils.formatPrice(state.amenitiesSubtotal) + '</span></div>';
        }

        html += '<div class="hb-summary-line hb-summary-total"><span>Total</span><span>'
            + HBUtils.formatPrice(state.totalAmount) + '</span></div>';

        var plan = currentPlan();
        if (plan && plan.value === 'partial_payment') {
            html += '<div class="hb-summary-line hb-summary-now"><span>Paying now</span><span>'
                + HBUtils.formatPrice(plan.amount) + '</span></div>';
            html += '<div class="hb-summary-line hb-summary-balance"><span>Balance due '
                + HBUtils.escapeHtml(formatDate(plan.due_date)) + '</span><span>'
                + HBUtils.formatPrice(plan.balance) + '</span></div>';
        }

        el.innerHTML = html;
    }

    function currentPlan() {
        if (!planOptions.length) return null;
        var selected = document.querySelector('input[name="hb_payment_plan"]:checked');
        var value = selected ? selected.value : 'full_payment';
        return planOptions.filter(function (p) { return p.value === value; })[0] || planOptions[0];
    }

    function formatDate(value) {
        if (!value) return '';
        var d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(value).substring(0, 10);
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    // Amounts and due dates are always fetched from the server; the UI never
    // derives a deposit itself.
    function loadPaymentPlans(state) {
        var container = document.getElementById('hb-payment-plans');
        if (!container) return;

        container.innerHTML = '';
        planOptions = [];

        HBUtils.apiRequest('payment-plans', 'POST', {
            room_id: state.roomId,
            check_in: state.checkIn,
            check_out: state.checkOut,
            adults: state.adults,
            children: state.children,
            amenities: amenityPayload(state),
        }).then(function (data) {
            planOptions = data.plans || [];

            // Nothing to choose between when only full payment is offered.
            if (planOptions.length < 2) {
                state.paymentPlan = 'full_payment';
                renderSummary(state);
                updatePayButton(state);
                return;
            }

            renderPlanOptions(container, state);
        }).catch(function () {
            // A failure here must not block payment: fall back to full payment.
            state.paymentPlan = 'full_payment';
        });
    }

    function renderPlanOptions(container, state) {
        var html = '<h4 class="hb-plans-title">Payment Option</h4>';

        planOptions.forEach(function (plan, index) {
            var checked = index === 0 ? 'checked' : '';
            var cls = index === 0 ? 'hb-payment-plan hb-selected' : 'hb-payment-plan';
            var pct = String(plan.percentage).replace(/\.00$/, '');

            html += '<label class="' + cls + '" data-plan="' + HBUtils.escapeHtml(plan.value) + '">';
            html += '<input type="radio" name="hb_payment_plan" value="' + HBUtils.escapeHtml(plan.value) + '" ' + checked + '>';
            html += '<span class="hb-plan-info">';
            html += '<span class="hb-plan-title">'
                + (plan.value === 'partial_payment' ? 'Pay ' + HBUtils.escapeHtml(pct) + '% Now' : 'Pay Full Amount')
                + '</span>';
            html += '<span class="hb-plan-amount">' + HBUtils.formatPrice(plan.amount) + '</span>';

            if (plan.value === 'partial_payment') {
                html += '<span class="hb-plan-note">Remaining ' + HBUtils.formatPrice(plan.balance)
                    + ' due by ' + HBUtils.escapeHtml(formatDate(plan.due_date)) + '</span>';
            }

            html += '</span></label>';
        });

        container.innerHTML = html;

        container.querySelectorAll('input[name="hb_payment_plan"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                container.querySelectorAll('.hb-payment-plan').forEach(function (el) {
                    el.classList.remove('hb-selected');
                });
                this.closest('.hb-payment-plan').classList.add('hb-selected');
                state.paymentPlan = this.value;
                renderSummary(state);
                updatePayButton(state);
            });
        });

        state.paymentPlan = planOptions[0].value;
        renderSummary(state);
        updatePayButton(state);
    }

    // "Pay $500 Now" is clearer than a bare "Complete Booking".
    function updatePayButton(state) {
        var btn = document.getElementById('hb-pay-btn');
        if (!btn) return;

        var label = btn.querySelector('.hb-btn-label');
        if (!label) {
            label = document.createElement('span');
            label.className = 'hb-btn-label';
            label.textContent = btn.textContent.trim() || 'Complete Booking';
            btn.insertBefore(label, btn.firstChild);
            // Drop the original bare text node now that it lives in the span.
            Array.prototype.slice.call(btn.childNodes).forEach(function (n) {
                if (n.nodeType === 3) btn.removeChild(n);
            });
        }

        var plan = currentPlan();
        var amount = plan ? plan.amount : state.totalAmount;

        label.textContent = amount > 0
            ? 'Pay ' + HBUtils.formatPrice(amount) + ' Now'
            : 'Complete Booking';
    }

    // Quantity-aware amenity payload, shared by every request from this step.
    function amenityPayload(state) {
        if (window.HBPopup && typeof window.HBPopup.getAmenitySelection === 'function') {
            return window.HBPopup.getAmenitySelection();
        }
        return (state.selectedAmenities || []).map(function (a) {
            return { id: a.id, quantity: 1 };
        });
    }

    function renderFields(container, fields) {
        if (fields.length === 0) {
            container.innerHTML = '<p>No fields configured.</p>';
            return;
        }

        var html = '<div class="hb-form-grid">';
        fields.forEach(function (field) {
            var required = field.required ? 'data-required' : '';
            var requiredMark = field.required ? ' <span style="color:var(--hb-danger);">*</span>' : '';
            var fullClass = (field.type === 'textarea') ? ' hb-field-full' : '';

            html += '<div class="hb-field' + fullClass + '">';
            html += '<label>' + HBUtils.escapeHtml(field.label) + requiredMark + '</label>';

            if (field.type === 'textarea') {
                html += '<textarea data-field-key="' + HBUtils.escapeHtml(field.key) + '" ' + required;
                html += ' data-label="' + HBUtils.escapeHtml(field.label) + '"';
                html += ' placeholder="' + HBUtils.escapeHtml(field.placeholder || '') + '"';
                html += '></textarea>';
            } else if (field.type === 'select' && field.options) {
                html += '<select data-field-key="' + HBUtils.escapeHtml(field.key) + '" ' + required;
                html += ' data-label="' + HBUtils.escapeHtml(field.label) + '">';
                html += '<option value="">-- Select --</option>';
                var opts = typeof field.options === 'string' ? field.options.split(',') : field.options;
                opts.forEach(function (opt) {
                    opt = opt.trim();
                    html += '<option value="' + HBUtils.escapeHtml(opt) + '">' + HBUtils.escapeHtml(opt) + '</option>';
                });
                html += '</select>';
            } else {
                var inputType = field.type === 'phone' ? 'tel' : (field.type || 'text');
                html += '<input type="' + inputType + '"';
                html += ' data-field-key="' + HBUtils.escapeHtml(field.key) + '"';
                html += ' data-label="' + HBUtils.escapeHtml(field.label) + '"';
                html += ' placeholder="' + HBUtils.escapeHtml(field.placeholder || '') + '"';
                html += ' ' + required + '>';
            }

            html += '</div>';
        });
        html += '</div>';

        container.innerHTML = html;
    }

    function renderPaymentForm(method, container) {
        if (method === 'stripe') {
            // Reuse an existing mount point. Replacing innerHTML would destroy the
            // node the card Element is mounted into, detaching Stripe's iframe
            // behind its back and leaving an Element that never becomes ready.
            if (!container.querySelector('#hb-card-element')) {
                detachCard();
                container.innerHTML = '<div id="hb-card-element" class="hb-stripe-element"></div>';
            }

            var err = initStripe();
            if (err) {
                detachCard();
                container.innerHTML = '';
                HBUtils.showError(PAY_STEP, err);
            } else {
                HBUtils.clearError(PAY_STEP);
            }
        } else {
            // Detach before the node disappears, so the Element can be re-mounted
            // cleanly if the guest switches back to card.
            detachCard();
            container.innerHTML = '';
            HBUtils.clearError(PAY_STEP);
        }
    }

    // Unmount without destroying: the Element is reused for the lifetime of the
    // popup, because an Elements group permits only one Element of each type.
    function detachCard() {
        if (!cardElement) return;
        try { cardElement.unmount(); } catch (e) { /* already detached */ }
        cardMounted = false;
        cardMountNode = null;
    }

    // Returns an error string when Stripe.js cannot be used, otherwise ''.
    function initStripe() {
        var pk = window.hbBooking.stripe_publishable_key;
        if (!pk) {
            return 'Card payment is not configured.';
        }

        // js.stripe.com is a hard dependency loaded from a third-party origin; an
        // ad blocker, strict CSP, or offline network leaves the global undefined
        // and calling it would throw an uncaught ReferenceError.
        if (typeof Stripe === 'undefined') {
            return 'The secure card form could not be loaded. Please disable any ad blocker or choose another payment method.';
        }

        try {
            if (!stripe) {
                stripe = Stripe(pk);
                elements = stripe.elements();
            }
        } catch (e) {
            return 'The secure card form could not be initialised.';
        }

        var mountNode = document.getElementById('hb-card-element');
        if (!mountNode) {
            return 'The secure card form could not be displayed.';
        }

        try {
            // Created once. Calling elements.create('card') a second time on the
            // same group yields an Element that never emits 'ready', which is what
            // produces "could not retrieve data from the specified Element".
            if (!cardElement) {
                cardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '15px',
                            fontFamily: 'var(--hb-font-family)',
                            color: '#1e293b',
                            '::placeholder': { color: '#94a3b8' },
                        },
                        invalid: { color: '#dc2626' },
                    },
                });

                cardElement.on('ready', function () { cardReady = true; });
                cardElement.on('change', function (event) {
                    if (event.error) {
                        HBUtils.showError(PAY_STEP, event.error.message);
                    } else {
                        HBUtils.clearError(PAY_STEP);
                    }
                });
            }

            // Re-mount only when genuinely detached, or when the container was
            // rebuilt and this is a different node. Mounting wipes whatever the
            // guest has typed, so a spurious re-mount surfaces as Stripe's
            // "Your card number is incomplete" on an apparently complete card.
            // Node identity is checked rather than hasChildNodes(), because the
            // iframe is injected asynchronously and is briefly absent.
            if (!cardMounted || cardMountNode !== mountNode || !mountNode.isConnected) {
                cardElement.mount(mountNode);
                cardMounted = true;
                cardMountNode = mountNode;
            }
        } catch (e) {
            return 'The secure card form could not be initialised.';
        }

        return '';
    }

    // Resolves once Stripe reports the Element ready, so a fast click straight
    // after the step opens does not confirm against an Element still loading.
    function whenCardReady(timeoutMs) {
        if (cardReady) return Promise.resolve(true);

        return new Promise(function (resolve) {
            var waited = 0;
            var poll = setInterval(function () {
                waited += 50;
                if (cardReady || waited >= (timeoutMs || 5000)) {
                    clearInterval(poll);
                    resolve(cardReady);
                }
            }, 50);
        });
    }

    function processStripe(state, btn) {
        if (!stripe || !cardElement || !cardMounted) {
            HBUtils.showError(PAY_STEP, 'The card form is not ready. Please reload the page and try again.');
            HBUtils.setLoading(btn, false);
            return;
        }

        // Tracks whether the card was actually charged, so a later failure is not
        // reported as "payment failed -- try again" (which would double-charge).
        var charged = false;

        whenCardReady().then(function (ready) {
            if (!ready) {
                throw { message: 'The card form is still loading. Please wait a moment and try again.' };
            }

            return HBUtils.apiRequest('payments/create-intent', 'POST', {
                room_id: state.roomId,
                check_in: state.checkIn,
                check_out: state.checkOut,
                adults: state.adults,
                children: state.children,
                amenities: amenityPayload(state),
                session_id: state.sessionId,
                // Re-validated server-side and attached to the PaymentIntent as
                // receipt_email + metadata. Never used for pricing.
                customer: state.customerData,
                // Selects the plan only; the server derives the amount.
                payment_plan: state.paymentPlan || 'full_payment',
            });
        }).then(function (data) {
            if (!data.client_secret) {
                throw { message: 'Could not start the payment. Please try again.' };
            }
            return stripe.confirmCardPayment(data.client_secret, {
                payment_method: {
                    card: cardElement,
                    billing_details: buildBillingDetails(state),
                },
            });
        }).then(function (result) {
            if (result.error) {
                // Card declined / 3DS failed -- nothing was captured, so a retry is safe.
                throw { message: result.error.message, declined: true };
            }

            charged = true;
            state.paymentIntentId = result.paymentIntent.id;

            return confirmBooking(state, 'stripe', result.paymentIntent.id);
        }).then(function (data) {
            HBUtils.setLoading(btn, false);
            showConfirmation(state, data);
        }).catch(function (err) {
            HBUtils.setLoading(btn, false);

            if (charged && err.code !== 'hb_payment_already_used') {
                // The money left the customer's account but the booking did not
                // persist. Retrying would charge again, so disable the button and
                // hand over a reference the hotel can reconcile against.
                var payBtn = document.getElementById('hb-pay-btn');
                if (payBtn) payBtn.disabled = true;

                HBUtils.showError(PAY_STEP,
                    'Your card was charged but we could not finish saving your booking. ' +
                    'Please do NOT pay again — contact the hotel quoting reference ' +
                    (state.paymentIntentId || 'unknown') + '.');

                if (window.console && console.error) {
                    console.error('[Hotel Booking] Charge succeeded but booking confirmation failed.', {
                        payment_intent: state.paymentIntentId,
                        session_id: state.sessionId,
                        error: err,
                    });
                }
                return;
            }

            HBUtils.showError(PAY_STEP,err.message || 'Payment failed. Please try again.');
        });
    }

    function processOffline(state, btn) {
        confirmBooking(state, 'offline', '').then(function (data) {
            HBUtils.setLoading(btn, false);
            showConfirmation(state, data);
        }).catch(function (err) {
            HBUtils.setLoading(btn, false);
            HBUtils.showError(PAY_STEP,err.message || 'Could not complete booking.');
        });
    }

    function confirmBooking(state, method, intentId) {
        return HBUtils.apiRequest('bookings/confirm', 'POST', {
            room_id: state.roomId,
            check_in: state.checkIn,
            check_out: state.checkOut,
            check_in_time: state.checkInTime,
            check_out_time: state.checkOutTime,
            adults: state.adults,
            children: state.children,
            amenities: amenityPayload(state),
            customer: state.customerData,
            payment_method: method,
            payment_intent_id: intentId || '',
            session_id: state.sessionId,
            payment_plan: state.paymentPlan || 'full_payment',
            terms_accepted: !!state.termsAccepted,
        });
    }

    function showConfirmation(state, data) {
        document.getElementById('hb-confirm-number').textContent = 'Booking #' + (data.booking_number || data.booking_id);

        var statusLabels = {
            paid: 'Paid',
            partially_paid: 'Partially Paid',
            pending: 'Pending',
            failed: 'Failed',
            refunded: 'Refunded',
        };

        var paidLabel = statusLabels[data.payment_status]
            || (data.payment_method === 'stripe' ? 'Paid' : 'Pending');

        var details = '';
        details += '<strong>Room:</strong> ' + HBUtils.escapeHtml(state.room ? state.room.title : '') + '<br>';
        details += '<strong>Check-in:</strong> ' + HBUtils.escapeHtml(state.checkIn) + (state.checkInTime ? ' ' + HBUtils.escapeHtml(state.checkInTime) : '') + '<br>';
        details += '<strong>Check-out:</strong> ' + HBUtils.escapeHtml(state.checkOut) + (state.checkOutTime ? ' ' + HBUtils.escapeHtml(state.checkOutTime) : '') + '<br>';
        details += '<strong>Guests:</strong> ' + state.adults + ' Adult(s), ' + state.children + ' Child(ren)<br>';
        details += '<strong>Total:</strong> ' + HBUtils.formatPrice(data.total_amount || state.totalAmount) + '<br>';
        details += '<strong>Payment:</strong> ' + (data.payment_method === 'stripe' ? 'Card' : 'Pay at Hotel')
            + ' — ' + HBUtils.escapeHtml(paidLabel) + '<br>';

        document.getElementById('hb-confirm-details').innerHTML = details;

        // Deposit breakdown, shown only when a balance actually remains. Every
        // figure comes from the server response.
        var balanceBlock = document.getElementById('hb-confirm-balance');
        if (balanceBlock) {
            if (data.is_partial && parseFloat(data.remaining_balance) > 0) {
                document.getElementById('hb-balance-total').textContent =
                    HBUtils.formatPrice(data.total_amount);
                document.getElementById('hb-balance-paid').textContent =
                    HBUtils.formatPrice(data.amount_paid_now);
                document.getElementById('hb-balance-remaining').textContent =
                    HBUtils.formatPrice(data.remaining_balance);
                document.getElementById('hb-balance-date').textContent =
                    formatDate(data.balance_due_date);
                balanceBlock.style.display = '';
            } else {
                balanceBlock.style.display = 'none';
            }
        }

        // Transaction reference comes from the server's payment record; it is never
        // generated here.
        var txnWrap = document.getElementById('hb-confirm-txn');
        var txnId = document.getElementById('hb-confirm-txn-id');
        if (txnWrap && txnId) {
            if (data.transaction_id) {
                txnId.textContent = data.transaction_id;
                txnWrap.style.display = '';
                bindCopyButton();
            } else {
                txnId.textContent = '';
                txnWrap.style.display = 'none';
            }
        }

        var emailEl = document.getElementById('hb-confirm-email');
        if (emailEl) {
            var email = data.customer_email || (state.customerData && state.customerData.email) || '';
            emailEl.textContent = email
                ? 'A confirmation email has been sent to ' + email + '.'
                : '';
        }

        window.HBPopup.goToStep(CONFIRM_STEP);
    }

    // Billing details ride along with the payment method so the payer's name,
    // email and phone show on the payment in the Stripe dashboard. The server
    // independently attaches its own validated copy to the PaymentIntent, so this
    // is presentation for Stripe, not a trusted input.
    function buildBillingDetails(state) {
        var c = state.customerData || {};
        var name = [c.first_name, c.last_name].filter(Boolean).join(' ').trim();
        var details = {};

        if (name) details.name = name;
        if (c.email) details.email = c.email;
        if (c.phone) details.phone = c.phone;

        var address = {};
        if (c.address) address.line1 = c.address;
        if (c.city) address.city = c.city;
        if (c.state) address.state = c.state;
        if (c.postal_code) address.postal_code = c.postal_code;
        if (Object.keys(address).length) details.address = address;

        return details;
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        // Fallback for non-secure contexts, where the async clipboard API is absent.
        return new Promise(function (resolve, reject) {
            try {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                var ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? resolve() : reject();
            } catch (e) {
                reject(e);
            }
        });
    }

    function bindCopyButton() {
        var btn = document.getElementById('hb-copy-txn');
        if (!btn || btn.dataset.hbBound === '1') return;
        btn.dataset.hbBound = '1';

        btn.addEventListener('click', function () {
            var idEl = document.getElementById('hb-confirm-txn-id');
            if (!idEl || !idEl.textContent) return;

            var self = this;
            copyToClipboard(idEl.textContent).then(function () {
                self.textContent = self.getAttribute('data-copied-label') || 'Copied';
                self.classList.add('hb-copied');
                setTimeout(function () {
                    self.textContent = self.getAttribute('data-copy-label') || 'Copy';
                    self.classList.remove('hb-copied');
                }, 2000);
            }).catch(function () {
                // Selecting the text still lets the user copy manually.
                var range = document.createRange();
                range.selectNodeContents(idEl);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            });
        });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    bindCopyButton();
})();
