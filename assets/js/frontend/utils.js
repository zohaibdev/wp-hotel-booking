(function () {
    'use strict';

    window.HBUtils = {
        formatPrice: function (amount) {
            var symbol = window.hbBooking.currency_symbol || '$';
            var position = window.hbBooking.currency_position || 'before';
            var formatted = parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return position === 'before' ? symbol + formatted : formatted + symbol;
        },

        apiRequest: function (endpoint, method, data) {
            var url = window.hbBooking.rest_url + endpoint;
            var options = {
                method: method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.hbBooking.nonce,
                },
            };

            if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
                options.body = JSON.stringify(data);
            }

            return fetch(url, options).then(function (response) {
                return response.text().then(function (text) {
                    var json;
                    try {
                        json = JSON.parse(text);
                    } catch (e) {
                        // A PHP notice or fatal ahead of the payload leaves
                        // unparseable output; surface a clean message instead of
                        // a raw SyntaxError.
                        throw {
                            message: 'The server returned an invalid response. Please try again.',
                            code: 'hb_invalid_json',
                        };
                    }

                    if (!response.ok || json.success === false) {
                        throw {
                            message: json.message || 'Request failed. Please try again.',
                            code: json.code || 'hb_error',
                            data: json.data || {},
                        };
                    }

                    // Unwrap the {success, data, message} envelope.
                    return json.data !== undefined ? json.data : json;
                });
            });
        },

        showError: function (panelNum, message) {
            var el = document.getElementById('hb-error-' + panelNum);
            if (el) {
                el.textContent = message;
                el.classList.add('hb-show');
            }
        },

        clearError: function (panelNum) {
            var el = document.getElementById('hb-error-' + panelNum);
            if (el) {
                el.textContent = '';
                el.classList.remove('hb-show');
            }
        },

        setLoading: function (btn, loading) {
            if (!btn) return;
            btn.disabled = loading;
            var spinner = btn.querySelector('.hb-spinner');
            if (spinner) {
                spinner.style.display = loading ? 'inline-block' : 'none';
            }
        },

        generateSessionId: function () {
            var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            var result = '';
            var array = new Uint8Array(32);
            window.crypto.getRandomValues(array);
            for (var i = 0; i < 32; i++) {
                result += chars[array[i] % chars.length];
            }
            return result;
        },

        escapeHtml: function (text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        },
    };
})();
