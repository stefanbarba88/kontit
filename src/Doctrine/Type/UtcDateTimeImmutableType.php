<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;

/**
 * DATETIME koji se u bazi UVEK čuva u UTC, bez obzira na vremensku zonu PHP-a ili sesije baze (Podsetnik).
 * Upis: bilo koji trenutak se prevodi u UTC. Čitanje: vraća se trenutak u UTC; prikaz u Europe/Belgrade radi Twig
 * (twig.date.timezone) ili {@see \App\Service\TodoClock}, nikad ručno u kontrolerima.
 */
class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    public const NAME = 'utc_datetime_immutable';

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            $value = \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone('UTC'));
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }
        $format = $platform->getDateTimeFormatString();
        $date = \DateTimeImmutable::createFromFormat($format, (string) $value, new \DateTimeZone('UTC'));
        if (false === $date) {
            throw new \Doctrine\DBAL\Types\Exception\InvalidFormat(sprintf('Neispravan datum "%s".', $value), self::class, $format);
        }

        return $date;
    }
}
