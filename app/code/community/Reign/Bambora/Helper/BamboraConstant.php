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
class Reign_Bambora_Helper_BamboraConstant
{
    
    // Transaction types
    const CC_PURCHASE = 1;
    const CC_AUTH = 2;
    const CC_REFUND = 5;
    const DE_DEBIT = 7;
    const DE_CREDIT = 8;
    
    // Transaction type names
    const CC_PURCHASE_LABEL = 'Purchase';
    const CC_AUTH_LABEL = 'Authorise Only';
        
    // Web Services URLs
    const SANDBOX_ENDPOINT = 'https://demo.bambora.co.nz/interface/api/dts.asmx';
    const LIVE_ENDPOINT = 'https://www.ippayments.com.au/interface/api/dts.asmx';

    // Payment statuses
    const PAYMENTSTATUS_PROCESSED = 'bambora_processed';    
    
    // Configuration
    const APPROVED_RESPONSE_CODE = 0;
    const API_TIMEOUT = 30;
    const LOGFILE = 'bambora.log';      // for errors and exceptions
    const DEBUG_LOGFILE = 'bambora_debug.log';  // logs all transactions, errors and exceptions
    
    // Error messages
    const TRANSACTION_SANDBOX_ERROR_MESSAGE = 'Payment failed';
    const TRANSACTION_LIVE_ERROR_MESSAGE = 'Unable to complete payment. Please try again or contact us for further assistance.';    
    
}