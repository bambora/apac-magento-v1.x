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

 
class Reign_Bambora_Model_Config
{
    protected static $_methods;

    public function getCcTypes()
    {
        $_types = Mage::getConfig()->getNode('global/payment/cc/types')->asArray();
        $types = array();
        foreach ($_types as $data) {
            if (isset($data['code']) && isset($data['name'])) {
                $types[$data['code']] = $data['name'];
            }
        }

        return $types;
    }
    
    public function getMonths()
    {
        $data = Mage::app()->getLocale()->getTranslationList('month');
        foreach ($data as $key => $value) {
            $monthNum = ($key < 10) ? '0'.$key : $key;
            $data[$key] = $monthNum . ' - ' . $value;
        }

        return $data;
    }

    public function getYears()
    {
        $years = array();
        $first = date("Y");

        for ($index=0; $index <= 10; $index++) {
            $year = $first + $index;
            $years[$year] = $year;
        }

        return $years;
    }
    
    public function isEnableSSLVerification()
    {
        if (Mage::getStoreConfig('payment/bambora/payment_bambora_mode') == "live") {
            return 1;
        }
        
        if (Mage::getStoreConfig('payment/bambora/ssl_verification')) {
            return 1;
        } else {
            return 0;
        }
        
    }    
   
}