<?php 

namespace CoinCorner\BitcoinCheckout\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;



class CreateBitcoinOrder extends Action
{
    protected $orderFactory;
    protected $payment;
    protected $checkoutSession;
    protected $scopeConfig;
    protected $_eventManager;
    protected $quoteRepository;
    protected $urlBuilder;

    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        Context $context,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder
    ) {

        parent::__construct($context);
        $this->quoteRepository = $quoteRepository;
        $this->_eventManager = $eventManager;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
    }

    public function execute()
    {

        $id = $this->checkoutSession->getLastOrderId();
        $order = $this->orderFactory->create()->load($id);

        if (!$order->getIncrementId()) {
            $this->getResponse()->setBody(json_encode([
                'status' => false,
                'reason' => 'Order Not Found',
            ]));
            return;
        }

        $response = $this->CreateCoinCornerOrder($order);
        $this->getResponse()->setBody(json_encode($response));
    }

    public function CreateCoinCornerOrder($order) 
    {
        try 
        {
            $description_array = [];
            foreach ($order->getAllItems() as $item) {
                $description_array[] = number_format($item->getQtyOrdered(), 0) . ' x ' . $item->getName();
            }
    
            $description = join($description_array, ', ');
    
            $api_key = strtolower($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $api_secret = strtolower($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_api_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $account_id = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_account_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    
            date_default_timezone_set("UTC");
            $date  = date_create();
            $nonce = date_timestamp_get($date);
            $message = strval($nonce) . strval($account_id) . strval($api_key);
            $api_sig = strtolower(hash_hmac('sha256', $message, $api_secret));
    
            $invoice_currency = strtoupper($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_invoicecurrency', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $settle_currency = strtoupper($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_settlecurrency', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));

            $data = array(
                'APIKey' => $api_key,
                'Signature' => $api_sig,
                'Nonce' => $nonce,
                'InvoiceCurrency' => $invoice_currency,
                'InvoiceAmount' => number_format($order->getGrandTotal(), 2, '.', ''),
                'SettleCurrency' => $settle_currency,
                'NotificationURL' => $this->urlBuilder->getUrl('coincorner/payment/callback'),
                'ItemDescription' => substr($description, 0, 255),
                'ItemCode' => '',
                'SuccessRedirectURL' => $this->urlBuilder->getUrl('coincorner/payment/ordersuccess'),
                'FailRedirectURL' => $this->urlBuilder->getUrl('coincorner/payment/cancelorder'),
                'OrderId' => $order->getIncrementId()
            );
            
            $url  = 'https://checkout.coincorner.com/api/CreateOrder';
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
                
                $error_message = $ccresponse["Message"];

                $order_status = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_cancelled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $order->setState($order_status);
                $order->setStatus($order_status);
                $order->addStatusHistoryComment('Payment could not be started, CoinCorner returned an error: ' . $error_message);
                $order->save();
                
                return ['status' => false];
            }
            else {

                $invoice = explode("/Checkout/", $ccresponse);
    
                if (count($invoice) < 2) 
                {

                    $order_status = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_cancelled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                    $order->setState($order_status);
                    $order->setStatus($order_status);
                    $order->addStatusHistoryComment('Payment could not be started, CoinCorner returned an error.');
                    $order->save();

                    return ['status' => false];
                } 
                else 
                {
                    $order_status = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_pending_payment', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                    $order->setState($order_status);
                    $order->setStatus($order_status);
                    $order->addStatusHistoryComment('Bitcoin order created, waiting for payment.');
                    $order->save();
        
                    return ['status' => true, 'payment_url' =>  $ccresponse];
                }

            }
        }
        catch(Exception $e) 
        {
            return ['status' => false];
        }
        
    }
    
}