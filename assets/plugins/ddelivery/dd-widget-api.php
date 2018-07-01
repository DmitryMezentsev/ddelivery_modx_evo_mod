<?php

include_once "config.php";
include_once "includes/DDeliveryWidgetApi.php";


$widgetApi = new DDeliveryWidgetApi();

$widgetApi->setApiKey(DDELIVERY_API_KEY);
$widgetApi->setMethod($_SERVER['REQUEST_METHOD']);
$widgetApi->setData(isset($_REQUEST['data']) ? $_REQUEST['data'] : []);

echo $widgetApi->submit($_REQUEST['url']);