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
    public string $ClientFirstName;

    /** Фамилия клиента */
    public string $ClientLastName;

    /** Отчество клиента */
    public string $ClientMiddleName;

    /** Контактный телефон клиента */
    public string $ClientContactPhone;

    /** Дополнительный контактный телефон клиента */
    public string $ClientExtendedContactPhone;

    /** Адрес электронной почты клиента */
    public string $ClientEmail;

    /** Дата рождения клиента */
    public string $ClientBirthDate;

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
        $this->ClientContactPhone = $phone;
        $this->ClientLastName = $lastName;
        $this->ClientFirstName = $firstName;
        $this->ClientMiddleName = $middleName;
        $this->ClientEmail = $email;
        $this->ClientExtendedContactPhone = $extendedPhone;
        if (!empty($birthDate)) {
            $this->ClientBirthDate = $birthDate;
        }
    }
}
