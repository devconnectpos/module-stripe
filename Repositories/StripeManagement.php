<?php

namespace SM\Stripe\Repositories;

use Magento\Framework\App\RequestInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Stripe\Helper\Data;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use Stripe\Stripe;

class StripeManagement extends ServiceAbstract
{
    /**
     * @var Data
     */
    protected $stripeHelper;
    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        Data $stripeHelper,
        CartRepositoryInterface $cartRepository
    ) {
        parent::__construct($requestInterface, $dataConfig, $storeManager);
        $this->stripeHelper = $stripeHelper;
        $this->cartRepository = $cartRepository;
    }

    /**
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function initStripe()
    {
        $this->stripeHelper->checkLibraryExists();
        $storeId = $this->getRequest()->getParam('store_id');
        $apiKey = $this->stripeHelper->getApiKey('secret', $storeId);
        if (!$apiKey) {
            throw new \Exception(__('Please configure the API Key first.'));
        }

        Stripe::setApiKey($apiKey);
        return $this;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createPaymentIntent()
    {
        $this->initStripe();
        $quoteId = $this->getRequest()->getParam('quote_id');

        try {
            $quote = $this->cartRepository->get($quoteId);
            $paymentIntent = \Stripe\PaymentIntent::create(
                [
                    'amount' => (int)($quote->getGrandTotal() * 100),
                    'currency' => $quote->getCurrency() ? strtolower($quote->getCurrency()->getStoreCurrencyCode()) : "usd",
                    'payment_method_types' => ['card_present'],
                    'capture_method' => 'manual',
                ]
            );

            return $this->getSearchResult()
                ->setItems([$paymentIntent->client_secret])
                ->setErrors([])
                ->setTotalCount(1)
                ->setLastPageNumber(1)
                ->getOutput();
        } catch (\Exception $e) {
            throw new \Exception(__($e->getMessage()));
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getConnectionToken()
    {
        $this->initStripe();
        try {
            $token = \Stripe\Terminal\ConnectionToken::create();

            return $this->getSearchResult()
                ->setItems([$token->secret])
                ->setErrors([])
                ->setTotalCount(1)
                ->setLastPageNumber(1)
                ->getOutput();
        } catch (\Exception $e) {
            throw new \Exception(__($e->getMessage()));
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function capturePayment()
    {
        $this->initStripe();
        $intentId = $this->getRequest()->getParam('intent_id');
        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($intentId);
            $paymentIntent->capture();

            return $this->getSearchResult()
                ->setItems([$paymentIntent->status])
                ->setErrors([])
                ->setTotalCount(1)
                ->setLastPageNumber(1)
                ->getOutput();
        } catch (\Exception $e) {
            throw new \Exception(__($e->getMessage()));
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function refundPayment()
    {
        $this->initStripe();
        $intentId = $this->getRequest()->getParam('intent_id');
        $amount = $this->getRequest()->getParam('amount');
        try {
            if ($amount) {
                $refund = \Stripe\Refund::create(
                    ['payment_intent' => $intentId, 'amount' => $amount]
                );
            } else {
                $refund = \Stripe\Refund::create(
                    ['payment_intent' => $intentId]
                );
            }
            return $this->getSearchResult()
                ->setItems([$refund->status])
                ->setErrors([])
                ->setTotalCount(1)
                ->setLastPageNumber(1)
                ->getOutput();
        } catch (\Exception $e) {
            throw new \Exception(__($e->getMessage()));
        }
    }
}
