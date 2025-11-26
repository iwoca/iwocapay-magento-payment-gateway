<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Api\Response;

interface GetOrderInterface
{
    public const ID = 'id';
    public const AMOUNT = 'amount';
    public const REFERENCE = 'reference';
    public const REDIRECT_URL = 'redirect_url';
    public const PRICING = 'pricing';
    public const PAY_LINK_ID = 'pay_link_id';
    public const SELLER_NAME = 'seller_name';
    public const STATUS = 'status';
    public const ALLOWED_PAYMENT_TERMS = 'allowed_payment_terms';
    public const PLUGIN_METADATA = 'meta_data';

    /** API status codes */
    public const STATUS_CODE_CREATED = 'CREATED';
    public const STATUS_CODE_PENDING = 'PENDING';
    public const STATUS_CODE_APPROVED = 'APPROVED';
    public const STATUS_CODE_SUCCESSFUL = 'SUCCESSFUL';
    public const STATUS_CODE_UNSUCCESSFUL = 'UNSUCCESSFUL';

    /**
     * @param string $id
     * @return GetOrderInterface
     */
    public function setId(string $id): GetOrderInterface;

    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param float $amount
     * @return GetOrderInterface
     */
    public function setAmount(float $amount): GetOrderInterface;

    /**
     * @return float
     */
    public function getAmount(): float;

    /**
     * @param string $reference
     * @return GetOrderInterface
     */
    public function setReference(string $reference): GetOrderInterface;

    /**
     * @return string
     */
    public function getReference(): string;

    /**
     * @param string $redirectUrl
     * @return GetOrderInterface
     */
    public function setRedirectUrl(string $redirectUrl): GetOrderInterface;

    /**
     * @return string
     */
    public function getRedirectUrl(): string;

    /**
     * @param \Iwoca\Iwocapay\Api\Response\PricingInterface $pricing
     * @return GetOrderInterface
     */
    public function setPricing(PricingInterface $pricing): GetOrderInterface;

    /**
     * @return \Iwoca\Iwocapay\Api\Response\PricingInterface
     */
    public function getPricing(): PricingInterface;

    /**
     * @param string $payLinkId
     * @return GetOrderInterface
     */
    public function setPayLinkId(string $payLinkId): GetOrderInterface;

    /**
     * @return string
     */
    public function getPayLinkId(): string;

    /**
     * @param string $sellerName
     * @return GetOrderInterface
     */
    public function setSellerName(string $sellerName): GetOrderInterface;

    /**
     * @return string
     */
    public function getSellerName(): string;

    /**
     * @param string $status
     * @return GetOrderInterface
     */
    public function setStatus(string $status): GetOrderInterface;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param array $allowedPaymentTerms
     * @return GetOrderInterface
     */
    public function setAllowedPaymentTerms(array $allowedPaymentTerms): GetOrderInterface;

    /**
     * @return array
     */
    public function getAllowedPaymentTerms(): array;
}
