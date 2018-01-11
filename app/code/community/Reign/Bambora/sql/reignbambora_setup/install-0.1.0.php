<?php
$installer = $this;

$installer->startSetup();

Mage::getModel('sales/order_status')
    ->setStatus('bambora_processing')
    ->setLabel('Bambora Processing')
    ->assignState('processing')
    ->save();

$installer->endSetup();