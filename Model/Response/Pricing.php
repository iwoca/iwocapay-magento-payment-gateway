<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Response;

use Iwoca\Iwocapay\Api\Response\PricingInterface;
use Magento\Framework\DataObject;

class Pricing extends DataObject implements PricingInterface
{
    /**
     * @param float $representativeInterest
     * @return PricingInterface
     */
    public function setRepresentativeInterest(float $representativeInterest): PricingInterface
    {
        return $this->setData(self::REPRESENTATIVE_INTEREST, $representativeInterest);
    }

    /**
     * @return float
     */
    public function getRepresentativeInterest(): float
    {
        return (float) $this->getData(self::REPRESENTATIVE_INTEREST);
    }

    /**
     * @param array $promotions
     * @return PricingInterface
     */
    public function setPromotions(array $promotions): PricingInterface
    {
        return $this->setData(self::PROMOTIONS, $promotions);
    }

    /**
     * @return array
     */
    public function getPromotions(): array
    {
        return $this->getData(self::PROMOTIONS, []);
    }
}
