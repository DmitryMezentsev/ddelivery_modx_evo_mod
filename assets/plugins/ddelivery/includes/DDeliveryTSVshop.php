<?php

require_once __DIR__ . '/../interfaces/iDDeliveryShopModule.php';
require_once 'DDeliveryHelpers.php';

// Функции для использования с модулем TSVshop
class DDeliveryTSVshop implements iDDeliveryShopModule {
    const ID = 1;
    
    
    /**
     * Подсчитывает итоговую стоимость товара с учетом влияния дополнительных параметров
     * 
     * @param $price string|int|float Исходная стоимость товара
     * @param $opts  string           Данные о влиянии на цену дополнительных параметров
     * @return       float
     */
    private static function calcProductTotalPrice($price, $opts = '')
    {
        $price = (float) $price;
        $opts = preg_split("/\s/", $opts);
        
        foreach($opts as $opt)
        {
            $opt = trim($opt);
            
            // Умножение первоначальной стоимости
            if ($opt[0] === '*')
                $price *= substr($opt, 1);
            // Уменьшение первоначальной стоимости
            elseif ($opt[0] === '-')
                $price -= substr($opt, 1);
            // Прибавление к первоначальной стоимости
            else
                $price += $opt;
        }
        
        return $price;
    }
    
    /**
     * Исправляет кривые имена товаров, к которым применены доп. параметры
     * 
     * @param $name string Имя товара
     * @return string
     */
    private static function fixProductName($name = '')
    {
        $name = preg_replace("/ldquo/", '(', $name);
        $name = preg_replace("/rdquo/", ')', $name);
        
        return $name;
    }
    
    /**
     * Возвращает данные корзины
     * 
     * @return array|null
     */
    private static function getSession()
    {
        global $session;
        include_once MODX_BASE_PATH . '/assets/snippets/tsvshop/include/cart.inc.php';
        
        return isset($_SESSION[$session]['orders']) ? $_SESSION[$session] : null;
    }
    
    /**
     * Устанавливает стоимость доставки
     * 
     * @param $price int|float|string Стоимость доставки
     */
    private static function setShippingPrice($price)
    {
        global $session;
        include_once MODX_BASE_PATH . '/assets/snippets/tsvshop/include/cart.inc.php';
        
        if (isset($_SESSION[$session]['result']['shipping']))
            $_SESSION[$session]['result']['shipping'] = $price;
    }
    
    /**
     * Возвращает распарсенное значение указанного Cookie
     * 
     * @param $name string
     * @return object
     */
    private static function getCookie($name)
    {
        return isset($_COOKIE[$name])
            ? DDeliveryHelpers::decodeCookie($_COOKIE[$name])
            : null;
    }
    
    /**
     * Определяет, является ли переданный способ оплаты/доставки оплатой/доставкой DDelivery
     * 
     * @param $name string Название способа
     * @return bool
     */
    private static function isDDelivery($name)
    {
        return (strpos($name, 'DDelivery') !== false);
    }
    
