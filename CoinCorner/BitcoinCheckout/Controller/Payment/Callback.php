<?php

namespace CoinCorner\BitcoinCheckout\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\OrderFactory;


class Callback extends Action
{
    protected $order;
    protected $scopeConfig;
    protected $request;
    protected $orderFactory;


    public function __construct(
        Context $context,
        Order $order,
        ScopeConfigInterface $scopeConfig,
        RequestInterface $request,
        OrderFactory $orderFactory
    ) {

        parent::__construct($context);
        $this->order = $order;
        $this->scopeConfig = $scopeConfig;
        $this->request = $request;
        $this->orderFactory = $orderFactory;

        $this->execute();
    }

    
    public function execute()
    {

        try {

            $postData = json_decode(file_get_contents("php://input"), true);

            $post_api_key = strtolower(filter_var($postData['APIKey'], FILTER_SANITIZE_STRING));
            $post_order_id = filter_var($postData['OrderId'], FILTER_SANITIZE_STRING);

            $merchant_api_key = strtolower($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $merchant_order = $this->orderFactory->create()->load($post_order_id);

            if((strcmp($post_api_key, $merchant_api_key) == 0) && (!is_null($merchant_order))) 
            {

                $api_key = strtolower($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
                $api_secret = strtolower($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_api_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
                $account_id = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_account_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        

                date_default_timezone_set("UTC");
                $date  = date_create();
                $nonce = date_timestamp_get($date);
                $message = strval($nonce) . strval($account_id) . strval($api_key);
                $api_sig = strtolower(hash_hmac('sha256', $message, $api_secret));
        
                $data = array(
                    'APIKey' => $api_key,
                    'Signature' => $api_sig,
                    'Nonce' => $nonce,
                    'OrderId' => $merchant_order->getIncrementId()
                );
    
                $url  = 'https://checkout.coincorner.com/api/CheckOrder';
                $curl = curl_init();
                $curl_options = array(CURLOPT_RETURNTRANSFER => 1,CURLOPT_URL  => $url);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                array_merge($curl_options, array(CURLOPT_POST => 1));
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

                curl_setopt_array($curl, $curl_options);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

                $ccresponse = json_decode(curl_exec($curl), TRUE);
                $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if($http_status != 200) {

                    http_response_code(400);
                    $this->getResponse()->setBody('FAIL');
                }
                else {


                    switch ($ccresponse["OrderStatusText"]) 
                    {
                        case 'Complete':
                            $order_status = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_complete', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                            $merchant_order->setState($order_status);
                            $merchant_order->setStatus($order_status);
                            $merchant_order->addStatusHistoryComment('Payment is confirmed on the network, and has been credited to the merchant.');
                            break;
                        case 'Pending Confirmation':
                            $order_status = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_pending_confirmation', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                            $merchant_order->setState($order_status);
                            $merchant_order->setStatus($order_status);
                            $merchant_order->addStatusHistoryComment('Payment has been made, pending confirmation.');
                            break;
                        case 'Expired':
                            $order_status = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_cancelled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                            $merchant_order->setState($order_status);
                            $merchant_order->setStatus($order_status);
                            $merchant_order->addStatusHistoryComment('Buyer did not pay within the required time and the invoice expired.');
                            break;
                        case 'Cancelled':
                            $order_status = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_cancelled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                            $merchant_order->setState($order_status);
                            $merchant_order->setStatus($order_status);
                            $merchant_order->addStatusHistoryComment('This order has been canceled.');
                            break;
                        case 'Refunded':
                            $order_status = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_refunded', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                            $merchant_order->setState($order_status);
                            $merchant_order->setStatus($order_status);
                            $merchant_order->addStatusHistoryComment('Payment was refunded to the buyer.');
                            break;
                        case 'N/A':
                            break;
                    }
        
                    $merchant_order->save();
                }
            }
        }
        catch (Exception $e) {
            http_response_code(400);
            $this->getResponse()->setBody('FAIL');
        }
       
        $this->getResponse()->setBody('OK');
    }
}