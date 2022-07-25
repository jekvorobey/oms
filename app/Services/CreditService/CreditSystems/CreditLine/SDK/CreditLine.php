<?php

namespace App\Services\CreditService\CreditSystems\CreditLine\SDK;

use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\Client;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\Credit;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\CreditPreferences;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\OrderReport;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Entity\Product;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Enum\BanksEnum;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLConfirmOrganizationRequestMessage;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLConfirmOrganizationResponseMessage;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLGetOrderReportRequestMessage;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLGetOrderReportResponseMessage;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLGetOrderStatusRequestMessage;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLOrderStatus;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLOrderStatusResponseMessage;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLRequest;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLRequestMessage;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLResponse;
use App\Services\CreditService\CreditSystems\CreditLine\SDK\Http\CLResponseMessage;
use RuntimeException;

/**
 * Класс для работы с Web-службой CreditLine
 * Class CreditLine
 * @package App\Services\CreditService\CreditSystems\CreditLine\SDK
 */
class CreditLine implements CreditLineInterface
{
    /** Клиент для сервисов CreditLine */
    private CreditLineServicesClient $clClient;

    /**
     * Создает объект для доступа к методам службы CreditLine
     * @param string $host Адрес службы
     * @param string $login Логин
     * @param string $password Пароль
     */
    public function __construct(string $host, string $login, string $password)
    {
        if (!$this->ExtensionIncluded("soap")) {
            throw new RuntimeException("Критическая ошибка, модуль SOAP не подключен");
        }
        if (!$this->ExtensionIncluded("openssl")) {
            throw new RuntimeException("Критическая ошибка, модуль OpenSSL не подключен");
        }
        if (!$host || !$login || !$password) {
            throw new RuntimeException("Не корректные параметры для подключения к сервису");
        }

        $this->clClient = new CreditLineServicesClient($host, $login, $password);
    }

    /**
     * Проверяет данные аутентификации
     */
    public function confirmOrganization(): bool
    {
        $request = new CLConfirmOrganizationRequestMessage();
        $response = new CLConfirmOrganizationResponseMessage();
        $this->clClient->call(__FUNCTION__, $request, $response);

        return $response->isActive;
    }

    /**
     * Отправляет заявку на кредит
     * @param CLRequest $request Заявка на кредит
     * @return CLResponse
     */
    public function processCreditLineApplication(CLRequest $request): CLResponse
    {
        $requestMessage = new CLRequestMessage();
        $requestMessage->creditLineRequest = $request;

        $responseMessage = new CLResponseMessage();
        $this->clClient->Call(__FUNCTION__, $requestMessage, $responseMessage);

        return $responseMessage->CreditLineResponse;
    }

    /**
     * Возвращает статус заказа
     * @param string $orderId Номер заказа
     * @return CLOrderStatus
     */
    public function getOrderStatus($orderId): CLOrderStatus
    {
        $request = new CLGetOrderStatusRequestMessage($orderId);
        $response = new CLOrderStatusResponseMessage();
        $this->clClient->call(__FUNCTION__, $request, $response);

        return $response->creditLineResponse;
    }

    /**
     * Возвращает отчет по заказам
     * @param string $startDate Начальная дата
     * @param string $endDate Конечная дата
     * @return Entity\OrderReport|mixed
     */
    public function getOrderReport(string $startDate, string $endDate)
    {
        $request = new CLGetOrderReportRequestMessage();
        $request->getOrderDates->startDate = $startDate;
        $request->getOrderDates->endDate = $endDate;

        $response = new CLGetOrderReportResponseMessage();
        $this->clClient->Call(__FUNCTION__, $request, $response);

        return $this->convertXMLReport($response->result->report->any);
    }

    /**
     * Возвращает заявку на кредит
     * @param string $orderId Номер заказа
     * @param Client $client Информация о клиенте
     * @param Credit $credit Информация о кредите
     * @param string|null $callTime Удобное время для звонка
     * @param string|null $shopName Наименование магазина
     * @param string|null $productsInStore Наличие товара на складе
     * @param string|null $signingKD Чьими силами производится подписание КД
     * @return CLRequest
     */
    public static function createCLRequest($orderId, $client, $credit, ?string $callTime = "", ?string $shopName = "", ?string $productsInStore = "", ?string $signingKD = ""): CLRequest
    {
        $clRequest = new CLRequest();
        $clRequest->client = $client;
        $clRequest->credit = $credit;
        $clRequest->productsInStore = $productsInStore;
        $clRequest->numOrder = $orderId;
        $clRequest->shopName = $shopName;
        $clRequest->signingKD = $signingKD;
        $clRequest->callTime = $callTime;

        return $clRequest;
    }

