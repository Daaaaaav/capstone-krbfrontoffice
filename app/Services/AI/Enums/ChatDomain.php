<?php

namespace App\Services\AI\Enums;

enum ChatDomain: string
{
    case ADMINISTRATIVE = 'administrative';
    case GENERAL_KRB_KNOWLEDGE = 'general_krb_knowledge';
    case ANALYTICS = 'analytics';
    case CALCULATION = 'calculation';
    case FORECAST = 'forecast';
    case AVAILABILITY = 'availability';
    case SYSTEM_UTILITY = 'system_utility';
    case OUT_OF_SCOPE = 'out_of_scope';
}
