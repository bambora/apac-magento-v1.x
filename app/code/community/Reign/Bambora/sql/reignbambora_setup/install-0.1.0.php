<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

Mage::getModel('sales/order_status')
    ->setStatus('bambora_processing')
    ->setLabel('Bambora Processing')
    ->assignState('processing')
    ->save();

$installer->endSetup();