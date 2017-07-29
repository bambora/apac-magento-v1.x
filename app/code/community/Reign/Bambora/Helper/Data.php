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
 
class Reign_Bambora_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Write message to Bambora errors and exceptions log
     *
     * @param string $message
     * @param int $level
     * @return void
     */
    public function log($id, $message, $level = null)
    {
        $errorMessage = sprintf("(ID: %s) - %s ", $id, $message);
        Mage::log($errorMessage, $level, Reign_Bambora_Helper_BamboraConstant::LOGFILE);
    }

    /**
     * Write exception to Bambora errors and exceptions log
     *
     * @param Exception $exception
     * @return void
     */
    public function logException($exception)
    {
        Mage::log($exception, null, Reign_Bambora_Helper_BamboraConstant::LOGFILE); 
    }    
    
    /**
     * Write message to Bambora debug log
     *
     * @param string $message
     * @param int $level
     * @return void
     */
    public function logDebug($message, $level = null)
    {
        $logMessage = sprintf($message);
        Mage::log($logMessage, $level, Reign_Bambora_Helper_BamboraConstant::DEBUG_LOGFILE);
    } 

    
    public function getCcTypeName($type)
    {  
        $_types = Mage::getConfig()->getNode('global/payment/cc/types')->asArray();    
        return (isset($_types) ? $_types[$type]['name'] : 'Unknown');    
    }
    
    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig('Reign_Bambora')->version;
    }

}