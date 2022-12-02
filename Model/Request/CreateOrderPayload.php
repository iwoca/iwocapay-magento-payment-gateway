<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Request;

use Iwoca\Iwocapay\Api\Request\CreateOrderPayloadInterface;
use Magento\Framework\DataObject;

class CreateOrderPayload extends DataObject implements CreateOrderPayloadInterface
{
    /**
     * @param float $amount
     * @return CreateOrderPayloadInterface
     */
    public function setAmount(float $amount): CreateOrderPayloadInterface
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
     * @return CreateOrderPayloadInterface
     */
    public function setReference(string $reference): CreateOrderPayloadInterface
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
     * @param array|null $allowed_payment_terms
     * @return CreateOrderPayloadInterface
     */
    public function setAllowedPaymentTerms(?array $allowed_payment_terms = null): CreateOrderPayloadInterface
    {
        return $this->setData(self::ALLOWED_PAYMENT_TERMS, $allowed_payment_terms);
    }

    /**
     * @return array|null
     */
    public function getAllowedPaymentTerms(): ?array
    {
        return $this->getData(self::ALLOWED_PAYMENT_TERMS);
    }

}
