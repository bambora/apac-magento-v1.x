<?php
/**
 * @author    Reign <hello@reign.com.au>
 * @version   1.2.0
 * @copyright Copyright (c) 2017 Reign. All rights reserved.
 * @copyright Copyright (c) 2017 Bambora. All rights reserved.
 * @license   Proprietary/Closed Source
 * By viewing, using, or actively developing this application in any way, you are
 * henceforth bound the license agreement, and all of its changes, set forth by
 * Reign and Bambora. The license can be found, in its entirety, at this address:
 * http://www.reign.com.au/magento-licence
 */

 
class Reign_Bambora_IndexController extends Mage_Core_Controller_Front_Action
{
    
    protected $_apiusername = "";
    protected $_apipassword = "";
    protected $_submiturl = "";
    
    
    public function integratedcheckoutAction()
    {
        $this->getAPICred();
        
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $custnumber = $customerData->getId();
        } else {
            $custnumber = 'guest';
        }
        
        $paymentaction = Mage::getStoreConfig('payment/bambora_integrated/payment_action');
        if ($paymentaction == Reign_Bambora_Helper_BamboraConstant::CC_PURCHASE) {
            $transactiontype = Reign_Bambora_Helper_BamboraConstant::CHECKOUT_V1_PURCHASE;
        } else {
            $transactiontype = Reign_Bambora_Helper_BamboraConstant::CHECKOUT_V1_PREAUTH;
        }
        
        $accountnumber = Mage::getStoreConfig('payment/bambora_integrated/account_number');
        $merchantnumber = Mage::getStoreConfig('payment/bambora_integrated/merchant_number');
        $sessionkey = Mage::getSingleton('core/session')->getFormKey();
        
        $headers = array(
            "Content-type: application/x-www-form-urlencoded",
            "Cache-Control: no-cache",
        );
        
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quoteData= $quote->getData();
        $grandTotal=$quoteData['grand_total'];
        $amount = $grandTotal * 100;
        $serverurl = base64_encode(Mage::getBaseUrl() . 'bambora/index/serverurl');
        $userurl = base64_encode(Mage::getBaseUrl() . 'bambora/index/userurl');
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $postfield  = 'UserName='.$this->_apiusername.'&';
        $postfield .= 'password='.$this->_apipassword.'&';
        $postfield .= 'CustRef='.$quote->getId().'&';    //  !!!! Need to get Magento order #
        $postfield .= 'CustNumber='.$custnumber.'&';   //  !!!! Need to get Magento customer ID or 'guest' for guests
        $postfield .= 'Amount='.$amount.'&';
        $postfield .= 'SessionId=' . $quote->getId() . '&';
        $postfield .= 'SessionKey='.$sessionkey.'&';   //  !!!! Need to get Magento session key
        $postfield .= 'DL='.$transactiontype.'&';
        $postfield .= 'ServerURL='.$serverurl.'&';
        $postfield .= 'UserURL='.$userurl.'&';
        
        if ($accountnumber != "") {
            $postfield .= 'AccountNumber=' . $accountnumber . '&'; 
        }
        
