<?php

namespace panix\mod\forsage\models;

use panix\engine\CMS;
use panix\engine\SettingsModel;

class SettingsForm extends SettingsModel
{

    public static $category = 'forsage';
    protected $module = 'forsage';

    public $out_stock_delete;
    public $apikey;
    public $hook_key;
    public $cloth_type;
    public $boots_type;
    public function rules()
    {
        return [
            [['hook_key', 'apikey'], "required"],
            //[['product_related_bilateral', 'group_attribute', 'smart_bc', 'smart_title'], 'boolean'],
            [['boots_type', 'cloth_type'], 'integer'],
            [['boots_type', 'cloth_type'], 'default'],
            [['apikey', 'hook_key'], 'string'],
            [['out_stock_delete'], 'boolean'],
            ['apikey', 'match', 'pattern' => "/^[a-zA-Z0-9\._\-]+$/u"],
            ['hook_key', 'match', 'pattern' => "/^[a-zA-Z0-9]+$/u", 'message' => 'Только буквы и цифры'],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function defaultSettings()
    {
        return [
            'out_stock_delete' => true,
            'apikey' => '',
            'hook_key' => CMS::gen(50),
            'cloth_type' => ''
        ];
    }

}
