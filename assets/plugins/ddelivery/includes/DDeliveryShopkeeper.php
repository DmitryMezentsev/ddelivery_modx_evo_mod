<?php

require_once __DIR__ . '/../interfaces/iDDeliveryShopModule.php';

// Функции для использования с модулем Shopkeeper
class DDeliveryShopkeeper implements iDDeliveryShopModule {
    const ID = 2;
    
    
    /**
     * Возвращает имя поддиректории в assets с файлами для данного модуля
     * 
     * @return string
     */
    public static function getAssetsSubdir()
    {
        return 'shopkeeper';
    }
    
    /**
     * Возвращает список возможных статусов заказа
     * 
     * @return array
     */
    public static function getStatuses()
    {
        global $modx;
        
        
        
        return [];
    }
    
    /**
     * Возвращает список доступных способов оплаты
     * 
     * @return array
     */
    public static function getPaymentMethods()
    {
        return [];
    }
    
    /**
     * Назначает заказу новый статус и задает трекинг-номер
     * 
     * @param $order_id     int       ID заказа
     * @param $new_status   string    Новый статус заказа
     * @param $track_number string    Трекинг-номер
     * @return              bool
     */
    public static function setStatusForOrder($order_id, $new_status, $track_number)
    {
        global $modx;
        
        return false;
    }
    
    /**
     * Возвращает общий вес корзины
     * 
     * @return int|float
     */
    public static function getProductsWeight()
    {
        return 0;
    }
    
    /**
     * Возвращает список товаров корзины для передачи в API виджета
     * 
     * @return array
     */
    public static function getProducts()
    {
        return [];
    }
    
    /**
     * Возвращает общую скидку на заказ в рублях
     * 
     * @return int
     */
    public static function getDiscount()
    {
        return 0;
    }
    
    /**
     * Возвращает текущую валюту магазина
     * 
     * @return string
     */
    public static function getCurrency()
    {
        
    }
    
    /**
     * Общий код, который всегда должен вызываться для данного модуля магазина
     * 
     * @param $api_key string API-ключ DDelivery
     */
    public static function init($api_key)
    {
        exit('Модуль Shopkeeper в настоящий момент не поддерживается. Плагин ещё находится в разработке.');
    }
}