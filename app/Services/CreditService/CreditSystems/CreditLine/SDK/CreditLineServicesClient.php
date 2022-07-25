<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK;

use ReflectionObject;
use RuntimeException;
use SoapClient;
use SoapFault;
use SoapHeader;
use stdClass;

/**
 * Класс для представления доступа к сервисам CreditLine
 * Class Client
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK
 */
class CreditLineServicesClient
{
    /** Клиент для сервисов SOAP */
    private SoapClient $soapClient;

    /**
     * Client constructor.
     * @param string $host
     * @param string $login
     * @param string $password
     */
    public function __construct(string $host, string $login, string $password)
    {
        ini_set("soap.wsdl_cache_enabled", "0");

        try {
            $this->soapClient = new SoapClient($host);
            //$this->soapClient = new SoapClient($host, ["trace" => 1]);
        } catch (SoapFault $e) {
            throw new RuntimeException($e->getMessage());
        }

        $auth = [
            "PartnerLogin" => md5($login),
            "PartnerPassword" => md5($password),
        ];

        $header = new SoapHeader("http://tempuri.org/", 'CreditLineHeader', $auth);
        $this->soapClient->__setSoapHeaders($header);
    }

    /**
     * Отправляет запрос службе
     * @param $methodName string Имя метода службы
     * @param $request mixed запроса
     * @param $response mixed
     */
    public function call($methodName, $request, &$response): void
    {
        $soapResult = $this->soapClient->__call($methodName, [$request]);
        $this::cast($response, $soapResult);
    }

    /**
     * Переводит тип из stdClass в пользовательский тип
     * @param $destination mixed назначения
     * @param stdClass $source Источник
     */
    private static function cast(&$destination, stdClass $source): void
    {
        $sourceReflection = new ReflectionObject($source);
        $sourceProperties = $sourceReflection->getProperties();

        foreach ($sourceProperties as $sourceProperty) {
            $name = $sourceProperty->getName();
            if (is_object($destination->{$name})) {
                self::cast($destination->{$name}, $source->$name);
            } else {
                $destination->{$name} = $source->$name;
            }
        }
    }
}
