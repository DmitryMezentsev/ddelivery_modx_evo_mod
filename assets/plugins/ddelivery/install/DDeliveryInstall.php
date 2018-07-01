<?php

final class DDeliveryInstall {
    // Имя таблицы заказов
    const ORDERS_TABLE_NAME = 'ddelivery_orders';
    // Название категории
    const CATEGORY_NAME = 'DDelivery';
    // Директория с кодом сниппетов
    const SNIPPETS_DIR = '/install/snippets/';
    
    
    /**
     * Создает сниппет
     * 
     * @param $name string Имя сниппета
     * @param $description string Описание
     * @param $code string Код сниппета
     * @param $category_id int ID категории
     */
    private static function createSnippet($name, $description = '', $code = '', $category_id = 0)
    {
        global $modx;
        
        $modx->db->insert([
            'name'        => $name,
            'description' => $description,
            'editor_type' => 2, // code editor
            'category'    => $category_id,
            'snippet'     => $code,
        ], $modx->getFullTableName('site_snippets'));
    }
    
    /**
     * Возвращает код сниппета из директории со сниппетами
     * 
     * @param $name string Имя файла сниппета
     * @return string
     */
    private static function getSnippetCode($name)
    {
        ob_start();
        
        readfile(MODX_BASE_PATH . 'assets/plugins/ddelivery' . self::SNIPPETS_DIR . $name . '.php');
        $code = ob_get_contents();
        ob_end_clean();
        
        return $code;
    }
    
    /**
     * Создает ресурс для API
     * 
     * @param $page_title  string  Заголовок ресурса
     * @param $alias       string  Алиас URL
     * @param $content     string  Содержимое (код вызова сниппета)
     * @param $parent      int     ID родительского ресурса
     * @param $is_folder   int     Является ли контейнером
     * @return             int     ID созданного ресурса
     */
    private static function createResource($page_title, $alias, $content = '', $parent = 0, $is_folder = 0)
    {
        global $modx;
        
        return $modx->db->insert([
            'type'        => 'document',
            'contentType' => 'application/json',
            'pagetitle'   => $page_title,
            'alias'       => $alias,
            'published'   => 1,
            'content'     => $content,
            'richtext'    => 0,
            'searchable'  => 0,
            'cacheable'   => 0,
            'donthit'     => 1,
            'hidemenu'    => 1,
            'parent'      => $parent,
            'isfolder'    => $is_folder,
        ], $modx->getFullTableName('site_content'));
    }
    
    
    /**
     * Проверяет, был ли уже установлен плагин
     * 
     * @return bool
     */
    public static function check()
    {
        global $modx;
        
        $table_name = $modx->db->config['table_prefix'] . self::ORDERS_TABLE_NAME;
        
        // Проверяем по наличию таблицы
        return (bool) $modx->db->query('SHOW TABLES LIKE "' . $table_name . '"')->num_rows;
    }
    
    /**
     * Запускает установку плагина
     */
    public static function run()
    {
        global $modx;
        
        // Создание таблицы заказов
        $modx->db->query(
            'CREATE TABLE ' . $modx->getFullTableName(self::ORDERS_TABLE_NAME) . ' (
                `order_id` INT(11) NOT NULL,
                `ddelivery_id` VARCHAR(48) NULL DEFAULT NULL,
                `in_ddelivery_cabinet` TINYINT(1) UNSIGNED NOT NULL DEFAULT "0"
            )
            COLLATE="utf8_general_ci"
            ENGINE=MyISAM'
        );
        
        // Создание категории
        $category_id = $modx->db->insert(['category' => self::CATEGORY_NAME], $modx->getFullTableName('categories'));
        
        // Создание сниппетов
        self::createSnippet('DDeliveryWidgetCard', 'Сниппет карточного виджета DDelivery', self::getSnippetCode('widget-card'), $category_id);
        self::createSnippet('DDeliveryWidgetTracking', 'Сниппет трекинг-виджета DDelivery', self::getSnippetCode('widget-tracking'), $category_id);
        self::createSnippet('DDeliveryAPI', 'Сниппет API для интеграции с DDelivery', self::getSnippetCode('api'), $category_id);
        
        // Создание ресурсов для интеграции с DDelivery SDK API
        $dd_api_folder_id = self::createResource('DDelivery API', 'ddelivery-api', '', 0, 1);
        self::createResource('statuses.json', 'statuses.json', '[!DDeliveryAPI &api=`statuses`!]', $dd_api_folder_id);
        self::createResource('payment-methods.json', 'payment-methods.json', '[!DDeliveryAPI &api=`payment-methods`!]', $dd_api_folder_id);
        self::createResource('traffic-orders.json', 'traffic-orders.json', '[!DDeliveryAPI &api=`traffic-orders`!]', $dd_api_folder_id);
        
        // Очистка кэша
        $modx->clearCache('full');
    }
}