    /**
     * Создает товар
     * @param string $name Наименование
     * @param float $price Цена
     * @param int $qty Количество
     * @return array
     */
    public static function createProduct($name, $price, $qty): array
    {
        $product = [];
        $product["Name"] = $name;
        $product["Price"] = $price;
        $product["Count"] = $qty;

        return $product;
    }

    /**
     * Создает клиента
     * @param string $phone Номер телефона
     * @param string $name Имя
     * @param string $email Адрес электронной почты
     * @param string $extendedPhone Дополнительный контактный телефон
     * @return Client
     */
    public static function createClient($phone, $name, $email, $extendedPhone = ""): Client
    {
        return new Client($phone, $name, "", "", $email, "", $extendedPhone);
    }

    /**
     * Создает параметры кредита
     * @param Product[] $productsArray Список товаров
     * @param float|null $initialPayment Предполагаемый первоначальный платеж
     * @param int|null $creditPeriod Предполагаемый срок кредита
     * @param float|null $discount Размер скидки. Если скидка не указывается, то можно указывать 0.
     * @param string|null $bank Предполагаемый банк кредитования
     * @param string|null $action Предполагаемая акция (кредитный продукт)
     * @return Credit
     */
    public static function createCredit($productsArray, ?float $initialPayment = .0, ?int $creditPeriod = 0, ?float $discount = .0, ?string $bank = BanksEnum::NONE, ?string $action = ""): Credit
    {
        if (!$discount || $discount === "") {
            $discount = .0;
        }
        $creditSum = .0;
        $products = [];

        foreach ($productsArray as $productValue) {
            $product = new Product($productValue["Name"], $productValue["Price"], $productValue["Count"]);
            $products[] = $product;
            $creditSum += $product->getTotalPrice();
        }

        return new Credit($discount, $creditSum, $products, new CreditPreferences($initialPayment, $creditPeriod, $bank, $action));
    }

    private function structToArray($values, &$i): array
    {
        $child = [];
        if (isset($values[$i]['value'])) {
            $child[] = $values[$i]['value'];
        }

        while ($i++ < count($values)) {
            switch ($values[$i]['type']) {
                case 'cdata':
                    $child[] = $values[$i]['value'];
                    break;

                case 'complete':
                    $name = $this->strToLowerFirst($values[$i]['tag']);
                    if (!empty($name)) {
                        $child[$name] = ($values[$i]['value']) ?: '';
                        if (isset($values[$i]['attributes'])) {
                            $child[$name] = $values[$i]['attributes'];
                        }
                    }
                    break;

                case 'open':
                    $name = $this->strToLowerFirst($values[$i]['tag']);
                    $size = isset($child[$name]) ? count($child[$name]) : 0;
                    $child[$name][$size] = $this->structToArray($values, $i);
                    break;

                case 'close':
                    return $child;
                    break;
            }
        }
        return $child;
    }

    private function convertXMLReport(string $xml): array
    {
        $secondPartPos = strpos($xml, "<diffgr");
        $xml = substr($xml, $secondPartPos);

        $values = [];
        $tags = [];
        $array = [];

        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $xml, $values, $tags);
        xml_parser_free($parser);

        $skipFirst = true;
        foreach ($tags as $key => $value) {
            if ($key !== "Result") {
                continue;
            }
            // каждая пара вхождений массива это нижняя и верхняя
            // границы диапазона для определения
            for ($i = 0, $iMax = count($value); $i < $iMax; $i += 2) {
                if ($skipFirst) {
                    $skipFirst = false;
                    continue;
                }
                $offset = $value[$i] + 1;
                $len = $value[$i + 1] - $offset;
                $slicedArray = array_slice($values, $offset, $len);
                $array[] = $this->ParseArray($slicedArray);
            }
        }
        return $array;
    }

    private function parseArray($values): OrderReport
    {
        $report = [];
        foreach ($values as $item) {
            $value = $item["value"] ?? null;
            $report[$item["tag"]] = $value;
        }

        return new OrderReport($report);
    }

    /**
     * Определяет, загружено ли PHP расширение
     * @param string $extensionName Имя расширения
     * @return bool Результат проверки
     */
    private function extensionIncluded($extensionName): bool
    {
        return in_array($extensionName, get_loaded_extensions(), true);
    }

    function strToLowerFirst(?string $str = "", $encoding = 'UTF8'): string
    {
        if (!$str) {
            return $str;
        }

        return
            mb_strtolower(mb_substr($str, 0, 1, $encoding), $encoding) .
            mb_substr($str, 1, mb_strlen($str, $encoding), $encoding);
    }
}
