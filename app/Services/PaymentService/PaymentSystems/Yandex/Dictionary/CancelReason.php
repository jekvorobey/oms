<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Dictionary;

use YooKassa\Common\AbstractEnum;

/**
 * Справочник причин отмены платежа
 *
 * @package App\Services\PaymentService\PaymentSystems\Yandex\Dictionary
 */
class CancelReason extends AbstractEnum
{
    public const SECURE_FAILED = '3d_secure_failed';
    public const CALL_ISSUER = 'call_issuer';
    public const CANCEL_BY_MERCHANT = 'canceled_by_merchant';
    public const CARD_EXPIRED = 'card_expired';
    public const COUNTRY_FORBIDDEN = 'country_forbidden';
    public const DEAL_EXPIRED = 'deal_expired';
    public const EXPIRED_ON_CAPTURE = 'expired_on_capture';
    public const EXPIRED_ON_CONFIRMATION = 'expired_on_confirmation';
    public const FRAUD_SUSPECTED = 'fraud_suspected';
    public const GENERAL_DECLINE = 'general_decline';
    public const IDENTIFICATION_REQUIRED = 'identification_required';
    public const INSUFFICIENT_FUNDS = 'insufficient_funds';
    public const INTERNAL_TIMEOUT = 'internal_timeout';
    public const INVALID_CARD_NUMBER = 'invalid_card_number';
    public const INVALID_CSC = 'invalid_csc';
    public const ISSUER_UNAVAILABLE = 'issuer_unavailable';
    public const PAYMENT_METHOD_LIMIT_EXCEEDED = 'payment_method_limit_exceeded';
    public const PAYMENT_METHOD_RESTRICTED = 'payment_method_restricted';
    public const PERMISSION_REVOKED = 'permission_revoked';
    public const UNSUPPORTED_MOBILE_OPERATOR = 'unsupported_mobile_operator';

    protected static $validValues = [
        self::SECURE_FAILED => true,
        self::CALL_ISSUER => true,
        self::CANCEL_BY_MERCHANT => true,
        self::CARD_EXPIRED => true,
        self::COUNTRY_FORBIDDEN => true,
        self::DEAL_EXPIRED => true,
        self::EXPIRED_ON_CAPTURE => true,
        self::EXPIRED_ON_CONFIRMATION => true,
        self::FRAUD_SUSPECTED => true,
        self::GENERAL_DECLINE => true,
        self::IDENTIFICATION_REQUIRED => true,
        self::INSUFFICIENT_FUNDS => true,
        self::INTERNAL_TIMEOUT => true,
        self::INVALID_CARD_NUMBER => true,
        self::INVALID_CSC => true,
        self::ISSUER_UNAVAILABLE => true,
        self::PAYMENT_METHOD_LIMIT_EXCEEDED => true,
        self::PAYMENT_METHOD_RESTRICTED => true,
        self::PERMISSION_REVOKED => true,
        self::UNSUPPORTED_MOBILE_OPERATOR => true,
    ];

    /**
     * Возвращает все возможные причины отмены
     * @return array
     */
    public static function cancelReasons(): array
    {
        return [
            self::SECURE_FAILED => 'Не пройдена аутентификация по 3-D Secure.
            При новой попытке оплаты пользователю следует пройти аутентификацию,
            использовать другое платежное средство или обратиться в банк за уточнениями',
            self::CALL_ISSUER => 'Оплата данным платежным средством отклонена по неизвестным причинам.
            Пользователю следует обратиться в организацию, выпустившую платежное средство',
            self::CANCEL_BY_MERCHANT => 'Платеж отменен по API при оплате в две стадии',
            self::CARD_EXPIRED => 'Истек срок действия банковской карты.
            При новой попытке оплаты пользователю следует использовать другое платежное средство',
            self::COUNTRY_FORBIDDEN => 'Нельзя заплатить банковской картой, выпущенной в этой стране.
            При новой попытке оплаты пользователю следует использовать другое платежное средство.
            Вы можете настроить ограничения на оплату иностранными банковскими картами',
            self::DEAL_EXPIRED => 'Для тех, кто использует Безопасную сделку: закончился срок жизни сделки.
            Если вы еще хотите принять оплату, создайте новую сделку и проведите для нее новый платеж',
            self::EXPIRED_ON_CAPTURE => 'Истек срок списания оплаты у двухстадийного платежа.
            Если вы еще хотите принять оплату, повторите платеж с новым ключом идемпотентности и спишите деньги после
            подтверждения платежа пользователем',
            self::EXPIRED_ON_CONFIRMATION => 'Истек срок оплаты: пользователь не подтвердил платеж за время,
            отведенное на оплату выбранным способом. Если пользователь еще хочет оплатить, вам необходимо повторить
            платеж с новым ключом идемпотентности, а пользователю — подтвердить его',
            self::FRAUD_SUSPECTED => 'Платеж заблокирован из-за подозрения в мошенничестве.
            При новой попытке оплаты пользователю следует использовать другое платежное средство',
            self::GENERAL_DECLINE => 'Причина не детализирована.
            Пользователю следует обратиться к инициатору отмены платежа за уточнением подробностей',
            self::IDENTIFICATION_REQUIRED => 'Превышены ограничения на платежи для кошелька ЮMoney.
            При новой попытке оплаты пользователю следует идентифицировать кошелек или выбрать другое платежное средство',
            self::INSUFFICIENT_FUNDS => 'Не хватает денег для оплаты.
            Пользователю следует пополнить баланс или использовать другое платежное средство',
            self::INTERNAL_TIMEOUT => 'Технические неполадки на стороне ЮKassa: не удалось обработать запрос
            в течение 30 секунд. Повторите платеж с новым ключом идемпотентности',
            self::INVALID_CARD_NUMBER => 'Неправильно указан номер карты. При новой попытке оплаты пользователю
            следует ввести корректные данные',
            self::INVALID_CSC => 'Неправильно указан код CVV2 (CVC2, CID). При новой попытке оплаты пользователю
            следует ввести корректные данные',
            self::ISSUER_UNAVAILABLE => 'Организация, выпустившая платежное средство, недоступна.
            При новой попытке оплаты пользователю следует использовать другое платежное средство или повторить оплату позже',
            self::PAYMENT_METHOD_LIMIT_EXCEEDED => 'Исчерпан лимит платежей для данного платежного средства или вашего магазина.
            При новой попытке оплаты пользователю следует использовать другое платежное средство или повторить оплату на следующий день',
            self::PAYMENT_METHOD_RESTRICTED => 'Запрещены операции данным платежным средством
            (например, карта заблокирована из-за утери, кошелек — из-за взлома мошенниками).
            Пользователю следует обратиться в организацию, выпустившую платежное средство',
            self::PERMISSION_REVOKED => 'Нельзя провести безакцептное списание: пользователь отозвал разрешение на автоплатежи.
            Если пользователь еще хочет оплатить, вам необходимо создать новый платеж, а пользователю — подтвердить оплату',
            self::UNSUPPORTED_MOBILE_OPERATOR => 'Нельзя заплатить с номера телефона этого мобильного оператора.
            При новой попытке оплаты пользователю следует использовать другое платежное средство. Список поддерживаемых операторов',
        ];
    }
}
