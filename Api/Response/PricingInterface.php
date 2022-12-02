<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Api\Response;

interface PricingInterface
{
    public const REPRESENTATIVE_INTEREST ='representative_interest';
    public const PROMOTIONS ='promotions';

    /**
     * @param float $representativeInterest
     * @return PricingInterface
     */
    public function setRepresentativeInterest(float $representativeInterest): PricingInterface;

    /**
     * @return float
     */
    public function getRepresentativeInterest(): float;

    /**
     * @param array $promotions
     * @return PricingInterface
     */
    public function setPromotions(array $promotions): PricingInterface;

    /**
     * @return array
     */
    public function getPromotions(): array;
}
