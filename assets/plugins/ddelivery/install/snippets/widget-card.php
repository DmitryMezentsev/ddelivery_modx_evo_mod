if (!isset($id)) $id = '';

$dom_id = 'dd-widget-card_' . $id;

?>
<div id="<?=$dom_id;?>"></div>

<script>
	new DDeliveryWidgetCard("<?=$dom_id;?>", {
		apiScript: "<?=DDeliveryPlugin::getWidgetAPIScript();?>",
		lang: "<?=isset($lang) ? $lang : DDELIVERY_LANG;?>",
		city: <?=isset($city) ? $city : 'undefined';?>,
		priceDeclared: <?=isset($priceDeclared) ? $priceDeclared : 'undefined';?>,
		pricePayment: <?=isset($pricePayment) ? $pricePayment : 'undefined';?>
	});
</script>