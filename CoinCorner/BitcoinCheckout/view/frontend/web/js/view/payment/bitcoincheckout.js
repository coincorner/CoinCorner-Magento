define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component, rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'coincorner_bitcoincheckout',
                component: 'CoinCorner_BitcoinCheckout/js/view/payment/method-renderer/bitcoincheckout'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    });
