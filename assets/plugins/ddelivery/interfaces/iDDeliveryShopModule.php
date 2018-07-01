<?php

// Интерфейс для классов модулей Интернет-магазинов
interface iDDeliveryShopModule {
    public static function getAssetsSubdir();
    
    public static function getStatuses();
    public static function getPaymentMethods();
    public static function setStatusForOrder($order_id, $new_status, $track_number);
    
    public static function getProductsWeight();
    public static function getProducts();
    public static function getDiscount();
    
    public static function getCurrency();
    
    public static function init($api_key);
}