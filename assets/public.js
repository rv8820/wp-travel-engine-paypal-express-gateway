if (typeof paypalexpress !== 'undefined') {
    jQuery(function ($) {
        var amountsByPaymentMode = {
            full_payment: wte.payments.total,
            partial: wte.payments.total_partial,
            remaining_payment: wte.payments.remaining_payment ?? (wte.payments.total - wte.payments.total_partial)
        }
        /**
         * Reset to normal booking flow
         */
        function ResetBookingFlow() {

            $('#wte-PayPalExpress-button').remove();
            $('.wpte-bf-field.wpte-bf-submit > input[name="wp_travel_engine_nw_bkg_submit"]').css('visibility', 'visible');

        }

        /**
         * Show paypal express checkout options on the checkout page.
         */
        function ShoWPayPalExpress() {

            var payment_option = $('input[name="wpte_checkout_paymnet_method"]:checked').val();

            if ('paypalexpress_enable' !== payment_option) {
                ResetBookingFlow();
                return false;
            }

            //$( '.wpte-bf-field.wpte-bf-submit' ).append( '<div id="wte-PayPalExpress-button"></div>' );
            $('<div id="wte-PayPalExpress-button"></div>').insertBefore(".wpte-bf-field.wpte-bf-submit");

            $('.wpte-bf-field.wpte-bf-submit > input[name="wp_travel_engine_nw_bkg_submit"]').css('visibility', 'hidden');

            var complete_payment = function (l) {

                var form_id = 'wp-travel-engine-new-checkout-form';

                jQuery('[name=wte_paypal_express_payment_details]').remove();
                jQuery('form#' + form_id)
                    .append(jQuery("<input type='hidden' name='wte_paypal_express_payment_details' />")
                        .attr("value", JSON.stringify(l)))
                    .find("[type=submit]").click();

            };

            var a = jQuery("#wp-travel-engine-new-checkout-form"),
                b = 0 < a.length && a.parsley(),
                c = function (d) {
                    if (b && b.isValid) {
                        if (b.isValid()) return void d.enable();
                        d.disable()
                    } else d.enable()
                };

            var form_id = 'wp-travel-engine-new-checkout-form';

            var payment_mode = $('input[name=wp_travel_engine_payment_mode]:checked').val();

            try {

                paypal.Buttons({

                    // onInit is called when the button first renders
                    onInit: function (data, actions) {

                        if (!a.parsley().isValid()) {
                            // Disable the buttons
                            actions.disable();
                        }

                        // Listen for changes to the checkbox
                        window.Parsley.on('form:success', function (event) {
                            actions.enable();
                        });

                        $('#' + form_id).change(function (event) {
                            if (!a.parsley().isValid()) {
                                // Disable the buttons
                                actions.disable();
                            }
                            a.parsley().validate();
                        });

                    },

                    onClick() {
                        a.parsley().validate();
                    },

                    createOrder: function (data, actions) {
                        let purchase_value = amountsByPaymentMode[payment_mode] || amountsByPaymentMode['full_payment'];
                        return actions.order.create({
                            purchase_units: [{
                                amount: {
                                    value: Math.round( purchase_value * 100 ) / 100,
                                    currency_code: wte.currency.code
                                }
                            }]
                        });
                    },

                    onApprove: function (data, actions) {
                        $('#wte-PayPalExpress-button').css({
                            'pointer-events': 'none',
                            'opacity': '0.5',
                        });

                        jQuery('form#wp-travel-engine-new-checkout-form').append(jQuery("<input type='hidden' name='wte_paypal_express_payment_token' />").attr("value", data.orderID))

                        return actions.order.capture().then(function (details) {
                            complete_payment(details);
                        });
                    },

                    onCancel: function (data) {
                        console.log('checkout.js payment cancelled', JSON.stringify(data, 0, 2));
                    },

                    onError: function (err) {
                        alert(paypalexpress.error);
                    }

                }).render('#wte-PayPalExpress-button');

            } catch (j) { }

        }

        $(document).on('change', 'input[name="wpte_checkout_paymnet_method"], input[name="wp_travel_engine_payment_mode"]', function () {
            ResetBookingFlow();
            ShoWPayPalExpress();
        });

        ShoWPayPalExpress();

    });
}
