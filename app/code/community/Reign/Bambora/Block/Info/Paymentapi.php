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
 
class Reign_Bambora_Block_Info_Paymentapi extends Mage_Payment_Block_Info
{
    protected function _construct(){
        parent::_construct();
        $this->setTemplate('reignbambora/info/info.phtml');
    }
    
    public function getCcTypeName($type)
    {
        return Mage::helper('bambora')->getCcTypeName($type);
    }
    
}