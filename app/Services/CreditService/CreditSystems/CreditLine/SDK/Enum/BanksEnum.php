<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Enum;

/**
 * Перечисление банков
 * Class BanksEnum
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Enum
 */
abstract class BanksEnum
{
    public const NONE = '';
    public const HOME_CREDIT_AND_FINANCE_BANK = 'HomeCreditAndFinanceBank';
    public const BANK_RUSSIAN_STANDARD = 'BankRussianStandard';
    public const CREDIT_EUROPE_BANK = 'CreditEuropeBank';
    public const OTP_BANK = 'OTPBank';
    public const RENESSANS_CREDIT_BANK = 'RenessansCreditBank';
    public const ALFA_BANK = 'AlfaBank';
    public const SETELEM_BANK = 'SetelemBank';
    public const MFK_AIR_LOANS = 'MFKAirLoans';
}
