<?php

namespace panix\mod\forsage\models;

use Yii;
use panix\engine\SettingsModel;
use yii\helpers\Html;
use yii\web\UploadedFile;

class SettingsForm extends SettingsModel
{

    public static $category = 'forsage';
    protected $module = 'forsage';

    public $out_stock_delete;


    public function rules()
    {
        return [
            //[['per_page'], "required"],
            //[['product_related_bilateral', 'group_attribute', 'smart_bc', 'smart_title'], 'boolean'],
            //[['label_expire_new', 'added_to_cart_count'], 'integer'],
            //[['added_to_cart_period'], 'string'],
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
        ];
    }

}
