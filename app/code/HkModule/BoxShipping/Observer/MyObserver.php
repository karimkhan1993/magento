<?php
namespace HkModule\BoxShipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Captcha\Observer\CaptchaStringResolver;

class MyObserver implements ObserverInterface
{

	public function execute(\Magento\Framework\Event\Observer $observer)
	{
		//Get Object Manager Instance
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$checkoutSession = $objectManager->get('Magento\Checkout\Model\Session');
		$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
		$connection = $resource->getConnection();
		$order = $observer->getEvent()->getOrder();

		$box_detail = json_encode($checkoutSession->getBox());
		//\Psr\Log\LoggerInterface
		$tableName = $resource->getTableName('sales_order');
	    $sql = "UPDATE ". $tableName. " SET box_detail= '$box_detail' WHERE entity_id =". $order->getId();
	    $result = $connection->query($sql);

		
	}

}