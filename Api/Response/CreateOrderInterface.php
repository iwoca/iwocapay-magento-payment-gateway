<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Api\Response;

interface CreateOrderInterface
{
    public const ID = 'id';
    public const AMOUNT = 'amount';
    public const REFERENCE = 'reference';
    public const ORDER_URL = 'order_url';
    public const STATUS = 'status';
    public const ALLOWED_PAYMENT_TERMS = 'allowed_payment_terms';

    /**
     * @param string $id
     * @return CreateOrderInterface
     */
    public function setId(string $id): CreateOrderInterface;

    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param float $amount
     * @return CreateOrderInterface
     */
    public function setAmount(float $amount): CreateOrderInterface;

    /**
     * @return float
     */
    public function getAmount(): float;

    /**
     * @param string $reference
     * @return CreateOrderInterface
     */
    public function setReference(string $reference): CreateOrderInterface;

    /**
     * @return string
     */
    public function getReference(): string;

    /**
     * @param string $orderUrl
     * @return CreateOrderInterface
     */
    public function setOrderUrl(string $orderUrl): CreateOrderInterface;

    /**
     * @return string
     */
    public function getOrderUrl(): string;

    /**
     * @param string $status
     * @return CreateOrderInterface
     */
    public function setStatus(string $status): CreateOrderInterface;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param array $allowedPaymentTerms
     * @return CreateOrderInterface
     */
    public function setAllowedPaymentTerms(array $allowedPaymentTerms): CreateOrderInterface;

    /**
     * @return array
     */
    public function getAllowedPaymentTerms(): array;
}
