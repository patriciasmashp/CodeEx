<?

namespace lib\Order;

use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem;
/**
 * Класс обертка над Bitrix API для создания заказа и получения ссылки на оплату
 */
class OrderPayment
{
    private const PRODUCT_ID = 317;
    private const DELIVERY_ID = 3;
    private const TINKOF_PAYMENT_ID = 7;
    private const PRODUCT_PRICE_ID = 314;

    private $siteId;
    private $currencyCode;
    private $userId;
    private $order;
    private $price;

    public function __construct($price)
    {
        global $USER;
        $this->siteId = Context::getCurrent()->getSite();
        $this->currencyCode = CurrencyManager::getBaseCurrency();
        $this->userId = $USER->GetID();
        $this->order = $this->getOrder();
        $this->price = $price;
    }

    /**
     * Функция создает и возвращает объект заказа
     * 
     * @link https://dev.1c-bitrix.ru/api_d7/bitrix/sale/classes/order/index.php
     * @return Order объект заказа
     **/
    private function getOrder():Order
    {
        $order = Order::create($this->siteId, $this->userId);
        $order->setPersonTypeId(1);
        $order->setField('CURRENCY', $this->currencyCode);


        return $order;
    }

    /**
     * Функция создает и возвращает объект корзины
     * 
     * @link https://dev.1c-bitrix.ru/api_d7/bitrix/sale/classes/basket/index.php
     * @return Basket объект корзины
     **/
    private function getBasket(Int $quantity = 1):Basket
    {
        $basket = Basket::create($this->siteId);
        $item = $basket->createItem('catalog', self::PRODUCT_ID);
        $item->setFields([
            'QUANTITY' => $quantity,
            'CURRENCY' => $this->currencyCode,
            'LID' => $this->siteId,
            'CUSTOM_PRICE' => 'Y',
            'PRICE' => $this->price,
            'NAME' => 'Оплата задолженности',
            'PRODUCT_PRICE_ID' => self::PRODUCT_PRICE_ID
        ]);
         

        return $basket;
    }

     /**
     * Функция привязывает доставку к заказу
     * 
     * @param $item Продукт - элемент корзины
     **/
    private function setShipment($item):Void
    {
        $shipmentCollection = $this->order->getShipmentCollection();
        $shipment = $shipmentCollection->createItem();
        $service = Delivery\Services\Manager::getById(self::DELIVERY_ID);
        
        $shipment->setFields(array(
            'DELIVERY_ID' => $service['ID'],
            'DELIVERY_NAME' => $service['NAME'],
        ));
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        $shipmentItem = $shipmentItemCollection->createItem($item);
        $shipmentItem->setQuantity($item->getQuantity());

    }

    /**
     * Функция устанавливает платежную систему для заказа
     **/
    private function setPayment():Void
    {
        $paymentCollection = $this->order->getPaymentCollection();
        $paySystemService = PaySystem\Manager::getObjectById(self::TINKOF_PAYMENT_ID);
        $payment = $paymentCollection->createItem();
        $payment->setFields([
            'PAY_SYSTEM_ID' => $paySystemService->getField("PAY_SYSTEM_ID"),
            'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
            'SUM' => $this->price
        ]);
    }

     /**
     * Функция добавляет свойства EMAIL и PERSONAL_PHONE пользователя к заказу
     **/
    private function setProperties():Void
    {
        global $USER;
        $userData = $USER->GetByID($USER->GetID())->Fetch();

        $propertyCollection = $this->order->getPropertyCollection();       
        $phoneProp = $propertyCollection->getPhone();
        $phoneProp->setValue($userData["PERSONAL_PHONE"]);
    
        $nameProp = $propertyCollection->getUserEmail();
        $nameProp->setValue($userData["EMAIL"]);
    
    }

    /**
     * Функция инициирует оплату и возвращает ссылку на нее
     * 
     * возвращает HTML код формы оплаты
     * 
     * @return String HTML код формы оплаты
     **/
    public function getPay():String
    {
        $paymentCollection = $this->order->getPaymentCollection();
        $payment = $paymentCollection[0];
        
        $paySystemService = PaySystem\Manager::getObjectById($payment->getPaymentSystemId());
        $context = \Bitrix\Main\Application::getInstance()->getContext();
        $initResult = $paySystemService->initiatePay($payment, $context->getRequest(), \Bitrix\Sale\PaySystem\BaseServiceHandler::STRING);
        $buffered_output = $initResult->getTemplate();


        return $buffered_output;
    }

    /**
     * Функция-билдер заказа. 
     * 
     * возвращает Id созданного заказа
     * 
     * @return Int id заказа
     **/
    public function create():Int
    {
        $basket = $this->getBasket();
        $this->order->setBasket($basket);
        $product = $basket->getBasketItems()[0];
        $this->setShipment($product);

        if($this->order->isAllowPay())
        {
            $payment = $this->setPayment();
        }

        $this->setProperties();

        $this->order->doFinalAction(true);
        $this->order->save();
        $orderId = $this->order->getId();


        return $orderId;
    }
}
