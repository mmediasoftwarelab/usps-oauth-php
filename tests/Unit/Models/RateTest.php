<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Unit\Models;

use MMedia\USPS\Models\Rate;
use PHPUnit\Framework\TestCase;

class RateTest extends TestCase
{
    public function testRateCanBeCreated(): void
    {
        $rate = new Rate(
            service: 'USPS_GROUND_ADVANTAGE',
            serviceLabel: 'USPS Ground Advantage',
            basePrice: 8.00,
            totalPrice: 8.50,
            metadata: ['zone' => '5', 'weight' => 2.5]
        );

        $this->assertInstanceOf(Rate::class, $rate);
    }

    public function testGetService(): void
    {
        $rate = new Rate(
            service: 'PRIORITY_MAIL',
            serviceLabel: 'Priority Mail',
            basePrice: 10.00,
            totalPrice: 10.50
        );

        $this->assertEquals('PRIORITY_MAIL', $rate->getService());
    }

    public function testGetServiceLabel(): void
    {
        $rate = new Rate(
            service: 'PRIORITY_MAIL',
            serviceLabel: 'Priority Mail',
            basePrice: 10.00,
            totalPrice: 10.50
        );

        $this->assertEquals('Priority Mail', $rate->getServiceLabel());
    }

    public function testGetBasePrice(): void
    {
        $rate = new Rate(
            service: 'PRIORITY_MAIL',
            serviceLabel: 'Priority Mail',
            basePrice: 10.00,
            totalPrice: 10.50
        );

        $this->assertEquals(10.00, $rate->getBasePrice());
    }

    public function testGetTotalPrice(): void
    {
        $rate = new Rate(
            service: 'PRIORITY_MAIL',
            serviceLabel: 'Priority Mail',
            basePrice: 10.00,
            totalPrice: 10.50
        );

        $this->assertEquals(10.50, $rate->getTotalPrice());
    }

    public function testGetMetadata(): void
    {
        $metadata = [
            'zone' => '5',
            'weight' => 2.5,
            'deliveryDays' => 3,
        ];

        $rate = new Rate(
            service: 'PRIORITY_MAIL',
            serviceLabel: 'Priority Mail',
            basePrice: 10.00,
            totalPrice: 10.50,
            metadata: $metadata
        );

        $this->assertEquals($metadata, $rate->getMetadata());
    }

    public function testMetadataDefaultsToEmptyArray(): void
    {
        $rate = new Rate(
            service: 'PRIORITY_MAIL',
            serviceLabel: 'Priority Mail',
            basePrice: 10.00,
            totalPrice: 10.50
        );

        $this->assertEquals([], $rate->getMetadata());
        $this->assertIsArray($rate->getMetadata());
    }

    public function testRateWithZeroPrices(): void
    {
        $rate = new Rate(
            service: 'FREE_SHIPPING',
            serviceLabel: 'Free Shipping',
            basePrice: 0.00,
            totalPrice: 0.00
        );

        $this->assertEquals(0.00, $rate->getBasePrice());
        $this->assertEquals(0.00, $rate->getTotalPrice());
    }

    public function testRateIsImmutable(): void
    {
        $rate = new Rate(
            service: 'PRIORITY_MAIL',
            serviceLabel: 'Priority Mail',
            basePrice: 10.00,
            totalPrice: 10.50
        );

        // Rate should be immutable - cannot modify after creation
        $this->assertEquals('PRIORITY_MAIL', $rate->getService());

        // Verify properties are readonly by checking they exist
        $reflection = new \ReflectionClass($rate);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
