<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Response;

use Iwoca\Iwocapay\Api\Response\CreateOrderInterface;
use Magento\Framework\DataObject;

class CreateOrder extends DataObject implements CreateOrderInterface
{
    /**
     * @param string $id
     * @return CreateOrderInterface
     */
    public function setId(string $id): CreateOrderInterface
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
     * @return CreateOrderInterface
     */
    public function setAmount(float $amount): CreateOrderInterface
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
     * @return CreateOrderInterface
     */
    public function setReference(string $reference): CreateOrderInterface
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
     * @param string $orderUrl
     * @return CreateOrderInterface
     */
    public function setOrderUrl(string $orderUrl): CreateOrderInterface
    {
        return $this->setData(self::ORDER_URL, $orderUrl);
    }

    /**
     * @return string
     */
    public function getOrderUrl(): string
    {
        return (string) $this->getData(self::ORDER_URL);
    }

    /**
     * @param string $status
     * @return CreateOrderInterface
     */
    public function setStatus(string $status): CreateOrderInterface
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
     * @return CreateOrderInterface
     */
    public function setAllowedPaymentTerms(array $allowedPaymentTerms): CreateOrderInterface
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
