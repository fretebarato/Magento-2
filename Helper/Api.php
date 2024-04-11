<?php

namespace H2w\Fretebarato\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \H2w\Fretebarato\Helper\Data as FretebaratoData;

class Api extends AbstractHelper {

    /**
     * COLLECTION
     */
    private $oCollection;

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, FretebaratoData $helperData){

        //ATTRIBUTES DEFAULT MAGENTO 2.x
        $this->scopeConfig = $scopeConfig;
        $this->helperData  = $helperData;

        //COLLECT COLLECTION
        $this->oCollection = json_decode(json_encode(Array('rest_url' => null, 
                                                           'access_token' => null, 
                                                           'scope' => null, 
                                                           'endpoint' => null,
                                                           'price' => ['cep_origem' => null, 'cep_destino' => null, 'skus' => []])));
    }

    private static function _BuildFinalFromURL($rest_url, $access_token){
        return $rest_url . '/' . $access_token;
    }

    private static function _toObject($aData){
        return json_decode(json_encode($aData));
    }

    private static function _isIsset($x, $y, $bOk = 0){
        return isset($x[$y]) ? $x[$y] : $bOk;
    }

    public function callCotationWebservice($destinationPostcode, $productsList, $additionalInformation) {
        $this->oCollection->scope  = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $this->oCollection->rest_url     = $this->scopeConfig->getValue('carriers/fretebarato/restCotationUrl', $this->oCollection->scope);
        $this->oCollection->access_token = $this->scopeConfig->getValue('carriers/fretebarato/accessToken', $this->oCollection->scope);
        $this->oCollection->endpoint = self::_BuildFinalFromURL($this->oCollection->rest_url, $this->oCollection->access_token);

        $this->oCollection->price->cep_origem = $this->helperData->getOnlyNumbers($this->scopeConfig->getValue('shipping/origin/postcode', $this->oCollection->scope));
        $this->oCollection->price->cep_destino = $this->helperData->getOnlyNumbers($destinationPostcode);

        //TRATAMENTO DE PRODUTOS
        foreach($productsList as $x) {
            $this->oCollection->price->skus[] = self::_toObject(Array('sku' => $x['sku'], 
                                                                      'description' => $x['descricao'], 
                                                                      'quantity' => $x['qtd'], 
                                                                      'price' => $x['preco'], 
                                                                      'height' => self::_isIsset($x, 'altura', 0),
                                                                      'width' => self::_isIsset($x, 'largura', 0),
                                                                      'length' => self::_isIsset($x, 'comprimento', 0),
                                                                      'weight' => $x['peso'],
                                                                      'vol' => $x['volume']));
        }

        $body = [
            'token' 	 => $this->oCollection->access_token,
            'cep_origem'  => $this->oCollection->price->cep_origem,
		    'cep_destino' => $this->oCollection->price->cep_destino,
            'skus' => $this->oCollection->price->skus,
		    'infComp'	 => $additionalInformation
        ];

		$curl = curl_init();
		curl_setopt_array($curl, [
		    CURLOPT_URL            => $this->oCollection->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FRESH_CONNECT  => true,
		    CURLOPT_MAXREDIRS      => 10,
		    CURLOPT_TIMEOUT        => 5,
		    CURLOPT_CUSTOMREQUEST  => "POST",
		    CURLOPT_POSTFIELDS     => json_encode($body),
		    CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json"
            ],
        ]);

		$response = curl_exec($curl);
		curl_close($curl);

        return json_decode($response, true);
    }

    public function callPostbackWebservice($pedido, $tabela, $cotacao)
    {
        $storeScope  = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $restUrl     = $this->scopeConfig->getValue('carriers/fretebarato/restPostbackUrl', $storeScope);
        $accessToken = $this->scopeConfig->getValue('carriers/fretebarato/accessToken', $storeScope);

        $body = [
            'token' 	 => $accessToken,
            'pedido'     => $pedido,
            'cod_tabela' => $tabela,
            'cotacao_id' => $cotacao,
        ];

		$curl = curl_init();
		curl_setopt_array($curl, [
		    CURLOPT_URL            => $restUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FRESH_CONNECT  => true,
		    CURLOPT_MAXREDIRS      => 10,
		    CURLOPT_TIMEOUT        => 30,
		    CURLOPT_CUSTOMREQUEST  => "POST",
		    CURLOPT_POSTFIELDS     => json_encode($body),
		    CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json"
            ],
        ]);

		$response = curl_exec($curl);
		curl_close($curl);

        return json_decode($response);
    }

}