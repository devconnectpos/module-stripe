<?php

namespace SM\Stripe\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
use SM\Payment\Model\ResourceModel\RetailPayment\CollectionFactory;
use SM\XRetail\Helper\Data as RetailHelper;
use Magento\Framework\ObjectManagerInterface;

class Data
{
    /**
     * @var RetailHelper
     */
    protected $retailHelper;
    /**
     * @var ModuleListInterface
     */
    protected $moduleList;
    /**
     * @var CollectionFactory
     */
    protected $paymentCollectionFactory;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct(
        RetailHelper $retailHelper,
        ModuleListInterface $moduleList,
        CollectionFactory $paymentCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        ObjectManagerInterface $objectManager
    ) {
        $this->retailHelper = $retailHelper;
        $this->moduleList = $moduleList;
        $this->paymentCollectionFactory = $paymentCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->objectManager = $objectManager;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function checkLibraryExists()
    {
        if (!class_exists(\Stripe\Stripe::class)) {
            throw new \Exception(
                __("The Stripe PHP library dependency has not been installed. Please follow the installation instructions at https://stripe.com/docs/plugins/magento/install#manual")
            );
        }
        return true;
    }

    /**
     * $type = secret or publishable
     * @param string $type
     * @return false|mixed
     */
    public function getApiKey($type, $storeId = null)
    {
        // get API Key from config
        if ($this->moduleList->getOne('StripeIntegration_Payments')) {
            $stripeMode = $this->getConfigValue('payment/stripe_payments_basic/stripe_mode', $storeId);
            if ($type === 'secret') {
                $apiKey = $this->getConfigValue("payment/stripe_payments_basic/stripe_{$stripeMode}_sk", $storeId);

                if (class_exists('StripeIntegration\Payments\Model\Config')) {
                    $configManager = $this->objectManager->get('StripeIntegration\Payments\Model\Config');
                    $apiKey = $configManager->decrypt($apiKey);
                }
            } else {
                $apiKey = $this->getConfigValue("payment/stripe_payments_basic/stripe_{$stripeMode}_pk", $storeId);
            }
            if ($apiKey) {
                return $apiKey;
            }
        }
        $collection = $this->paymentCollectionFactory->create()
            ->addFieldToFilter('type', ['eq' => \SM\Payment\Model\RetailPayment::STRIPE]);
        if ($collection->count() == 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Payment not found!'));
        }
        $stripePayment = $collection->getFirstItem();
        $paymentData = json_decode($stripePayment->getData('payment_data'), true);

        if ($type === 'secret') {
            return $paymentData['secret_api_key'];
        }
        return $paymentData['publishable_api_key'];
    }

    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
