<?php

/**
 * Generation migrate by PIXELION CMS
 *
 * @author PIXELION CMS development team <dev@pixelion.com.ua>
 * @link http://pixelion.com.ua PIXELION CMS
 *
 * Class m170908_104527_forsage_studio
 */

use panix\engine\db\Migration;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\Supplier;
use panix\mod\shop\models\Brand;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\ProductImage;
use panix\mod\shop\models\Category;

class m200917_193214_forsage_studio extends Migration
{

    public $settingsForm = 'panix\mod\forsage\models\SettingsForm';

    public function up()
    {
        $this->addColumn(Product::tableName(), 'forsage_id', $this->integer()->null());
        //$this->addColumn(Product::tableName(), 'ukraine', $this->boolean()->defaultValue(0));
        //$this->addColumn(Product::tableName(), 'leather', $this->boolean()->defaultValue(0));
        $this->addColumn(Supplier::tableName(), 'forsage_id', $this->integer()->null());
        $this->addColumn(Brand::tableName(), 'forsage_id', $this->integer()->null());
        $this->addColumn(Attribute::tableName(), 'forsage_id', $this->integer()->null());
        $this->addColumn(ProductImage::tableName(), 'forsage_id', $this->integer()->null());
        $this->addColumn(Category::tableName(), 'path_hash', $this->string(32)->null());

        //$this->createIndex('forsage_id', Product::tableName(), 'forsage_id');
        //$this->createIndex('forsage_id', Supplier::tableName(), 'forsage_id');
        //$this->createIndex('forsage_id', Attribute::tableName(), 'forsage_id');
        //$this->createIndex('forsage_id', Brand::tableName(), 'forsage_id');
        $this->loadSettings();
    }

    public function down()
    {

        $this->dropColumn(Product::tableName(), 'forsage_id');
        //$this->dropColumn(Product::tableName(), 'ukraine');
        //$this->dropColumn(Product::tableName(), 'leather');
        $this->dropColumn(Supplier::tableName(), 'forsage_id');
        $this->dropColumn(Brand::tableName(), 'forsage_id');
        $this->dropColumn(Attribute::tableName(), 'forsage_id');
        $this->dropColumn(ProductImage::tableName(), 'forsage_id');
        $this->dropColumn(Category::tableName(), 'path_hash');
        if (Yii::$app->get('settings')) {
            Yii::$app->settings->clear('forsage');
        }
    }

}
