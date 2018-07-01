<?php

require_once 'DDeliveryTVShop.php';
require_once 'DDeliveryShopkeeper.php';
require_once 'DDeliveryHelpers.php';

final class DDeliveryPlugin {
    // Типы модулей Интернет-магазинов
    const TVSHOP     = DDeliveryTVShop::ID;
    const SHOPKEEPER = DDeliveryShopkeeper::ID;
    
    
    // Директория плагина в MODX
    const DIR = 'assets/plugins/ddelivery';
    
    
    // Пути к JS и CSS-файлам
    const JS_JQUERY  = self::DIR . '/assets/jquery.min.js';
    const JS_MAIN    = self::DIR . '/assets/:shop_mod:/main.js';
    const CSS_COMMON = self::DIR . '/assets/:shop_mod:/common.css';
    
    const JS_W_CART_API     = 'https://ddelivery.ru/front/widget-cart/public/api.js';
    const JS_W_TRACKING_API = 'https://ddelivery.ru/front/widget-tracking/public/api.js';
    const JS_W_CARD_API     = 'https://ddelivery.ru/front/widget-card/public/api.js';
    
    
    private static $instance = null;
    
    
    // API-ключ для интеграции
    private $api_key;
    // Ссылка на класс с функциями для выбранного модуля Интернет-магазина
    private $shop_module;
    
    
    private function __construct() {}
    private function __clone() {}
    
    
    /**
     * @param $shop_module int Тип модуля Интернет-магазина
     * @param $api_key string API-ключ
     * @return DDeliveryPlugin
     */
    public static function getInstance($shop_module = 0, $api_key = '')
    {
        if (self::$instance === null)
            self::$instance = new self();
        
        self::$instance->setShopModule($shop_module);
        self::$instance->setApiKey($api_key);
        
        return self::$instance;
    }
    
    
    /**
     * Возвращает путь к API-скрипту для использования виджетами
     * 
     * @return string
     */
    public static function getWidgetAPIScript()
    {
        global $modx;
        
        return $modx->getConfig('base_url') . self::DIR . '/dd-widget-api.php';
    }
    
    /**
     * Возвращает значение GET-параметра
     * 
     * @param $name string Имя параметра
     * @return mixed
     */
    public static function get($name)
    {
        return ($name && isset($_GET[$name])) ? $_GET[$name] : null;
    }
    
    /**
     * Возвращает значение POST-параметра
     * 
     * @param $name string Имя параметра
     * @return mixed
     */
    public static function post($name)
    {
        return ($name && isset($_POST[$name])) ? $_POST[$name] : null;
    }
    
    
    /**
     * Сеттер API-ключа
     * 
     * @param $api_key string API-ключ
     */
    private function setApiKey($api_key)
    {
        if (!$this->api_key && $api_key)
            $this->api_key = $api_key;
    }
    
    /**
     * Задает тип модуля Интернет-магазина
     * 
     * @param $shop_module int
     */
    private function setShopModule($shop_module)
    {
        if (!$this->shop_module && $shop_module)
        {
            switch($shop_module)
            {
                case self::TVSHOP:     $this->shop_module = DDeliveryTVShop;     break;
                case self::SHOPKEEPER: $this->shop_module = DDeliveryShopkeeper; break;
            }
        }
    }
    
    /**
     * Обрабатывает путь к подключаемому файлу, возвращая путь, соответствующий заданному модулю Интернет-магазина
     * 
     * @param $path string Путь к файлу
     * @return string
     */
    private function prepareAssetPath($path)
    {
        $shop_module = $this->shop_module;
        
        return str_replace(':shop_mod:', $shop_module::getAssetsSubdir(), $path);
    }
    
    /**
     * Проверяет соответствие API-ключа
     * 
     * @param $api_key string API-ключ для проверки
     * @return bool
     */
    public function checkApiKey($api_key)
    {
        return ($this->api_key && $this->api_key === $api_key);
    }
    
    /**
     * Геттер API-ключа
     * 
     * @return string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }
    
    /**
     * Если вызвана перед запуском плагина, плагин подключит к страницам клиентской части jQuery
     */
    public function includeJquery()
    {
        global $modx;
        
        if ($modx->isFrontend())
            $modx->regClientStartupScript(self::JS_JQUERY);
    }
    
    /**
     * Запускает плагин
     */
    public function run()
    {
        global $modx;
        
        $shop_module = $this->shop_module;
        $shop_module::init($this->api_key);
        
        // Подключение к фронту JS и CSS
        if ($modx->isFrontend())
        {
            $inlineJs  = '<script>';
            $inlineJs .= 'var DDELIVERY = {};';
            $inlineJs .= 'DDELIVERY.LANG = "' . DDELIVERY_LANG . '";';
            $inlineJs .= 'DDELIVERY.API_SCRIPT = "' . self::getWidgetAPIScript() . '";';
            $inlineJs .= 'DDELIVERY.WEIGHT = ' . $shop_module::getProductsWeight() . ';';
            $inlineJs .= 'DDELIVERY.PRODUCTS = ' . json_encode($shop_module::getProducts(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . ';';
            $inlineJs .= 'DDELIVERY.DISCOUNT = ' . $shop_module::getDiscount() . ';';
            $inlineJs .= 'DDELIVERY.CURRENCY = "' . $shop_module::getCurrency() . '";';
            $inlineJs .= '</script>';
            $modx->regClientStartupScript($inlineJs);
            
            $modx->regClientStartupScript($this->prepareAssetPath(self::JS_MAIN));
            $modx->regClientCSS($this->prepareAssetPath(self::CSS_COMMON));
            
            $modx->regClientStartupScript(self::JS_W_CART_API);
            $modx->regClientStartupScript(self::JS_W_TRACKING_API);
            $modx->regClientStartupScript(self::JS_W_CARD_API);
        }
    }
    
    /**
     * Возвращает список статусов заказа
     * 
     * @return array
     */
    public function getOrderStatuses()
    {
        $shop_module = $this->shop_module;
        return $shop_module::getStatuses();
    }
    
    /**
     * Возвращает список доступных способов оплаты
     * 
     * @return array
     */
    public function getPaymentMethods()
    {
        $shop_module = $this->shop_module;
        return $shop_module::getPaymentMethods();
    }
    
    /**
     * Назначает заказу в CMS новый статус и задает трекинг-номер
     * 
     * @param $ddelivery_id int       DDelivery ID заказа
     * @param $new_status   string    Новый статус заказа
     * @param $track_number string    Трекинг-номер
     * @return              bool
     */
    public function setStatusForOrder($ddelivery_id, $new_status, $track_number = '')
    {
        $order_id = DDeliveryHelpers::getMODXOrderID($ddelivery_id);
        if (!$order_id) return false;
        
        $shop_module = $this->shop_module;
        
        return $shop_module::setStatusForOrder($order_id, $new_status, $track_number);
    }
    
    
}