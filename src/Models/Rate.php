<?php

declare(strict_types=1);

namespace MMedia\USPS\Models;

/**
 * Shipping Rate Result
 */
class Rate
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $service,
        private readonly string $serviceLabel,
        private readonly float $basePrice,
        private readonly float $totalPrice,
        private readonly array $metadata = []
    ) {
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getServiceLabel(): string
    {
        return $this->serviceLabel;
    }

    public function getBasePrice(): float
    {
        return $this->basePrice;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice(int $decimals = 2): string
    {
        return number_format($this->totalPrice, $decimals);
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'service_label' => $this->serviceLabel,
            'base_price' => $this->basePrice,
            'total_price' => $this->totalPrice,
            'metadata' => $this->metadata,
        ];
    }
}
