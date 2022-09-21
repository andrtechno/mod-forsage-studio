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


    public function rules()
    {
        return [
            [['hook_key','apikey'], "required"],
            //[['product_related_bilateral', 'group_attribute', 'smart_bc', 'smart_title'], 'boolean'],
            //[['label_expire_new', 'added_to_cart_count'], 'integer'],
            [['apikey','hook_key'], 'string'],
            [['out_stock_delete'], 'boolean'],

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
            'hook_key' => CMS::gen(30),
        ];
    }

}
