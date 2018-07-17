<?php

require_once 'includes/DDeliveryPlugin.php';


// Укажите здесь свой API-ключ DDelivery
// Ключ можно скопировать со страницы магазина в Личном Кабинете DDelivery
define('DDELIVERY_API_KEY', '');

// Задайте тип используемого вами модуля Интернет-магазина
// Возможные варианты: DDeliveryPlugin::TSVSHOP, DDeliveryPlugin::SHOPKEEPER
define('DDELIVERY_SHOP_MODULE', DDeliveryPlugin::TSVSHOP);

// Статус заказа до получения оплаты (в случае использования эквайринга DDelivery)
define('DDELIVERY_STATUS_BEFORE_PAY', 'Ожидание оплаты');
// Статус заказа после получения оплаты (в случае использования эквайринга DDelivery)
define('DDELIVERY_STATUS_AFTER_PAY', 'Оплачено');

// Требуется ли подключать к странице jQuery
// Замените false на true в случае появления ошибки 'jQuery is not found'
define('DDELIVERY_INCLUDE_JQUERY', false);

// Язык интерфейса виджетов по умолчанию
// Возможные значения: 'ru', 'en', 'zh'
define('DDELIVERY_LANG', 'ru');