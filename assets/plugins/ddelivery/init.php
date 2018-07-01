<?php

/**
 * DDelivery Plugin for MODX Evolution 1.2+, Evolution CMS
 * Author: Dmitry Mezentsev
 *
 * https://ddelivery.ru/
 * https://github.com/DmitryMezentsev/ddelivery_modx_evo_mod
 */


require_once 'config.php';
require_once 'install/DDeliveryInstall.php';
require_once 'includes/DDeliveryPlugin.php';


// Начальная установка всех необходимых для работы плагина компонентов
if (!DDeliveryInstall::check()) DDeliveryInstall::run();


$ddelivery = DDeliveryPlugin::getInstance(DDELIVERY_SHOP_MODULE, DDELIVERY_API_KEY);

if (DDELIVERY_INCLUDE_JQUERY) $ddelivery->includeJquery();

$ddelivery->run();