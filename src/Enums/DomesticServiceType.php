<?php

declare(strict_types=1);

namespace MMedia\USPS\Enums;

/**
 * Domestic Service Types
 */
enum DomesticServiceType: string
{
    case GROUND_ADVANTAGE = 'USPS_GROUND_ADVANTAGE';
    case PRIORITY_MAIL = 'PRIORITY_MAIL';
    case PRIORITY_MAIL_EXPRESS = 'PRIORITY_MAIL_EXPRESS';
    case MEDIA_MAIL = 'USPS_MEDIA_MAIL';
    case FIRST_CLASS_PACKAGE = 'FIRST-CLASS_PACKAGE_SERVICE';

    public function getLabel(): string
    {
        return match ($this) {
            self::GROUND_ADVANTAGE => 'USPS Ground Advantage',
            self::PRIORITY_MAIL => 'USPS Priority Mail',
            self::PRIORITY_MAIL_EXPRESS => 'USPS Priority Mail Express',
            self::MEDIA_MAIL => 'USPS Media Mail',
            self::FIRST_CLASS_PACKAGE => 'USPS First-Class Package',
        };
    }
}
