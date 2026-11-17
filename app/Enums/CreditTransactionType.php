<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Bonus = 'bonus';
    case Purchase = 'purchase';
    case Charge = 'charge';
    case Hold = 'hold';
    case Release = 'release';
}
