<?php

namespace CoinCorner\BitcoinCheckout\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;



class PreOrderCheck extends Action
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
        $response = ['status' => false];

        $api_key = strtolower($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $api_secret = strtolower($this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_api_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $account_id = $this->scopeConfig->getValue('payment/coincorner_bitcoincheckout/coincorner_account_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if(strlen($api_key) > 0 && strlen($api_secret) > 0 && strlen($account_id) > 0) 
        {
            $response['status'] = true;
        }

        $this->getResponse()->setBody(json_encode($response));
    }

    
}