<?php

namespace H2w\Fretebarato\Observer\Sales\Order;

use \H2w\Fretebarato\Helper\Api as FretebaratoApi;

class PlaceAfter implements \Magento\Framework\Event\ObserverInterface
{

    public function __construct(FretebaratoApi $helperApi)
    {
        $this->helperApi  = $helperApi;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $order  = $observer->getEvent()->getOrder();
        $method = $order->getShippingMethod();

        // Se o pedido foi fechado com um método Fretebarato
        if (strpos($method, 'fretebarato_') !== false) {
            $info = explode('<h2w>', $method);

            try {
                $this->helperApi->callPostbackWebservice($order->getIncrementId(), $info[1], $info[2]);
            } catch(Exception $e) {}
        }
    }

}