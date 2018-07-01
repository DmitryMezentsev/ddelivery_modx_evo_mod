$ddelivery = DDeliveryPlugin::getInstance();

// Проверка ключа, переданного в GET-параметре 'k'
if ($ddelivery->checkApiKey(DDeliveryPlugin::get('k')))
{
	if ($api === 'statuses')
	{
		return json_encode($ddelivery->getOrderStatuses());
	}

	if ($api === 'payment-methods')
	{
		return json_encode($ddelivery->getPaymentMethods());
	}

	if ($api === 'traffic-orders')
	{
		$id           = DDeliveryPlugin::post('id');
		$status_cms   = DDeliveryPlugin::post('status_cms');
		$track_number = DDeliveryPlugin::post('track_number');
		
		if ($id && $status_cms)
		{
			return json_encode($ddelivery->setStatusForOrder($id, $status_cms, $track_number)
				? ['status' => 'ok']
				: ['status' => 'error']);
		}
		else
		{
			header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
		}
	}
}
else
{
	header($_SERVER['SERVER_PROTOCOL'] . ' 401 Unauthorized');
}
