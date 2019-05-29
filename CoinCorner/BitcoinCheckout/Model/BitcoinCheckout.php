<?php

namespace CoinCorner\BitcoinCheckout\Model;

class BitcoinCheckout extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'coincorner_bitcoincheckout';

    protected $_code = self::CODE;

    protected $_canAuthorize = true;
    protected $_canCapture = true;

 
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            if(is_null($payment->getParentTransactionId())) {
                $this->authorize($payment, $amount);
            }

            $request = [
                'capture_amount' => $amount,
            ];

            $response = $this->makeCaptureRequest($request);

            $payment->setIsTransactionClosed(1);

        } catch (\Exception $e) {
            $this->debug($payment->getData(), $e->getMessage());
        }

        return $this;
    }


    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {

            $request = [
                'cc_type' => $payment->getCcType(),
                'cc_exp_month' => $payment->getCcExpMonth(),
                'cc_exp_year' => $payment->getCcExpYear(),
                'cc_number' => $payment->getCcNumberEnc(),
                'amount' => $amount
            ];

            //check if payment has been authorized
            $response = $this->makeAuthRequest($request);

        } catch (\Exception $e) {
            $this->debug($payment->getData(), $e->getMessage());
        }

        if(isset($response['transactionID'])) {
            $payment->setTransactionId($response['transactionID']);
            $payment->setParentTransactionId($response['transactionID']);
        }

        $payment->setIsTransactionClosed(0);

        return $this;
    }

    public function getConfigPaymentAction()
    {
        return self::ACTION_AUTHORIZE_CAPTURE;
    }


    public function makeAuthRequest($request)
    {
        $response = ['transactionId' => 123]; 

        if(!$response) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed auth request.'));
        }

        return $response;
    }

    public function makeCaptureRequest($request)
    {
        $response = ['success']; 

        if(!$response) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed capture request.'));
        }

        return $response;
    }
}