<?php

namespace H2w\Fretebarato\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use \H2w\Fretebarato\Helper\{Data as FretebaratoData, Api as FretebaratoApi};

class Fretebarato extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements \Magento\Shipping\Model\Carrier\CarrierInterface
{

    protected $_code = 'fretebarato'; // Keet it like this
    protected $_isFixed = false;
    protected $_rateResultFactory;
    protected $_rateMethodFactory;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        array $data = [],
        FretebaratoData $helperData,
        FretebaratoApi $helperApi
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

        $this->scopeConfig = $scopeConfig;
        $this->helperData  = $helperData;
        $this->helperApi   = $helperApi;
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        $definePrazoExibicao = function($shippingMethod) use ($storeScope) {
            if (!empty($shippingMethod['prazo_exibicao'])) {
                return (int) $shippingMethod['prazo_exibicao'];
            }
            if (!empty($shippingMethod['prazo'])) {
                return (int) $shippingMethod['prazo'];
            }
            return (int) $this->scopeConfig->getValue('carriers/fretebarato/defaultShippingDeadline', $storeScope);
        };

        $defineValorExibicao = function($shippingMethod) {
            return (double) $shippingMethod['valor_frete_exibicao'] ?? $shippingMethod['valor_frete'];
        };

		try {
            $productsList 		   = $this->helperData->prepareProductsList($request->getAllItems());
            $additionalInformation = $this->helperData->prepareAdditionalInformation();
            $wsResults             = $this->helperApi->callCotationWebservice($request->getDestPostcode(), $productsList, $additionalInformation);

            if ($wsResults->error) {
                throw new Exception($wsResults->message, $wsResults->code);
            }

			$deadlineMessage = trim($this->scopeConfig->getValue('carriers/fretebarato/msgShippingDeadline', $storeScope));
            $methodsResults  = $this->_rateResultFactory->create();

			foreach($wsResults->quotes as $shippingMethod) {
                // $prazoExibicao = $definePrazoExibicao($shippingMethod);
                // $valorExibicao = $defineValorExibicao($shippingMethod);

                $method = $this->_rateMethodFactory->create();
				$method->setCarrier($this->_code); // Keep it like this
                $method->setPrice($shippingMethod->price);
				$method->setCost($shippingMethod->price);

				$method->setCarrierTitle($this->scopeConfig->getValue('carriers/fretebarato/title', $storeScope));
				$method->setMethodTitle($shippingMethod->name);

                if (isset($deadlineMessage)) {
                    $method->setMethodTitle($method->getMethodTitle() . ' ' . sprintf($deadlineMessage, (string) $shippingMethod->days));
                }

				$method->setMethod($this->helperData->buildShippingMethodName($shippingMethod->name). '<h2w>' .$shippingMethod->quote_id. '<h2w>');
				// $method->setMethod($this->helperData->buildShippingMethodName($shippingMethod->name). '<h2w>' .$shippingMethod->quote_id. '<h2w>' .$wsResults['CotacaoId']);
				$methodsResults->append($method);
			}

			return $methodsResults;
		} catch (Exception $e) {
			return false;
		}
    }

    public function getAllowedMethods()
    {
        return ['flatrate' => $this->getConfigData('title')];
    }

}
