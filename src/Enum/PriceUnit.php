<?php

namespace App\Enum;

enum PriceUnit: string
{
    case PO_IZVRSENJU = 'PO_IZVRSENJU';
    case MESECNO = 'MESECNO';
    case GODISNJE = 'GODISNJE';

    public function label(): string
    {
        return match ($this) {
            self::PO_IZVRSENJU => 'Po izvršenju',
            self::MESECNO => 'Mesečno',
            self::GODISNJE => 'Godišnje',
        };
    }
}
