if (!isset($id)) $id = '';

$dom_id = 'dd-widget-tracking_' . $id;

?>
<div id="<?=$dom_id;?>"></div>

<script>
	new DDeliveryWidgetTracking("<?=$dom_id;?>", {
		apiScript: "<?=DDeliveryPlugin::getWidgetAPIScript();?>",
		lang: "<?=isset($lang) ? $lang : DDELIVERY_LANG;?>",
		autofocus: <?=(isset($autofocus) && $autofocus == '0') ? 'false' : 'true';?>
	});
</script>