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

 
class Reign_Bambora_Model_Config_Source_Orderstatus extends 
    Mage_Adminhtml_Model_System_Config_Source_Order_Status_Newprocessing
{
    
    /**
     * Return new order statuses and strip out 'Processed Ogone Payment'
     * which is not applicable
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = parent::toOptionArray();
        foreach ($options as $key => $option) {
            if (strpos($option['value'], '_ogone') !== false) {
                unset($options[$key]);
            }
        }
        
        return $options;
    }
    
}