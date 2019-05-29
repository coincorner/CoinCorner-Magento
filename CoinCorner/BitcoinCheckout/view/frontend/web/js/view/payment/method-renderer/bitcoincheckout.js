define([
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
    ],
    function ($, Component, placeOrderAction, selectPaymentMethodAction, customer, checkoutData, additionalValidators, url) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'CoinCorner_BitcoinCheckout/payment/bitcoincheckout'
            },

            placeOrder: function(data, event) {

                var PreOrderCheckResult = false;


                var preordercheck = $.ajax({
                    url: url.build('coincorner/payment/preordercheck'),
                    type: 'POST',
                    async: false,
                    dataType: 'json',
                    error: function(xhr,s,e) {
                        PreOrderCheckResult = false
                    },
                    success: function(resp) {
                        if(resp.status == true) {
                            PreOrderCheckResult = true;
                        }
                        else 
                        {
                            PreOrderCheckResult = false;
                        }
                    }
                });

                if(PreOrderCheckResult == false) {
                    alert('Sorry! something went wrong, please try again later')
                    return false;
                }


                if (event) {
                    event.preventDefault();
                }
                var self = this,placeOrder,emailValidationResult = customer.isLoggedIn(),loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }
                if (emailValidationResult && this.validate() && additionalValidators.validate()) 
                {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);
   
                    $.when(placeOrder).fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },

            afterPlaceOrder: function(quoteId) {

                var request = $.ajax({
                    url: url.build('coincorner/payment/createbitcoinorder'),
                    type: 'POST',
                    dataType: 'json',
                    data: {quote_id: quoteId},
                    error: function(xhr,s,e) {
                        alert('Sorry! something went wrong, please try again later')
                    }
                });
   
                request.done(function(response) {

                    if (response.status) {
                        window.location.replace(response.payment_url);
                    } 
                    else {
                        alert('Sorry! something went wrong, please try again later')
                        window.location.replace('checkout/cart');
                    }
                });

            },

            context: function() {
                return this;
            },

            getCode: function() {
                return 'coincorner_bitcoincheckout';
            },

            isActive: function() {
                return true;
            }
        });
    }
);