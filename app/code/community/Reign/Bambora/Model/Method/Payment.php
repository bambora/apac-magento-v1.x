<?php
/**
 * @author    Reign <hello@reign.com.au>
 * @version   1.0
 * @copyright Copyright (c) 2017 Reign. All rights reserved.
 * @copyright Copyright (c) 2017 Bambora. All rights reserved.
 * @license   Proprietary/Closed Source
 * By viewing, using, or actively developing this application in any way, you are
 * henceforth bound the license agreement, and all of its changes, set forth by
 * Reign and Bambora. The license can be found, in its entirety, at this address:
 * http://www.reign.com.au/magento-licence
 */

 
class Reign_Bambora_Model_Method_Payment extends Mage_Payment_Model_Method_Abstract
{
    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = false;
    protected $_canRefundInvoicePartial     = false;
    protected $_canVoid                     = false;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = true;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = false;
    protected $_canReviewPayment            = false;
    protected $_canCreateBillingAgreement   = false;
    protected $_canManageRecurringProfiles  = false;
    protected $_connectionType;
    protected $_isBackendOrder;

    protected $_code = "bambora";
    protected $_formBlockType = 'bambora/form_paymentapi_cc';
    protected $_infoBlockType = 'bambora/info_paymentapi';
    protected $_api_username = '';
    protected $_api_password = '';
    
    
    /**
     * Map Bambora payment action to Magento payment action
     *
     * @see Mage_Sales_Model_Payment::place()
     * @return string
     */
    public function getConfigPaymentAction()
    {
        switch ($this->getConfigData('payment_action')) {
            case Reign_Bambora_Helper_BamboraConstant::CC_AUTH:
                return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
            case Reign_Bambora_Helper_BamboraConstant::CC_PURCHASE:
                return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
        }
    }    
    
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        
        $info = $this->getInfoInstance();
        
        $info->setCcType($data->getCcType())
                ->setCcOwner($data->getCcOwner())
                ->setCcLast4(substr($data->getCcNumber(), -4))
                ->setCcNumber($data->getCcNumber())
                ->setCcCid($data->getCcCid())
                ->setCcExpMonth($data->getCcExpMonth())
                ->setCcExpYear($data->getCcExpYear());
                