    /**
     * Вызывается после оформления заказа на сайте
     * 
     * @param $order          array  Данные только что созданного заказа
     * @param $dd_widget_data object Данные виджета
     * @param $dd_order_data  object Данные заказа в DDelivery
     * @param $api_key        string API-ключ
     */
    private static function onAfterOrderCreate($order, $dd_widget_data, $dd_order_data, $api_key)
    {
        global $modx;
        
        // Сохранение данных клиента из виджета
        if ($dd_widget_data && self::isDDelivery($order['result']['shiptype']))
        {
            $modx->db->update([
                'fio'    => isset($dd_widget_data->contacts->fullName)       ? $dd_widget_data->contacts->fullName       : '',
                'phone'  => isset($dd_widget_data->contacts->phone)          ? $dd_widget_data->contacts->phone          : '',
                'city'   => isset($dd_widget_data->city->name)               ? $dd_widget_data->city->name               : '',
                'zip'    => isset($dd_widget_data->contacts->address->index) ? $dd_widget_data->contacts->address->index : '',
                'adress' => DDeliveryHelpers::getShippingAddress($dd_widget_data),
            ], $modx->getFullTableName('shop_order'), 'numorder="' . $order['result']['numorder'] . '"');
        }
        
        // Создание записи о заказе в таблице
        if ($dd_order_data)
        {
            DDeliveryHelpers::saveDDeliveryOrderInfo([
                'order_id'             => $order['result']['numorder'],
                'ddelivery_id'         => $dd_order_data->id,
                'in_ddelivery_cabinet' => $dd_order_data->confirmed ? 1 : 0,
            ]);
        }
        
        // Отправка в DDelivery SDK статуса заказа, ID заказа и способа оплаты
        $response = DDeliveryHelpers::updateOrderInDDelivery($api_key, [
            'id'             => $dd_order_data->id,
            'status'         => $order['result']['status'],
            'cms_id'         => $order['result']['numorder'],
            'payment_method' => $order['result']['payment'],
        ]);
        
        // Если заказ был сразу перенесен в ЛК
        if ($response['status'] === 'ok' && isset($response['data']['cabinet_id']))
        {
            // Сохраняем его ID в ЛК и устанавливаем соответствующий флаг
            DDeliveryHelpers::saveDDeliveryOrderInfo([
                'ddelivery_id' => $response['data']['cabinet_id'],
                'in_ddelivery_cabinet' => 1,
            ], $order['result']['numorder']);
        }
        
        // Установка статуса "Ожидание оплаты" при использовании оплаты DDelivery
        if (self::isDDelivery($order['result']['payment']))
            self::setStatusForOrder($order['result']['numorder'], DDELIVERY_STATUS_BEFORE_PAY);
    }
    
    /**
     * Обработчик для события 'TSVshopOnOrderStatusUpdate'
     * 
     * @param $order_id   string|int ID заказа
     * @param $new_status string     Новый статус заказа
     * @param $api_key    string     API-ключ
     */
    private static function onOrderStatusUpdate($order_id, $new_status, $api_key)
    {
        // Если заказ ещё не был перенесен в ЛК DDelivery
        if (!DDeliveryHelpers::orderInDDeliveryCabinet($order_id))
        {
            $ddelivery_id = DDeliveryHelpers::getDDeliveryOrderID($order_id);
            
            if ($ddelivery_id)
            {
                // Отправка обновленного статуса в SDK DDelivery
                $response = DDeliveryHelpers::updateOrderInDDelivery($api_key, [
                    'id' => $ddelivery_id,
                    'status' => $new_status,
                ]);
                
                // Если запрос был отправлен успешно и заказ был перенесен в ЛК
                if ($response['status'] === 'ok' && isset($response['data']['cabinet_id']))
                {
                    // Устанавливаем соответствующий флаг и сохраняем его новый DDelivery ID
                    DDeliveryHelpers::saveDDeliveryOrderInfo([
                        'ddelivery_id' => $response['data']['cabinet_id'],
                        'in_ddelivery_cabinet' => 1,
                    ], $order_id);
                }
            }
        }
    }
    
    
    /**
     * Возвращает имя поддиректории в assets с файлами для данного модуля
     * 
     * @return string
     */
    public static function getAssetsSubdir()
    {
        return 'tsvshop';
    }
    
    /**
     * Возвращает список возможных статусов заказа
     * 
     * @return array
     */
    public static function getStatuses()
    {
        global $modx;
        
        $statuses = $modx->db->getValue($modx->db->select('value', $modx->getFullTableName('shop_conf'), "name='StatusOrder'"));
        $result = [];
        
        foreach(array_keys(DDeliveryHelpers::parseMODXStringList($statuses)) as $status) {
            $result[$status] = $status;
        };
        
        return $result;
    }
    
    /**
     * Возвращает список доступных способов оплаты
     * 
     * @return array
     */
    public static function getPaymentMethods()
    {
        global $modx;
        
        $methods = $modx->db->select('name', $modx->getFullTableName('shop_payments'), "active='1'");
        $result = [];
        
        while($method = $modx->db->getRow($methods)) {
            $result[$method['name']] = $method['name'];
        }
        
        return $result;
    }
    