        if ($merchantnumber != "") {
            $postfield .= 'MerchantNumber=' . $merchantnumber . '&';
        }
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->_submiturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfield);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $responsecurl =  curl_exec($ch);
        curl_close($ch);

        Mage::register('submiturl', $this->_submiturl);
        Mage::register('quoteid', $quote->getId());
        
        $this->loadLayout();
        $this->renderLayout(); 
        
        return $this;

    }
    
    public function serverurlAction()
    {
        
        $paymentaction = Mage::getStoreConfig('payment/bambora_integrated/payment_action');
        if ($paymentaction == Reign_Bambora_Helper_BamboraConstant::CC_PURCHASE) {
            $transactiontype = Reign_Bambora_Helper_BamboraConstant::BAMBORA_ORDER_TRANSACTION_CAPTURE;
        } else {
            $transactiontype = Reign_Bambora_Helper_BamboraConstant::BAMBORA_ORDER_TRANSACTION_AUTHORIZATION;
        }
        
        $declinecode = Mage::app()->getRequest()->getPost('DeclinedCode');
        
        if ($declinecode != "") {
            if (Mage::getStoreConfig('payment/bambora_integrated/debug_mode')) {
                $timestamp = Mage::app()->getRequest()->getPost('TxDateTime');      
                $receiptNo = Mage::app()->getRequest()->getPost('Receipt');
                $declinedCode = Mage::app()->getRequest()->getPost('DeclinedCode');
                $declinedMsg = Mage::app()->getRequest()->getPost('DeclinedMessage');
                $cardNo = Mage::app()->getRequest()->getPost('MaskedCard');
                $cardExp = Mage::app()->getRequest()->getPost('ExpiryDate');
                $cardHolderName = Mage::app()->getRequest()->getPost('CardHolderName');
                $paymentApiMode = Mage::getStoreConfig('payment/bambora_integrated/mode');

                $message  = "Timestamp: " . $timestamp;                        
                $message .= " Response Code: " . $responseCode;
                $message .= " Declined Code: " . $declinedCode;  
                $message .= " Declined Message: " . $declinedMsg;
                $message .= " Amount: " . $grandTotal;
                $message .= " Receipt #: " . $receiptNo;
                $message .= " Card Number: " . $cardNo;
                $message .= " Expiry: " . $cardExp;
                $message .= " Card Holder Name: " . $cardHolderName;
                $message .= " Magento Order #: " . $orderId;
                $message .= " Payment API Mode: " . $paymentApiMode;
                
                if (Mage::getStoreConfig('payment/bambora/debug_mode')) {
                    // Log all messages in debug mode
                    Mage::helper('bambora')->logException($message);                
                }                 
            }
            
            return true;
        }
        
   
        
        $cartId = Mage::app()->getRequest()->getPost('SessionId');
        
        $quote = Mage::getModel('sales/quote')->load($cartId);
        $quote->collectTotals()
            ->getPayment()->setMethod('bambora_integrated');

        $service = Mage::getModel('sales/service_quote', $quote);
                    
        $service->submitAll();
        $order = $service->getOrder();
        $payment = $order->getPayment();
        
        $data["CcOwner"] =  Mage::app()->getRequest()->getPost('CardHolderName');
        $data["CcNumber"] = Mage::app()->getRequest()->getPost('MaskedCard');
        $data["CardType"] = Mage::app()->getRequest()->getPost('CardType');
        $data["ExpiryDate"] = Mage::app()->getRequest()->getPost('ExpiryDate');
        $data["CardType"] = Mage::app()->getRequest()->getPost('CardType');
        
        $payment->getMethodInstance()->assignData($data);
        
        $amount = Mage::app()->getStore($order->getStoreId())->roundPrice($order->getBaseTotalDue());
        $payment->setTransactionId(Mage::app()->getRequest()->getPost('Receipt'));
        $transaction = $payment->addTransaction($transactiontype, null, false, "");
        $transaction->setIsClosed(0);
        $transaction->save();
        $payment->save();
        $order->save();     
       
        if (Mage::getStoreConfig('payment/bambora_integrated/debug_mode')) {
            $timestamp = Mage::app()->getRequest()->getPost('TxDateTime');      
            $receiptNo = Mage::app()->getRequest()->getPost('Receipt');
            $declinedCode = Mage::app()->getRequest()->getPost('DeclinedCode');
            $declinedMsg = Mage::app()->getRequest()->getPost('DeclinedMessage');
            $cardNo = Mage::app()->getRequest()->getPost('MaskedCard');
            $cardExp = Mage::app()->getRequest()->getPost('ExpiryDate');
            $cardHolderName = Mage::app()->getRequest()->getPost('CardHolderName');
            $paymentApiMode = Mage::getStoreConfig('payment/bambora_integrated/mode');

            $message  = "Timestamp: " . $timestamp;                        
            $message .= " Response Code: " . $responseCode;
            $message .= " Declined Code: " . $declinedCode;  
            $message .= " Declined Message: " . $declinedMsg;
            $message .= " Amount: " . $amount;
            $message .= " Receipt #: " . $receiptNo;
            $message .= " Card Number: " . $cardNo;
            $message .= " Expiry: " . $cardExp;
            $message .= " Card Holder Name: " . $cardHolderName;
            $message .= " Magento Order #: " . $order->getIncrementId();
            $message .= " Payment API Mode: " . $paymentApiMode;
            
            if (Mage::getStoreConfig('payment/bambora/debug_mode')) {
                // Log all messages in debug mode
                Mage::helper('bambora')->logDebug($message);                
            }                 
        }     
        
        if (Mage::getStoreConfig('payment/bambora_integrated/payment_action') == Reign_Bambora_Helper_BamboraConstant::CC_PURCHASE &&
            Mage::getStoreConfig('payment/bambora_integrated/order_status') != "pending") {
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->register();
           
            $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
             
            $invoice->sendEmail(true, '');
            $transactionSave->save();
        }
       
    }

    
    public function userurlAction()
    {
        $message = "";
        $errormessage = "";
        $quotecheck = Mage::getSingleton('checkout/session')->getQuote();
        
        $orders = Mage::getModel('sales/order')->getCollection()
             ->setOrder('created_at', 'DESC')
             ->setPageSize(1)
             ->setCurPage(1);
        $orderId = $orders->getFirstItem()->getEntityId();
        $order = Mage::getModel("sales/order")->load($orderId);
        
        
        $sst = Mage::app()->getRequest()->getPost('SST');
        $sessionid = Mage::app()->getRequest()->getPost('SessionId');
        $errormessage = $this->queryTransaction($sst, $sessionid);
        
        if ($errormessage != "") {            
            if (Mage::getStoreConfig('payment/bambora_integrated/mode') == 'sandbox') { 
                $message = Reign_Bambora_Helper_BamboraConstant::TRANSACTION_SANDBOX_ERROR_MESSAGE . ": " . $errormessage;
            } else {
                $message = Reign_Bambora_Helper_BamboraConstant::TRANSACTION_LIVE_ERROR_MESSAGE;
            }   
            
            Mage::register('errormessage', $message);

            $this->loadLayout();
            $this->renderLayout();
        
            return $this;
        }
        
        $cart = Mage::getModel('checkout/cart');                
        $cart->truncate()->save(); // remove all active items in cart page
        $cart->init();
        $session= Mage::getSingleton('checkout/session');
        $quote = $session->getQuote();
        $cart = Mage::getModel('checkout/cart');
        $cartItems = $cart->getItems();
        
        foreach ($cartItems as $item) {
            $quote->removeItem($item->getId())->save();
        }
        
        Mage::getSingleton('checkout/session')->clear();
        
        Mage::getSingleton('checkout/type_onepage')->getCheckout()
            ->setLastOrderId($order->getId())
            ->setLastSuccessQuoteId($order->getQuoteId())
            ->setLastQuoteId($order->getQuoteId());
            
        Mage::register('errormessage', $message);
        $this->loadLayout();
        $this->renderLayout();         
        
        return $this;
    }
    
    protected function getAPICred()
    {
        if (Mage::getStoreConfig('payment/bambora_integrated/mode') == 'live') {
            $this->_apiusername = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora_integrated/live_api_key'));
            $this->_apipassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora_integrated/live_api_password'));
            $this->_submiturl = Reign_Bambora_Helper_BamboraConstant::LIVE_INTEGRATED_CHECKOUT_URL;
        } else {
            $this->_apiusername = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora_integrated/sandbox_api_key'));
            $this->_apipassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora_integrated/sandbox_api_password'));
            $this->_submiturl = Reign_Bambora_Helper_BamboraConstant::SANDBOX_INTEGRATED_CHECKOUT_URL;
        }
        
    }
    
    protected function queryTransaction($sst, $sessionid)
    {
        
        $this->getAPICred();
        
        $headers = array(
            "Content-type: application/x-www-form-urlencoded",
            "Cache-Control: no-cache",
        );

        $postfield = 'UserName='.$this->_apiusername.'&';
        $postfield .= 'Password='.$this->_apipassword.'&';
        $postfield .= 'SST='.$sst.'&';
        $postfield .= 'SessionId='.$sessionid.'&';
        $postfield .= 'Query=true';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->_submiturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfield);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $responsecurl =  curl_exec($ch);
        curl_close($ch);
 
        $string = $responsecurl;
        $result = preg_match('/<input type="hidden" name="DeclinedMessage" value="(.*?)"/', $string, $matches);
        $resultsecond = preg_match('/<input type="hidden" name="DeclinedCode" value="(.*?)"/', $string, $matchesarray);
        $resultresponse = preg_match('/<input type="hidden" name="Result" value="(.*?)"/', $string, $resultresponsearray);
        if ($resultresponsearray[1] == "0") {
            return $matchesarray[1] . " " .str_replace("+", " ", $matches[1]);
        } else {
            return "";  
        }
    }
    

}