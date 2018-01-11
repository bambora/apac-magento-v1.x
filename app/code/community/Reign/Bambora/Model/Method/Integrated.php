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

 
class Reign_Bambora_Model_Method_Integrated extends Mage_Payment_Model_Method_Abstract
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

    protected $_code = "bambora_integrated";
    protected $_formBlockType = 'bambora/form_integrated';
    protected $_infoBlockType = 'bambora/info_paymentapi';
    
    protected $_apiusername = "";
    protected $_apipassword = "";
    protected $_submiturl = "";

    public function assignData($data)
    {
        if ($data["CardType"] == "") {
            return true;
        }
        
        $expirydate = explode("/", $data['ExpiryDate']);
        $info = $this->getInfoInstance();


        $cctype = "";
        
        switch($data["CardType"]) {
            case "MasterCard":
                $cctype = "MC";
                break;
            case "American Express":
                $cctype = "AE";
                break;
            case "Diners Club International":
                $cctype = "DC";
                break;
            default:
                $cctype = "VI";
                break;
        }

        if ($data["CcOwner"] != "") {
            $info->setCcType($cctype)
                    ->setCcOwner($data["CcOwner"])
                    ->setCcLast4(substr($data["CcNumber"], -4))
                    ->setCcExpMonth($expirydate[0])
                    ->setCcExpYear("20".$expirydate[1]);
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
        Mage::throwException(Reign_Bambora_Helper_BamboraConstant::TRANSACTION_LIVE_ERROR_MESSAGE);
        
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
        
        if (Mage::getStoreConfig('payment/bambora_integrated/payment_action') == Reign_Bambora_Helper_BamboraConstant::CC_PURCHASE) {  
            return true;        
        }
        
        $orderId = $payment->getOrder()->getId(); 
        
        $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
        ->addAttributeToFilter('order_id', array('eq' => $orderId))
        ->addAttributeToFilter('txn_type', array('eq' => 'authorization'));
        $transactionid  = $transaction->getData();
        $transactionid  = $transactionid[0]['txn_id'];
        
        $receiptNumber = $transactionid;

        $response = $this->_SingleCaptureRequest($receiptNumber, $amount);
        
        
        $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0);  
        
        return $this;
    }

    protected function getApi()
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
    

    public function refund(Varien_Object $payment, $amount){
        
        $this->getApi();
        
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
        
        $this->getApi();

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
        $this->getApi();
        
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
        $rz = str_replace("<soap:Body>", "", $responsecurl);
        $rx = str_replace("</soap:Body>", "", $rz); 
        $xml = simplexml_load_string($rx);
        curl_close($ch);        
        return $xml;
    }


    

}