    /**
     * Назначает заказу новый статус и задает трекинг-номер
     * 
     * @param $order_id     int       ID заказа
     * @param $new_status   string    Новый статус заказа
     * @param $track_number string    Трекинг-номер
     * @return              bool
     */
    public static function setStatusForOrder($order_id, $new_status, $track_number = '')
    {
        global $modx;
        
        $values = ['status' => $new_status];
        
        if ($track_number)
            $values['tracking'] = $track_number;
        
        return $modx->db->update($values, $modx->getFullTableName('shop_order'), "numorder='$order_id'");
    }
    
    /**
     * Возвращает общий вес корзины
     * 
     * @return int|float
     */
    public static function getProductsWeight()
    {
        $weight = 0;
        $session = self::getSession();
        
        if ($session)
        {
            foreach ($session['orders'] as $product)
            {
                $product_weight = DDeliveryHelpers::getProductTVValue($product['id'], 'weight', 0);
                $product_weight = str_replace(',', '.', $product_weight);
                
                $weight += $product_weight * $product['qty'];
            }
        }
        
        return $weight;
    }
    
    /**
     * Возвращает список товаров корзины для передачи в API виджета
     * 
     * @return array
     */
    public static function getProducts()
    {
        $products = [];
        $session = self::getSession();
        
        if ($session)
        {
            foreach ($session['orders'] as $product)
            {
                $products[] = [
                    'name'       => self::fixProductName($product['name']),
                    'vendorCode' => $product['articul'],
                    'barcode'    => DDeliveryHelpers::getProductTVValue($product['id'], 'barcode', ''),
                    'nds'        => (int) DDeliveryHelpers::getProductTVValue($product['id'], 'vat', 0),
                    'price'      => self::calcProductTotalPrice($product['price'], $product['opt']),
                    'count'      => $product['qty'],
                ];
            }
        }
        
        return $products;
    }
    
    /**
     * Возвращает общую скидку на заказ в рублях
     * 
     * @return int
     */
    public static function getDiscount()
    {
        global $tsvshop;
        
        $discount = 0;
        
        // Скидка по подарочному сертификату
        if (isset($tsvshop['sertificatsum']))
            $discount += $tsvshop['sertificatsum'];
        
        // Иные скидки
        if (isset($tsvshop['discountsize']))
            $discount += $tsvshop['discountsize'];
        
        return $discount;
    }
    
    /**
     * Возвращает текущую валюту магазина
     * 
     * @return string
     */
    public static function getCurrency()
    {
        global $modx;
        
        return $modx->db->getValue($modx->db->select('value', $modx->getFullTableName('shop_conf'), "name='MonetarySymbol'"));
    }
    
    /**
     * Общий код, который всегда должен вызываться для данного модуля магазина
     * 
     * @param $api_key string API-ключ DDelivery
     */
    public static function init($api_key)
    {
        global $modx;
        
        $dd_widget_data = self::getCookie('DDWidgetData');
        $dd_order_data = self::getCookie('DDOrderData');
        
        if ($dd_widget_data)
        {
            // Курьерская и Почта России
            if (isset($dd_widget_data->delivery->total_price))
                self::setShippingPrice($dd_widget_data->delivery->total_price);
            // Самовывоз
            elseif (isset($dd_widget_data->delivery->point->price_delivery))
                self::setShippingPrice($dd_widget_data->delivery->point->price_delivery);
            else
                self::setShippingPrice(0);
        }
        
        // Оформление заказа
        if (isset($_SESSION['tsvshopfin']) && !self::getSession() && $dd_widget_data && $dd_order_data)
        {
            self::onAfterOrderCreate($_SESSION['tsvshopfin'], $dd_widget_data, $dd_order_data, $api_key);
            
            DDeliveryHelpers::deleteCookie('DDOrderData');
        }
        
        // Обновление статуса заказа
        if ($modx->event->name === 'TSVshopOnOrderStatusUpdate')
        {
            self::onOrderStatusUpdate($modx->event->params['idorder'], $modx->event->params['newstatus'], $api_key);
        }
    }
}