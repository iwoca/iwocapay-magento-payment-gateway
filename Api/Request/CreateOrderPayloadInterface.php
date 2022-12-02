<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Api\Request;

interface CreateOrderPayloadInterface
{
    public const AMOUNT ='amount';
    public const REFERENCE ='reference';
    public const ALLOWED_PAYMENT_TERMS ='allowed_payment_terms';

    /**
     * @param float $amount
     * @return CreateOrderPayloadInterface
     */
    public function setAmount(float $amount): CreateOrderPayloadInterface;

    /**
     * @return float
     */
    public function getAmount(): float;

    /**
     * @param string $reference
     * @return CreateOrderPayloadInterface
     */
    public function setReference(string $reference): CreateOrderPayloadInterface;

    /**
     * @return string
     */
    public function getReference(): string;

    /**
     * @param array|null $allowed_payment_terms
     * @return CreateOrderPayloadInterface
     */
    public function setAllowedPaymentTerms(?array $allowed_payment_terms = null): CreateOrderPayloadInterface;

    /**
     * @return array|null
     */
    public function getAllowedPaymentTerms(): ?array;

}