        return $this;
    }

    /**
     * Capture payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Reign_Bambora_Model_Method_Payment
     */    
    public function capture(Varien_Object $payment, $amount)
    {        
        //$receiptNumber = $payment->getParentTransactionId();
        $receiptNumber = $payment->getLastTransId();    
        if ($receiptNumber) {
            // Preauthorisation exists so capture it
            $paymentAction = 'Capture';
            $response = $this->_SingleCaptureRequest($receiptNumber, $amount);
        } else {
            // No preauthorisation so submit the purchase
            $paymentAction = 'Purchase';
            $response = $this->_SubmitSinglePayment($payment, $amount, Reign_Bambora_Helper_BamboraConstant::CC_PURCHASE);
        }

        $responseCode = isset($response->ResponseCode) ? $response->ResponseCode : '';
        if ($responseCode == Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE) {
            // Create transaction against order with receipt number            
            $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0);             
        }
        
        /* Debug mode and exception logging */        
        if(Mage::getStoreConfig('payment/bambora/debug_mode') || $responseCode != Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE || $responseCode == '') 
        {
            $timestamp = (isset($response->Timestamp)) ? $response->Timestamp : '';      
            $declinedCode = (isset($response->DeclinedCode)) ? $response->DeclinedCode : '';
            $declinedMsg = (isset($response->DeclinedMessage)) ? $response->DeclinedMessage : '';
            $currencyCode = $payment->getOrder()->getOrderCurrencyCode();
            $orderId = $payment->getOrder()->getIncrementId(); 
            $receiptNo = (isset($response->Receipt)) ? $response->Receipt : '';
            $cardNo = "XXXX-" . substr($payment->getCcNumber(), -4); // last 4 digits only
            $cardExp = $payment->getCcExpMonth() . "/" . $payment->getCcExpYear();
            $cardHolderName = $payment->getCcOwner();
            // $cardCcv = $payment->getCcCid(); 
            $paymentApiMode = Mage::getStoreConfig('payment/bambora/mode');
            $AccountNumber = Mage::getStoreConfig('payment/bambora/account_number');            
            
            $message  = "Timestamp: " . $timestamp;                        
            $message .= " Response Code: " . $responseCode;
            $message .= " Declined Code: " . $declinedCode;  
            $message .= " Declined Message: " . $declinedMsg;
            $message .= " Currency: " . $currencyCode;
            $message .= " Payment Action: " . $paymentAction; 
            $message .= " Amount: " . $amount;
            $message .= " Receipt #: " . $receiptNo;
            $message .= " Card Number: " . $cardNo;
            $message .= " Expiry: " . $cardExp;
            $message .= " Card Holder Name: " . $cardHolderName;
            $message .= " Magento Order #: " . $orderId;
            $message .= " Account Number: " . $AccountNumber;            
            $message .= " Payment API Mode: " . $paymentApiMode;
            
            if(Mage::getStoreConfig('payment/bambora/debug_mode')) {
                // Log all messages in debug mode
                Mage::helper('bambora')->logDebug($message);                
            }
            
            if($responseCode != Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE || $responseCode == '') {
                Mage::helper('bambora')->logException($message);
                // Throw exception if response is not approved
                if(Mage::getStoreConfig('payment/bambora/mode') == 'sandbox') {
                    Mage::throwException(Reign_Bambora_Helper_BamboraConstant::TRANSACTION_SANDBOX_ERROR_MESSAGE . ": " . $declinedCode . " " . $declinedMsg);
                } else {
                    Mage::throwException(Reign_Bambora_Helper_BamboraConstant::TRANSACTION_LIVE_ERROR_MESSAGE);
                }                
            }
            
        }        
        
        return $this;
    }
 
    /**
     * Authorise payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Reign_Bambora_Model_Method_Payment
     */    
    public function authorize(Varien_Object $payment, $amount)
    {
        $orderId = $payment->getOrder()->getIncrementId(); 
        $paymentAction = Reign_Bambora_Helper_BamboraConstant::CC_AUTH;        
        $response = $this->_SubmitSinglePayment($payment, $amount, $paymentAction);        
        $responseCode = (isset($response->ResponseCode)) ? $response->ResponseCode : '';
        
        if($responseCode == Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE) {
            // Create transaction against order with receipt number
            $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0);   
        }
        
        /* Debug mode and exception logging */        
        if(Mage::getStoreConfig('payment/bambora/debug_mode') || $responseCode != Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE || $responseCode == '') 
        {
            $timestamp = (isset($response->Timestamp)) ? $response->Timestamp : '';      
            $declinedCode = (isset($response->DeclinedCode)) ? $response->DeclinedCode : '';
            $declinedMsg = (isset($response->DeclinedMessage)) ? $response->DeclinedMessage : '';
            $currencyCode = $payment->getOrder()->getOrderCurrencyCode();
            // $paymentAction = Mage::getStoreConfig('payment/bambora/payment_action');     
            $receiptNo = (isset($response->Receipt)) ? $response->Receipt : '';
            $cardNo = "XXXX-" . substr($payment->getCcNumber(), -4); // last 4 digits only
            $cardExp = $payment->getCcExpMonth() . "/" . $payment->getCcExpYear();
            $cardHolderName = $payment->getCcOwner();
            // $cardCcv = $payment->getCcCid(); 
            $paymentApiMode = Mage::getStoreConfig('payment/bambora/mode');
            $AccountNumber = Mage::getStoreConfig('payment/bambora/account_number');
            
            $message  = "Timestamp: " . $timestamp;                        
            $message .= " Response Code: " . $responseCode;
            $message .= " Declined Code: " . $declinedCode;  
            $message .= " Declined Message: " . $declinedMsg;
            $message .= " Currency: " . $currencyCode;
            $message .= " Payment Action: " . $paymentAction; 
            $message .= " Amount: " . $amount;
            $message .= " Receipt #: " . $receiptNo;
            $message .= " Card Number: " . $cardNo;
            $message .= " Expiry: " . $cardExp;
            $message .= " Card Holder Name: " . $cardHolderName;
            $message .= " Magento Order #: " . $orderId;
            $message .= " Account Number: " . $AccountNumber;
            $message .= " Payment API Mode: " . $paymentApiMode;          
            
            if(Mage::getStoreConfig('payment/bambora/debug_mode')) {
                // Log all messages in debug mode
                Mage::helper('bambora')->logDebug($message);                
            } 
            
            if($responseCode != Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE || $responseCode == '') {
                // Throw exception if response is not approved
                Mage::helper('bambora')->logException($message);
                if(Mage::getStoreConfig('payment/bambora/mode') == 'sandbox') {
                    Mage::throwException(Reign_Bambora_Helper_BamboraConstant::TRANSACTION_SANDBOX_ERROR_MESSAGE . ": " . $declinedCode . " " . $declinedMsg);
                } else {
                    Mage::throwException(Reign_Bambora_Helper_BamboraConstant::TRANSACTION_LIVE_ERROR_MESSAGE);
                }   
            }
            
        }

        return $this;
    }

    /**
     * Submit single capture request (i.e. complete a preauthorisation) to gateway 
     * and return response
     */     
    protected function _SingleCaptureRequest($receiptNumber, $amount){
        $this->_getAPICred();
        
        $AmountCents = $amount * 100;
        $UserName = $this->_api_username;
        $Password = $this->_api_password;
        
        $soap_request  = '<dts:SubmitSingleCapture>';
        $soap_request .= '<dts:trnXML>';
        $soap_request .= '<![CDATA[';
        $soap_request .= '<Capture>';
        $soap_request .= '<Receipt>' . $receiptNumber . '</Receipt>';
        $soap_request .= '<Amount>' . $AmountCents . '</Amount>';
        $soap_request .= '<Security>';
        $soap_request .= '<UserName>' . $UserName . '</UserName>';
        $soap_request .= '<Password>' . $Password . '</Password>';
        $soap_request .= '</Security>';
        $soap_request .= '</Capture>';
        $soap_request .= ']]>';    
        $soap_request .= '</dts:trnXML>';
        $soap_request .= '</dts:SubmitSingleCapture>'; 
        
        $xml = $this->_doAPI($soap_request);
        
        $xml_array = (array) $xml->SubmitSingleCaptureResponse->SubmitSingleCaptureResult;
        $response = isset($xml_array[0]) ? simplexml_load_string($xml_array[0]) : null; 
        return $response; 
    }
    
    /**
     * Submit single payment request to gateway and return response
     */ 
    protected function _SubmitSinglePayment($payment, $amount, $paymentAction)
    {
        $this->_getAPICred();
        
        $orderId = $payment->getOrder()->getIncrementId();
        
        if(Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $CustNumber = $customerData->getId();
        }else{
            $CustNumber = '';
        }
        
        $CustRef = $orderId;
        $AmountCents = $amount * 100;
        $CardNumber = $payment->getCcNumber();
        $ExpM = $payment->getCcExpMonth();
        $ExpY = $payment->getCcExpYear();
        $CVN = $payment->getCcCid();
        $CardHolderName = $payment->getCcOwner();
        $UserName = $this->_api_username;
        $Password = $this->_api_password;
        $AccountNumber = Mage::getStoreConfig('payment/bambora/account_number');
        $TrnType = $paymentAction;         

        $soap_request  = '  <dts:SubmitSinglePayment>';
        $soap_request .= '     <!--Optional:-->';
        $soap_request .= '     <dts:trnXML>';
        $soap_request .= '     <![CDATA[';
        $soap_request .= '      <Transaction>';
        $soap_request .= '        <CustNumber>' . $CustNumber . '</CustNumber>';
        $soap_request .= '        <CustRef>' . $CustRef . '</CustRef>';
        $soap_request .= '        <Amount>' . $AmountCents . '</Amount>';
        $soap_request .= '        <TrnType>' . $TrnType . '</TrnType>';
        $soap_request .= '        <AccountNumber>' . $AccountNumber . '</AccountNumber>';
        $soap_request .= '        <CreditCard Registered="False">';
        $soap_request .= '                <CardNumber>' . $CardNumber . '</CardNumber>';
        $soap_request .= '                <ExpM>' . $ExpM . '</ExpM>';
        $soap_request .= '                <ExpY>' . $ExpY . '</ExpY>';
        $soap_request .= '                <CVN>' . $CVN . '</CVN>';
        $soap_request .= '                <CardHolderName>' . $CardHolderName . '</CardHolderName>';
        $soap_request .= '        </CreditCard>';
        $soap_request .= '        <Security>';
        $soap_request .= '                <UserName>' . $UserName . '</UserName>';
        $soap_request .= '                <Password>' . $Password . '</Password>';
        $soap_request .= '        </Security>';
        $soap_request .= '        </Transaction>';
        $soap_request .= '      ]]>';
        $soap_request .= '     </dts:trnXML>';
        $soap_request .= '  </dts:SubmitSinglePayment>';     
        
        $xml = $this->_doAPI($soap_request);
        
        $xml_array = (array) $xml->SubmitSinglePaymentResponse->SubmitSinglePaymentResult;
        $response = isset($xml_array[0]) ? simplexml_load_string($xml_array[0]) : null;         

        return $response;
    }
       
    protected function _getAPICred()
    {
        if(Mage::getStoreConfig('payment/bambora/mode') == 'sandbox'){
            $this->_api_username = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora/sandbox_api_key'));
            $this->_api_password = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora/sandbox_api_password'));
        } else {
            $this->_api_username = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora/live_api_key'));
            $this->_api_password = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora/live_api_password'));
        }
    }

    protected function _doAPI($request)
    {        
        if(Mage::getStoreConfig('payment/bambora/mode') == 'sandbox'){
            $url = Reign_Bambora_Helper_BamboraConstant::SANDBOX_ENDPOINT;
        } else {
            $url = Reign_Bambora_Helper_BamboraConstant::LIVE_ENDPOINT;
        }
                
        $soap_request  = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dts="http://www.ippayments.com.au/interface/api/dts">';
        $soap_request .= '<soapenv:Header/>';
        $soap_request .= '<soapenv:Body>';
        $soap_request .= $request;
        $soap_request .= '</soapenv:Body>';
        $soap_request .= '</soapenv:Envelope>';
        
        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: ".strlen($soap_request),
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, Reign_Bambora_Helper_BamboraConstant::API_TIMEOUT);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_request);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, Mage::getSingleton('bambora/config')->isEnableSSLVerification());    
        $response_curl =  curl_exec($ch);
        $r1 = str_replace("<soap:Body>","",$response_curl);
        $r2 = str_replace("</soap:Body>","",$r1); 
        $xml = simplexml_load_string($r2);
        curl_close($ch);        
        return $xml;
    }

}