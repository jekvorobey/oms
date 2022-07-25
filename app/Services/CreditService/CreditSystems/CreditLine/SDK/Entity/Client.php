<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity;

/**
 * Информация о клиенте
 * Class CreditLineClient
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity
 */
class Client
{
    /** Имя клиента */
    public string $clientFirstName;

    /** Фамилия клиента */
    public string $clientLastName;

    /** Отчество клиента */
    public string $clientMiddleName;

    /** Контактный телефон клиента */
    public string $clientContactPhone;

    /** Дополнительный контактный телефон клиента */
    public string $clientExtendedContactPhone;

    /** Адрес электронной почты клиента */
    public string $clientEmail;

    /** Дата рождения клиента */
    public string $clientBirthDate;

    /**
     * Создает объект класса
     * @param string|null $phone Номер телефона
     * @param string|null $lastName Фамилия
     * @param string|null $firstName Имя
     * @param string|null $middleName Отчество
     * @param string|null $email Адрес электронной почты
     * @param string|null $birthDate Дата рождения
     * @param string|null $extendedPhone Дополнительный контактный телефон клиента
     */
    public function __construct(
        ?string $phone = '',
        ?string $lastName = '',
        ?string $firstName = '',
        ?string $middleName = '',
        ?string $email = '',
        ?string $birthDate = '',
        ?string $extendedPhone = ''
    ) {
        $this->clientContactPhone = $phone;
        $this->clientLastName = $lastName;
        $this->clientFirstName = $firstName;
        $this->clientMiddleName = $middleName;
        $this->clientEmail = $email;
        $this->clientExtendedContactPhone = $extendedPhone;
        if (!empty($birthDate)) {
            $this->clientBirthDate = $birthDate;
        }
    }
}
