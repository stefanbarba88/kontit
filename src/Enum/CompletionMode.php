<?php

namespace App\Enum;

enum CompletionMode: string
{
    case ALL = 'ALL';
    case ANY = 'ANY';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Svi učesnici moraju da završe',
            self::ANY => 'Dovoljan je jedan učesnik',
        };
    }
}
