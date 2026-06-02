<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Request;

use Iwoca\Iwocapay\Api\Request\CreateOrderPayloadInterface;
use Magento\Framework\DataObject;

class CreateOrderPayload extends DataObject implements CreateOrderPayloadInterface
{
    /**
     * @inheritdoc
     */
    public function setAmount(float $amount): CreateOrderPayloadInterface
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @inheritdoc
     */
    public function getAmount(): float
    {
        return (float) $this->getData(self::AMOUNT);
    }

    /**
     * @inheritdoc
     */
    public function setReference(string $reference): CreateOrderPayloadInterface
    {
        return $this->setData(self::REFERENCE, $reference);
    }

    /**
     * @inheritdoc
     */
    public function getReference(): string
    {
        return (string) $this->getData(self::REFERENCE);
    }

    /**
     * @inheritdoc
     */
    public function setAllowedPaymentTerms(?array $allowed_payment_terms = null): CreateOrderPayloadInterface
    {
        return $this->setData(self::ALLOWED_PAYMENT_TERMS, $allowed_payment_terms);
    }

    /**
     * @inheritdoc
     */
    public function getAllowedPaymentTerms(): ?array
    {
        return $this->getData(self::ALLOWED_PAYMENT_TERMS);
    }

    /**
     * @inheritdoc
     */
    public function setSource(string $source): CreateOrderPayloadInterface
    {
        return $this->setData(self::SOURCE, $source);
    }

    /**
     * @inheritdoc
     */
    public function getSource(): string
    {
        return (string) $this->getData(self::SOURCE);
    }

    /**
     * @inheritdoc
     */
    public function setRedirectUrl(string $redirectUrl): CreateOrderPayloadInterface
    {
        return $this->setData(self::REDIRECT_URL, $redirectUrl);
    }

    /**
     * @inheritdoc
     */
    public function setPluginMetadata(array $dict): CreateOrderPayloadInterface
    {
        return $this->setData(self::PLUGIN_METADATA, $dict);
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        return (string) $this->getData(self::REDIRECT_URL);
    }
}
