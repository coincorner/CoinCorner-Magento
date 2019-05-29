<?php

namespace CoinCorner\BitcoinCheckout\Controller\Payment;

use Magento\Framework\App\Action\Action;

class OrderSuccess extends Action
{
    
    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }


    public function execute()
    {
        if ($this->_getCheckout()->getLastRealOrderId()) {
            $this->_redirect('checkout/onepage/success');
        }
    }
}