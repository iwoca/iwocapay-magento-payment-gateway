<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Response;

use Iwoca\Iwocapay\Api\Response\GetOrderInterface;
use Iwoca\Iwocapay\Api\Response\PricingInterface;
use Magento\Framework\DataObject;

class GetOrder extends DataObject implements GetOrderInterface
{
    /**
     * @param string $id
     * @return GetOrderInterface
     */
    public function setId(string $id): GetOrderInterface
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return (string) $this->getData(self::ID);
    }

    /**
     * @param float $amount
     * @return GetOrderInterface
     */
    public function setAmount(float $amount): GetOrderInterface
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return (float) $this->getData(self::AMOUNT);
    }

    /**
     * @param string $reference
     * @return GetOrderInterface
     */
    public function setReference(string $reference): GetOrderInterface
    {
        return $this->setData(self::REFERENCE, $reference);
    }

    /**
     * @return string
     */
    public function getReference(): string
    {
        return (string) $this->getData(self::REFERENCE);
    }

    /**
     * @param string $redirectUrl
     * @return GetOrderInterface
     */
    public function setRedirectUrl(string $redirectUrl): GetOrderInterface
    {
        return $this->setData(self::REDIRECT_URL, $redirectUrl);
    }

    /**
     * @param object $dict
     * @return GetOrderInterface
     */
    public function setPluginMetadata(object $dict): GetOrderInterface
    {
        return $this->setData(self::PLUGIN_METADATA, $dict);
    }

    /**
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return (string) $this->getData(self::REDIRECT_URL);
    }

    /**
     * @param PricingInterface $pricing
     * @return GetOrderInterface
     */
    public function setPricing(PricingInterface $pricing): GetOrderInterface
    {
        return $this->setData(self::PRICING, $pricing);
    }

    /**
     * @return PricingInterface
     */
    public function getPricing(): PricingInterface
    {
        return $this->getData(self::PRICING);
    }

    /**
     * @param string $payLinkId
     * @return GetOrderInterface
     */
    public function setPayLinkId(string $payLinkId): GetOrderInterface
    {
        return $this->setData(self::PAY_LINK_ID, $payLinkId);
    }

    /**
     * @return string
     */
    public function getPayLinkId(): string
    {
        return (string) $this->getData(self::PAY_LINK_ID);
    }

    /**
     * @param string $sellerName
     * @return GetOrderInterface
     */
    public function setSellerName(string $sellerName): GetOrderInterface
    {
        return $this->setData(self::SELLER_NAME, $sellerName);
    }

    /**
     * @return string
     */
    public function getSellerName(): string
    {
        return (string) $this->getData(self::SELLER_NAME);
    }

    /**
     * @param string $status
     * @return GetOrderInterface
     */
    public function setStatus(string $status): GetOrderInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    /**
     * @param array $allowedPaymentTerms
     * @return GetOrderInterface
     */
    public function setAllowedPaymentTerms(array $allowedPaymentTerms): GetOrderInterface
    {
        return $this->setData(self::ALLOWED_PAYMENT_TERMS, $allowedPaymentTerms);
    }

    /**
     * @return array
     */
    public function getAllowedPaymentTerms(): array
    {
        return $this->getData(self::ALLOWED_PAYMENT_TERMS);
    }
}
