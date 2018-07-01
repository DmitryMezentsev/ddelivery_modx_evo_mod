<?php

require_once __DIR__ . '/../install/DDeliveryInstall.php';
require_once __DIR__ . '/../includes/Browser.php';

class DDeliveryHelpers {
    /**
     * Преобразует строку вида 'a==1||b==2||c==3' в ассоциативный массив
     * 
     * @param $list string Строка со списком значений
     * @return array
     */
    public static function parseMODXStringList($list)
    {
        $result = [];
        
        if ($list)
        {
            array_walk(preg_split("/\|\|/", $list), function ($item) use (&$result) {
                $data = preg_split("/==/", $item);
                
                if ($data[0] && isset($data[1]))
                    $result[$data[0]] = $data[1];
            });
        }
        
        return $result;
    }
    
    /**
     * Обновляет данные заказа на сервере DDelivery
     * 
     * @param $api_key  string  API-ключ
     * @param $data     array   Параметры запроса
     */
    public static function updateOrderInDDelivery($api_key, $data)
    {
        $curl = curl_init('https://ddelivery.ru/api/' .  $api_key. '/sdk/update-order.json');
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        return $response;
    }
    
    /**
     * Возвращает ID заказа в MODX по DDelivery ID заказа
     * 
     * @param $ddelivery_id string|int DDelivery ID заказа
     * @return int
     */
    public static function getMODXOrderID($ddelivery_id)
    {
        global $modx;
        
        $result = $modx->db->select('order_id', $modx->getFullTableName(DDeliveryInstall::ORDERS_TABLE_NAME), "ddelivery_id='$ddelivery_id'", '', 1);
        return (int) $modx->db->getValue($result);
    }
    
    /**
     * Возвращает DDelivery ID заказа по его ID в CMS
     * 
     * @param $order_id string|int ID заказа
     * @return string
     */
    public static function getDDeliveryOrderID($order_id)
    {
        global $modx;
        
        $result = $modx->db->select('ddelivery_id', $modx->getFullTableName(DDeliveryInstall::ORDERS_TABLE_NAME), "order_id='$order_id'", '', 1);
        return $modx->db->getValue($result);
    }
    
    /**
     * Проверяет, является ли заказ уже перенесенным в ЛК DDelivery
     *
     * @param $id               string|int  ID заказа
     * @param $is_ddelivery_id  bool        Следует установить в true, если передается DDelivery ID, а не CMS ID
     * @return                  bool
     */
    public static function orderInDDeliveryCabinet($id, $is_ddelivery_id = false)
    {
        global $modx;
        
        $where = ($is_ddelivery_id ? 'ddelivery_id' : 'order_id') . "='$id'";
        $result = $modx->db->select('in_ddelivery_cabinet', $modx->getFullTableName(DDeliveryInstall::ORDERS_TABLE_NAME), $where, '', 1);
        
        return (bool) $modx->db->getValue($result);
    }
    
    /**
     * Сохраняет информацию о DDelivery-заказе в таблице 'ddelivery_orders'
     * 
     * @param $values   array       Значения для вставки/обновления записи
     * @param $order_id int|string  ID заказа, данные которого нужно обновить
     */
    public static function saveDDeliveryOrderInfo($values, $order_id = null)
    {
        global $modx;
        
        $table = $modx->getFullTableName(DDeliveryInstall::ORDERS_TABLE_NAME);
        
        if ($order_id)
            $modx->db->update($values, $table, "order_id='$order_id'");
        else
            $modx->db->insert($values, $table);
    }
    
    /**
     * Возвращает значение TV-параметра товара
     * 
     * @param $id      int|string ID товара
     * @param $name    string     Имя параметра
     * @param $default mixed      Значение по умолчанию, если TV-параметр отсутствует или не задан
     * @return         mixed
     */
    public static function getProductTVValue($id, $name, $default = null)
    {
        global $modx;
        
        $tv = $modx->getTemplateVar($name, '*', $id);
        
        return ($tv && $tv['value'])
            ? $tv['value']
            : $default;
    }
    
    /**
     * Преобразует строку с JSON из Cookies в объект с данными
     * 
     * @param $json_str string
     * @return object
     */
    public static function decodeCookie($json_str)
    {
        $json_str = self::removeSanitizeSeed($json_str);
        
        $browser = (new Browser())->getBrowser();
        
        // Перекодирование в UTF-8 нужно для IE/Edge
        if ($browser === Browser::BROWSER_IE || $browser === Browser::BROWSER_POCKET_IE || $browser === Browser::BROWSER_EDGE)
            $json_str = mb_convert_encoding($json_str, 'utf-8', 'windows-1251');
        
        return json_decode(urldecode($json_str));
    }
    
    /**
     * Удаляет sanitize seed из строки
     * 
     * @param $str string
     * @return string
     */
    public static function removeSanitizeSeed($str)
    {
        global $sanitize_seed;
        
        if (strpos($str, $sanitize_seed) !== false)
            $str = str_replace($sanitize_seed, '', $str);
        
        return $str;
    }
    
    /**
     * Возвращает адрес доставки из объекта с данными виджета
     * 
     * @param $widgetData object
     * @return string
     */
    public static function getShippingAddress($widget_data)
    {
        if (!isset($widget_data->delivery->type)) return '';
        
        $address = '';
        
        if (intval($widget_data->delivery->type) === 1 && isset($widget_data->delivery->point->address))
        {
            // Адрес точки самовывоза
            $address = $widget_data->delivery->point->address;
        }
        elseif (isset($widget_data->contacts->address))
        {
            // Адрес клиента
            $a = $widget_data->contacts->address;

            if (isset($a->street)) $address .= $a->street . ', ';
            if (isset($a->house))  $address .= $a->house . ', ';
            if (isset($a->flat))   $address .= $a->flat;
        }
        
        return trim($address);
    }
    
    /**
     * Удаляет Cookie с указанным именем
     * 
     * @param $name string Имя Cookie
     */
    public static function deleteCookie($name)
    {
        setcookie($name, null, -1, '/');
    }
    
    
}