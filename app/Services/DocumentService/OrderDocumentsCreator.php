<?php

namespace App\Services\DocumentService;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Order\OrderDocument;
use App\Services\OrderService;
use Cms\Core\CmsException;
use Cms\Dto\OptionDto;
use Cms\Services\OptionService\OptionService;
use Greensight\Customer\Dto\CustomerDocumentDto;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Throwable;

abstract class OrderDocumentsCreator extends DocumentCreator
{
    protected OrderService $orderService;
    protected OptionService $optionService;

    protected ?Order $order = null;
    protected bool $isProductType;
    protected CustomerDto $customer;

    public const MAPPING_KEYS = [
        'short_name' => OptionDto::KEY_ORGANIZATION_CARD_SHORT_NAME,
        'full_name' => OptionDto::KEY_ORGANIZATION_CARD_FULL_NAME,

        'inn' => OptionDto::KEY_ORGANIZATION_CARD_INN,
        'kpp' => OptionDto::KEY_ORGANIZATION_CARD_KPP,
        'okpo' => OptionDto::KEY_ORGANIZATION_CARD_OKPO,
        'ogrn' => OptionDto::KEY_ORGANIZATION_CARD_OGRN,

        'fact_address' => OptionDto::KEY_ORGANIZATION_CARD_FACT_ADDRESS,
        'legal_address' => OptionDto::KEY_ORGANIZATION_CARD_LEGAL_ADDRESS,

        'payment_account' => OptionDto::KEY_ORGANIZATION_CARD_PAYMENT_ACCOUNT,
        'bank_bik' => OptionDto::KEY_ORGANIZATION_CARD_BANK_BIK,
        'bank_name' => OptionDto::KEY_ORGANIZATION_CARD_BANK_NAME,
        'correspondent_account' => OptionDto::KEY_ORGANIZATION_CARD_CORRESPONDENT_ACCOUNT,

        'ceo_last_name' => OptionDto::KEY_ORGANIZATION_CARD_CEO_LAST_NAME,
        'ceo_first_name' => OptionDto::KEY_ORGANIZATION_CARD_CEO_FIRST_NAME,
        'ceo_middle_name' => OptionDto::KEY_ORGANIZATION_CARD_CEO_MIDDLE_NAME,
        'ceo_document_number' => OptionDto::KEY_ORGANIZATION_CARD_CEO_DOCUMENT_NUMBER,

        'logistics_manager_last_name' => OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_LAST_NAME,
        'logistics_manager_first_name' => OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_FIRST_NAME,
        'logistics_manager_middle_name' => OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_MIDDLE_NAME,
        'logistics_manager_phone' => OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_PHONE,
        'logistics_manager_email' => OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_EMAIL,

        'general_accountant_last_name' => OptionDto::KEY_ORGANIZATION_CARD_GENERAL_ACCOUNTANT_LAST_NAME,
        'general_accountant_first_name' => OptionDto::KEY_ORGANIZATION_CARD_GENERAL_ACCOUNTANT_FIRST_NAME,
        'general_accountant_middle_name' => OptionDto::KEY_ORGANIZATION_CARD_GENERAL_ACCOUNTANT_MIDDLE_NAME,
        'general_accountant_phone' => OptionDto::KEY_ORGANIZATION_CARD_GENERAL_ACCOUNTANT_PHONE,
        'general_accountant_email' => OptionDto::KEY_ORGANIZATION_CARD_GENERAL_ACCOUNTANT_EMAIL,

        'contact_centre_phone' => OptionDto::KEY_ORGANIZATION_CARD_CONTACT_CENTRE_PHONE,
        'social_phone' => OptionDto::KEY_ORGANIZATION_CARD_SOCIAL_PHONE,
        'email_for_merchant' => OptionDto::KEY_ORGANIZATION_CARD_EMAIL_FOR_MERCHANT,
        'common_email' => OptionDto::KEY_ORGANIZATION_CARD_COMMON_EMAIL,
        'email_for_claim' => OptionDto::KEY_ORGANIZATION_CARD_EMAIL_FOR_CLAIM,
    ];

    public function __construct(OrderService $orderService, OptionService $optionService)
    {
        $this->orderService = $orderService;
        $this->optionService = $optionService;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;
        $this->isProductType = $order->type === Basket::TYPE_PRODUCT;

        return $this;
    }

    public function setCustomer(int $id): self
    {
        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);
        $customerQuery = $customerService->newQuery()
            ->addFields(CustomerDto::entity(), 'id', 'legal_info_company_name', 'legal_info_inn')
            ->setFilter('id', $id);
        $this->customer = $customerService->customers($customerQuery)->first();

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function createOrderDocumentRecord(int $orderId, int $fileId, string $type): void
    {
        $orderDocument = new OrderDocument([
            'order_id' => $orderId,
            'file_id' => $fileId,
            'type' => $type,
        ]);
        $orderDocument->saveOrFail();
    }

    public function createRecordInCustomerDocuments(int $customerId, int $fileId, ?string $title = null): void
    {
        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);
        $customerDocument = new CustomerDocumentDto();
        $customerDocument->file_id = $fileId;
        $customerDocument->type = CustomerDocumentDto::TYPE_CONTRACT;
        $customerDocument->title = $title;
        $customerService->createDocument($customerId, $customerDocument);
    }

    protected function resultDocSuffix(): string
    {
        return $this->order->number;
    }

    /**
     * @throws CmsException
     */
    protected function getOrganizationInfo(): array
    {
        $organizationCardKeys = array_values(self::MAPPING_KEYS);
        $options = $this->optionService->get($organizationCardKeys);

        return collect(self::MAPPING_KEYS)
            ->map(function ($item) use ($options) {
                return $options[$item];
            })
            ->all();
    }
}
