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
 
class Reign_Bambora_Block_Form_Integrated extends  Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('reignbambora/form/integrated.phtml');
         
    }

    protected function _getConfig()
    {
        return Mage::getSingleton('bambora/config');
    }

}