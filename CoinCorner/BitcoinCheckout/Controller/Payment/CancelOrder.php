<?php

namespace CoinCorner\BitcoinCheckout\Controller\Payment;

use Magento\Framework\App\Action\Action;

class CancelOrder extends Action
{

    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }


    public function execute()
    {
        if ($this->_getCheckout()->getLastRealOrderId()) {

            $order = $this->_getCheckout()->getLastRealOrder();
            if ($order->getId() && ! $order->isCanceled()) {
                $order->registerCancellation('Order has been cancelled')->save();
            }

            $this->_getCheckout()->restoreQuote();
            $this->_redirect('checkout/cart');
        }
    }

}