<?php

declare(strict_types=1);

namespace MMedia\USPS\Enums;

/**
 * International Service Types
 */
enum InternationalServiceType: string
{
    case PRIORITY_MAIL_INTERNATIONAL = 'PRIORITY_MAIL_INTERNATIONAL';
    case PRIORITY_MAIL_EXPRESS_INTERNATIONAL = 'PRIORITY_MAIL_EXPRESS_INTERNATIONAL';
    case FIRST_CLASS_PACKAGE_INTERNATIONAL = 'FIRST-CLASS_PACKAGE_INTERNATIONAL_SERVICE';

    public function getLabel(): string
    {
        return match ($this) {
            self::PRIORITY_MAIL_INTERNATIONAL => 'Priority Mail International',
            self::PRIORITY_MAIL_EXPRESS_INTERNATIONAL => 'Priority Mail Express International',
            self::FIRST_CLASS_PACKAGE_INTERNATIONAL => 'First-Class Package International',
        };
    }
}
