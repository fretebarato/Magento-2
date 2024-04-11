<?php

namespace H2w\Fretebarato\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function prepareProductsList($cartProducts)
    {        
        $storeScope    = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productsList  = [];

        $addToProductsList = function($parentId, $parent, $sku, $name, $qty = 1, $price = 0, $height = 0, $width = 0, $length = 0, $weight = 0) use (&$productsList) {
            if ($parent === $sku) {
                $productsList[$parentId] = [
                    'sku'         => $sku,
                    'descricao'   => $name,
                    'qtd'         => $qty,
                    'preco'       => $price,
                    'altura'      => $height,
                    'largura'     => $width,
                    'comprimento' => $length,
                    'peso'        => $weight,
                    'volume'      => 1,
                ];

                return true;
            }

            if (isset($productsList[$parentId])) {
                $parentQtd                              = $productsList[$parentId]['qtd'];
                $productsList[$parentId]['qtd']         = 1;
                $productsList[$parentId]['altura']      = 0;
                $productsList[$parentId]['largura']     = 0;
                $productsList[$parentId]['comprimento'] = 0;
                $productsList[$parentId]['peso']        = 0;
            }

            $productsList[$sku] = [
                'sku'         => $sku,
                'descricao'   => $name,
                'qtd'         => $parentQtd,
                'preco'       => 0,
                'altura'      => $height,
                'largura'     => $width,
                'comprimento' => $length,
                'peso'        => $weight,
                'volume'      => 1,
            ];

            return true;
        };

        $heightAttribute = $this->scopeConfig->getValue('carriers/fretebarato/heightAttribute', $storeScope);
        $widthAttribute  = $this->scopeConfig->getValue('carriers/fretebarato/widthAttribute', $storeScope);
        $lengthAttribute = $this->scopeConfig->getValue('carriers/fretebarato/lengthAttribute', $storeScope);
        $weightAttribute = $this->scopeConfig->getValue('carriers/fretebarato/weightAttribute', $storeScope);
        $sizeUnit        = $this->scopeConfig->getValue('carriers/fretebarato/sizeUnit', $storeScope);
        $weightUnit      = $this->scopeConfig->getValue('carriers/fretebarato/weightUnit', $storeScope);

        foreach ($cartProducts as $cartProduct) {
            $product  = $parent = $objectManager->create('Magento\Catalog\Model\Product')->load($cartProduct->getProduct()->getId());
            $parentId = $cartProduct->getId();

            if (!empty($cartProduct->getParentItemId())) {
                $parentId = $cartProduct->getParentItemId();
                $parent   = $objectManager->create('Magento\Catalog\Model\Product')->load($cartProduct->getParentItem()->getProduct()->getId());
            }

            $addToProductsList(
                $parentId,
                $parent->getSku(),
                $product->getSku(),
                $product->getName(),
                $cartProduct->getQty(),
                ($cartProduct->getRowTotal() - $cartProduct->getDiscountAmount()),
                $product->getData($heightAttribute),
                $product->getData($widthAttribute),
                $product->getData($lengthAttribute),
                $product->getData($weightAttribute)
            );

        }

        return array_values($productsList);
    }

    public function prepareAdditionalInformation()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        $additionalInformation = [
            'tipo_ordenacao' => (int) $this->scopeConfig->getValue('carriers/fretebarato/displayOrder', $storeScope),
            'doc_empresa'    => $this->getOnlyNumbers($this->scopeConfig->getValue('carriers/fretebarato/accessTaxvat', $storeScope)),
		    'plataforma'     => $this->scopeConfig->getValue('carriers/fretebarato/platformName', $storeScope)
        ];

        return $additionalInformation;
    }

    public function getOnlyNumbers($str)
    {
        return preg_replace('/[^0-9]/s', '', $str);
    }

	public function buildShippingMethodName($title)
	{
		$title 		  = trim(strip_tags((function_exists('mb_strtolower')) ? mb_strtolower($title, 'UTF-8') : strtolower($title)));
		$arraySearch  = ['á','à','ã','â','ä','é','è','ẽ','ê','ë','í','ì','ĩ','î','ï','ó','ò','õ','ô','ö','ú','ù','ũ','û','ü'];
	    $arrayReplace = ['a','a','a','a','a','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','u','u','u','u','u'];
	    $arrayStrip   = ["~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]", "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;", "â€”", "â€“", ",", "<", ".", ">", "/", "?"];

	    $title = str_replace(' ', '-', $title);
	    $title = str_replace($arraySearch, $arrayReplace, $title);
	    $title = str_replace($arrayStrip, '', $title);
	    $title = preg_replace('/\s+/', '-', $title);
	    $title = preg_replace('/[^a-zA-Z0-9\-]/', '', $title);

	    return $title;
	}

}
