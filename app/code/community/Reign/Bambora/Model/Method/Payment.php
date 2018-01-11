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

 
class Reign_Bambora_Model_Method_Payment extends Mage_Payment_Model_Method_Abstract
{
    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
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
        $receiptNumber = $payment->getLastTransId();    
        if ($receiptNumber) {
            // Preauthorisation exists so capture it
            $paymentaction = 'Capture';
            $response = $this->_SingleCaptureRequest($receiptNumber, $amount);
        } else {
            // No preauthorisation so submit the purchase
            $paymentaction = 'Purchase';
            $response = $this->_SubmitSinglePayment($payment, $amount, Reign_Bambora_Helper_BamboraConstant::CC_PURCHASE);
        }

        $responseCode = isset($response->ResponseCode) ? $response->ResponseCode : '';
        if ($responseCode == Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE) {
            // Create transaction against order with receipt number            
            $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0);             
        }
        
        /* Debug mode and exception logging */        
        if (Mage::getStoreConfig('payment/bambora/debug_mode') || $responseCode != Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE || $responseCode == '') {
            $timestamp = (isset($response->Timestamp)) ? $response->Timestamp : '';      
            $declinedCode = (isset($response->DeclinedCode)) ? $response->DeclinedCode : '';
            $declinedMsg = (isset($response->DeclinedMessage)) ? $response->DeclinedMessage : '';
            $currencyCode = $payment->getOrder()->getOrderCurrencyCode();
            $orderId = $payment->getOrder()->getIncrementId(); 
            $receiptNo = (isset($response->Receipt)) ? $response->Receipt : '';
            $cardNo = "XXXX-" . substr($payment->getCcNumber(), -4); // last 4 digits only
            $cardExp = $payment->getCcExpMonth() . "/" . $payment->getCcExpYear();
            $cardholdername = $payment->getCcOwner();
            // $cardCcv = $payment->getCcCid(); 
            $paymentApiMode = Mage::getStoreConfig('payment/bambora/mode');
            $AccountNumber = Mage::getStoreConfig('payment/bambora/account_number');            
            
            $message  = "Timestamp: " . $timestamp;                        
            $message .= " Response Code: " . $responseCode;
            $message .= " Declined Code: " . $declinedCode;  
            $message .= " Declined Message: " . $declinedMsg;
            $message .= " Currency: " . $currencyCode;
            $message .= " Payment Action: " . $paymentaction; 
            $message .= " Amount: " . $amount;
            $message .= " Receipt #: " . $receiptNo;
            $message .= " Card Number: " . $cardNo;
            $message .= " Expiry: " . $cardExp;
            $message .= " Card Holder Name: " . $cardholdername;
            $message .= " Magento Order #: " . $orderId;
            $message .= " Account Number: " . $AccountNumber;            
            $message .= " Payment API Mode: " . $paymentApiMode;
            
            if (Mage::getStoreConfig('payment/bambora/debug_mode')) {
                // Log all messages in debug mode
                Mage::helper('bambora')->logDebug($message);                
            }
            
            if ($responseCode != Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE || $responseCode == '') {
                Mage::helper('bambora')->logException($message);
                // Throw exception if response is not approved
                if (Mage::getStoreConfig('payment/bambora/mode') == 'sandbox') {
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
        $paymentaction = Reign_Bambora_Helper_BamboraConstant::CC_AUTH;        
        $response = $this->_SubmitSinglePayment($payment, $amount, $paymentaction);        
        $responseCode = (isset($response->ResponseCode)) ? $response->ResponseCode : '';
        
        if ($responseCode == Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE) {
            // Create transaction against order with receipt number
            $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0);   
        }
        
        /* Debug mode and exception logging */        
        if (Mage::getStoreConfig('payment/bambora/debug_mode') || $responseCode != Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE || $responseCode == '') {
            $timestamp = (isset($response->Timestamp)) ? $response->Timestamp : '';      
            $declinedCode = (isset($response->DeclinedCode)) ? $response->DeclinedCode : '';
            $declinedMsg = (isset($response->DeclinedMessage)) ? $response->DeclinedMessage : '';
            $currencyCode = $payment->getOrder()->getOrderCurrencyCode();
            // $paymentaction = Mage::getStoreConfig('payment/bambora/payment_action');     
            $receiptNo = (isset($response->Receipt)) ? $response->Receipt : '';
            $cardNo = "XXXX-" . substr($payment->getCcNumber(), -4); // last 4 digits only
            $cardExp = $payment->getCcExpMonth() . "/" . $payment->getCcExpYear();
            $cardholdername = $payment->getCcOwner();
            // $cardCcv = $payment->getCcCid(); 
            $paymentApiMode = Mage::getStoreConfig('payment/bambora/mode');
            $AccountNumber = Mage::getStoreConfig('payment/bambora/account_number');
            
            $message  = "Timestamp: " . $timestamp;                        
            $message .= " Response Code: " . $responseCode;
            $message .= " Declined Code: " . $declinedCode;  
            $message .= " Declined Message: " . $declinedMsg;
            $message .= " Currency: " . $currencyCode;
            $message .= " Payment Action: " . $paymentaction; 
            $message .= " Amount: " . $amount;
            $message .= " Receipt #: " . $receiptNo;
            $message .= " Card Number: " . $cardNo;
            $message .= " Expiry: " . $cardExp;
            $message .= " Card Holder Name: " . $cardholdername;
            $message .= " Magento Order #: " . $orderId;
            $message .= " Account Number: " . $AccountNumber;
            $message .= " Payment API Mode: " . $paymentApiMode;          
            
            if (Mage::getStoreConfig('payment/bambora/debug_mode')) {
                // Log all messages in debug mode
                Mage::helper('bambora')->logDebug($message);                
            } 
            
            if ($responseCode != Reign_Bambora_Helper_BamboraConstant::APPROVED_RESPONSE_CODE || $responseCode == '') {
                // Throw exception if response is not approved
                Mage::helper('bambora')->logException($message);
                if (Mage::getStoreConfig('payment/bambora/mode') == 'sandbox') {
                    Mage::throwException(Reign_Bambora_Helper_BamboraConstant::TRANSACTION_SANDBOX_ERROR_MESSAGE . ": " . $declinedCode . " " . $declinedMsg);
                } else {
                    Mage::throwException(Reign_Bambora_Helper_BamboraConstant::TRANSACTION_LIVE_ERROR_MESSAGE);
                }   
            }
        }

        return $this;
    }

    public function refund(Varien_Object $payment, $amount){
        
        $this->_getAPICred();
        
        $amountcents = $amount * 100;
        $username = $this->_apiusername;
        $password = $this->_apipassword;
        
        $receiptNumber = $payment->getLastTransId();

        $soaprequest  = ' <dts:SubmitSingleRefund>';
        $soaprequest .= '     <!--Optional:-->';
        $soaprequest .= '     <dts:trnXML>';
        $soaprequest .= '     <![CDATA[';
        $soaprequest .= '     <Refund>';
        $soaprequest .= '         <Receipt>'.$receiptNumber .'</Receipt>';
        $soaprequest .= '         <Amount>' . $amountcents . '</Amount>';
        $soaprequest .= '         <Security>';
        $soaprequest .= '             <UserName>'. $username . '</UserName>';
        $soaprequest .= '             <Password>'. $password . '</Password>';
        $soaprequest .= '          </Security> ';
        $soaprequest .= '     </Refund>';
        $soaprequest .= '     ]]>';
        $soaprequest .= '     </dts:trnXML>';
        $soaprequest .= ' </dts:SubmitSingleRefund>';
                   
        $xml = $this->_doAPI($soaprequest);
        
        $xmlarray = (array) $xml->SubmitSingleRefundResponse->SubmitSingleRefundResult;
        $response = isset($xmlarray[0]) ? simplexml_load_string($xmlarray[0]) : null; 
 
        if($response->ResponseCode != 0){
            
            /*
            [ResponseCode] => 0
            [Timestamp] => 21-Aug-2017 17:33:06
            [Receipt] => 91386244
            [SettlementDate] => SimpleXMLElement Object
            [DeclinedCode] => SimpleXMLElement Object
            [DeclinedMessage] => SimpleXMLElement Object
            */
            
            $errorMsg = $this->_getHelper()->__('Error Processing the request: ' . $response->DeclinedMessage);

            $message  = "ResponseCode: " . $response->ResponseCode;                        
            $message .= "Timestamp: " . $response->Timestamp;
            $message .= "Receipt: " . $response->Receipt;  
            $message .= "SettlementDate: " . $response->SettlementDate;
            $message .= "DeclinedCode" . $response->DeclinedCode;
            $message .= "DeclinedMessage: " . $response->DeclinedMessage; 
            
            if (Mage::getStoreConfig('payment/bambora/debug_mode')) {
                // Log all messages in debug mode
                Mage::helper('bambora')->logDebug($message);                
            } 
            
            Mage::throwException($errorMsg);
        }
        
        $message  = "ResponseCode: " . $response->ResponseCode;                        
        $message .= "Timestamp: " . $response->Timestamp;
        $message .= "Receipt: " . $response->Receipt;  
        $message .= "SettlementDate: " . $response->SettlementDate;
        $message .= "DeclinedCode" . $response->DeclinedCode;
        $message .= "DeclinedMessage: " . $response->DeclinedMessage; 
        
        if (Mage::getStoreConfig('payment/bambora/debug_mode')) {
            // Log all messages in debug mode
            Mage::helper('bambora')->logDebug($message);                
        } 
        
        $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0); 
        
        return $this;
 
    }    


    public function void(Varien_Object $payment){
        
        $this->_getAPICred();

        $username = $this->_apiusername;
        $password = $this->_apipassword;
        
        $receiptNumber = $payment->getLastTransId();
        
        
        $amountcents = $payment->getOrder()->getGrandTotal();

        $response = $this->_SingleCaptureRequest($receiptNumber,$amountcents);
        $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0); 

        $soaprequest  = ' <dts:SubmitSingleVoid>';
        $soaprequest .= '     <!--Optional:-->';
        $soaprequest .= '     <dts:trnXML>';
        $soaprequest .= '     <![CDATA[';
        $soaprequest .= '     <Void>';
        $soaprequest .= '         <Receipt>'.$response->Receipt  .'</Receipt>';
        $soaprequest .= '         <Amount>' . $amountcents . '</Amount>';
        $soaprequest .= '         <Security>';
        $soaprequest .= '             <UserName>'. $username . '</UserName>';
        $soaprequest .= '             <Password>'. $password . '</Password>';
        $soaprequest .= '          </Security> ';
        $soaprequest .= '     </Void>';
        $soaprequest .= '     ]]>';
        $soaprequest .= '     </dts:trnXML>';
        $soaprequest .= ' </dts:SubmitSingleVoid>';

        //Mage::throwException("error" .   $response->Receipt .":::". $amountcents . ":::" . $receiptNumber);
                   
        $xml = $this->_doAPI($soaprequest);
        
        $xmlarray = (array) $xml->SubmitSingleVoidResponse->SubmitSingleVoidResult;
        $response = isset($xmlarray[0]) ? simplexml_load_string($xmlarray[0]) : null; 
 
        if($response->ResponseCode != 0){
            
            /*
            [ResponseCode] => 0
            [Timestamp] => 21-Aug-2017 17:33:06
            [Receipt] => 91386244
            [SettlementDate] => SimpleXMLElement Object
            [DeclinedCode] => SimpleXMLElement Object
            [DeclinedMessage] => SimpleXMLElement Object
            */
            
            $errorMsg = $this->_getHelper()->__('Error Processing the request: ' . $response->DeclinedMessage);
            
            $message  = "ResponseCode: " . $response->ResponseCode;                        
            $message .= "Timestamp: " . $response->Timestamp;
            $message .= "Receipt: " . $response->Receipt;  
            $message .= "SettlementDate: " . $response->SettlementDate;
            $message .= "DeclinedCode" . $response->DeclinedCode;
            $message .= "DeclinedMessage: " . $response->DeclinedMessage; 
            
            
            if (Mage::getStoreConfig('payment/bambora/debug_mode')) {
                // Log all messages in debug mode
                Mage::helper('bambora')->logDebug($message);                
            } 
        
            Mage::throwException($errorMsg);
        }
        
        $message  = "ResponseCode: " . $response->ResponseCode;                        
        $message .= "Timestamp: " . $response->Timestamp;
        $message .= "Receipt: " . $response->Receipt;  
        $message .= "SettlementDate: " . $response->SettlementDate;
        $message .= "DeclinedCode" . $response->DeclinedCode;
        $message .= "DeclinedMessage: " . $response->DeclinedMessage; 
        
        
        if (Mage::getStoreConfig('payment/bambora/debug_mode')) {
            // Log all messages in debug mode
            Mage::helper('bambora')->logDebug($message);                
        } 
        
        $orderTransaction = $payment->lookupTransaction(
            false, Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
        );
        
        
        
        //if ($orderTransaction) {
        //$payment->setParentTransactionId($orderTransaction->getTxnId());
        //$payment->setTransactionId(9999);
        //}
      
        return $this;
 
    }    


    public function cancel(Varien_Object $payment)
    {
        return $this->void($payment);
    }
    
    /**
     * Submit single capture request (i.e. complete a preauthorisation) to gateway 
     * and return response
     */     
    protected function _SingleCaptureRequest($receiptNumber, $amount)
    {
        $this->_getAPICred();
        
        $amountcents = $amount * 100;
        $username = $this->_apiusername;
        $password = $this->_apipassword;
        
        $soaprequest  = '<dts:SubmitSingleCapture>';
        $soaprequest .= '<dts:trnXML>';
        $soaprequest .= '<![CDATA[';
        $soaprequest .= '<Capture>';
        $soaprequest .= '<Receipt>' . $receiptNumber . '</Receipt>';
        $soaprequest .= '<Amount>' . $amountcents . '</Amount>';
        $soaprequest .= '<Security>';
        $soaprequest .= '<UserName>' . $username . '</UserName>';
        $soaprequest .= '<Password>' . $password . '</Password>';
        $soaprequest .= '</Security>';
        $soaprequest .= '</Capture>';
        $soaprequest .= ']]>';    
        $soaprequest .= '</dts:trnXML>';
        $soaprequest .= '</dts:SubmitSingleCapture>';
        
        $xml = $this->_doAPI($soaprequest);
        
        $xmlarray = (array) $xml->SubmitSingleCaptureResponse->SubmitSingleCaptureResult;
        $response = isset($xmlarray[0]) ? simplexml_load_string($xmlarray[0]) : null; 

        return $response; 
    }
    
    /**
     * Submit single payment request to gateway and return response
     */ 
    protected function _SubmitSinglePayment($payment, $amount, $paymentaction)
    {
        $this->_getAPICred();
        
        $orderId = $payment->getOrder()->getIncrementId();
        
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $CustNumber = $customerData->getId();
        } else {
            $CustNumber = '';
        }
        
        $custref = $orderId;
        $amountcents = $amount * 100;
        $accountnumber = Mage::getStoreConfig('payment/bambora/account_number');
        $cardnumber = $payment->getCcNumber();
        $expm = $payment->getCcExpMonth();
        $expy = $payment->getCcExpYear();
        $CVN = $payment->getCcCid();
        $cardholdername = $payment->getCcOwner();
        $username = $this->_apiusername;
        $password = $this->_apipassword;
        $trntype = $paymentaction;         

        $soaprequest  = '  <dts:SubmitSinglePayment>';
        $soaprequest .= '     <!--Optional:-->';
        $soaprequest .= '     <dts:trnXML>';
        $soaprequest .= '     <![CDATA[';
        $soaprequest .= '      <Transaction>';
        $soaprequest .= '        <CustNumber>' . $CustNumber . '</CustNumber>';
        $soaprequest .= '        <CustRef>' . $custref . '</CustRef>';
        $soaprequest .= '        <Amount>' . $amountcents . '</Amount>';
        $soaprequest .= '        <TrnType>' . $trntype . '</TrnType>';
        $soaprequest .= '        <AccountNumber>' . $accountnumber . '</AccountNumber>';
        $soaprequest .= '        <CreditCard Registered="False">';
        $soaprequest .= '                <CardNumber>' . $cardnumber . '</CardNumber>';
        $soaprequest .= '                <ExpM>' . $expm . '</ExpM>';
        $soaprequest .= '                <ExpY>' . $expy . '</ExpY>';
        $soaprequest .= '                <CVN>' . $CVN . '</CVN>';
        $soaprequest .= '                <CardHolderName>' . $cardholdername . '</CardHolderName>';
        $soaprequest .= '        </CreditCard>';
        $soaprequest .= '        <Security>';
        $soaprequest .= '                <UserName>' . $username . '</UserName>';
        $soaprequest .= '                <Password>' . $password . '</Password>';
        $soaprequest .= '        </Security>';
        $soaprequest .= '        </Transaction>';
        $soaprequest .= '      ]]>';
        $soaprequest .= '     </dts:trnXML>';
        $soaprequest .= '  </dts:SubmitSinglePayment>';
        
        $xml = $this->_doAPI($soaprequest);
        
        $xmlarray = (array) $xml->SubmitSinglePaymentResponse->SubmitSinglePaymentResult;
        $response = isset($xmlarray[0]) ? simplexml_load_string($xmlarray[0]) : null;         

        return $response;
    }
       
    protected function _getAPICred()
    {
        if (Mage::getStoreConfig('payment/bambora/mode') == "sandbox") {
            $this->_apiusername = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora/sandbox_api_key'));
            $this->_apipassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora/sandbox_api_password'));
        } else {
            $this->_apiusername = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora/live_api_key'));
            $this->_apipassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/bambora/live_api_password'));
        }
    }

    protected function _doAPI($request)
    {        
        
        if (Mage::getStoreConfig('payment/bambora/mode') == "sandbox") {
            $url = Reign_Bambora_Helper_BamboraConstant::SANDBOX_ENDPOINT;
        } else {
            $url = Reign_Bambora_Helper_BamboraConstant::LIVE_ENDPOINT;
        }
                
        $soaprequest  = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dts="http://www.ippayments.com.au/interface/api/dts">';
        $soaprequest .= '<soapenv:Header/>';
        $soaprequest .= '<soapenv:Body>';
        $soaprequest .= $request;
        $soaprequest .= '</soapenv:Body>';
        $soaprequest .= '</soapenv:Envelope>';
        
        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: ".strlen($soaprequest),
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, Reign_Bambora_Helper_BamboraConstant::API_TIMEOUT);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soaprequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, Mage::getSingleton('bambora/config')->isEnableSSLVerification());    

        $responsecurl =  curl_exec($ch);
        $rt = str_replace("<soap:Body>", "", $responsecurl);
        $rx = str_replace("</soap:Body>", "", $rt); 
        $xml = simplexml_load_string($rx);
        curl_close($ch);        
        return $xml;
    }
    
}
