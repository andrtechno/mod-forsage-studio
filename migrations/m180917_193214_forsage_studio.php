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

class m180917_193214_forsage_studio extends Migration
{

    public $settingsForm = 'panix\mod\forsage\models\SettingsForm';

    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci ENGINE=InnoDB';
       /* $this->createTable('{{%forsage_studio}}', [
            'id' => $this->primaryKey()->unsigned(),
            'object_id' => $this->integer()->unsigned()->null(),
            'object_type' => $this->tinyInteger()->null(),
            'external_id' => $this->integer()->unsigned()->null(),
            'external_data' => $this->string(255)->null(),
        ], $tableOptions);

        $this->createIndex('object_id', '{{%forsage_studio}}', 'object_id');
        $this->createIndex('object_type', '{{%forsage_studio}}', 'object_type');
        $this->createIndex('external_id', '{{%forsage_studio}}', 'external_id');*/
        $this->addColumn(Product::tableName(), 'forsage_id', 'int');
        $this->addColumn(Product::tableName(), 'in_box', 'int');
        $this->addColumn(Supplier::tableName(), 'forsage_id', 'int');
        $this->addColumn(Brand::tableName(), 'forsage_id', 'int');
        $this->addColumn(Attribute::tableName(), 'forsage_id', 'int');
        $this->addColumn(AttributeOption::tableName(), 'forsage_id', 'int');

        $this->createIndex('forsage_id', Product::tableName(), 'forsage_id');
        $this->createIndex('forsage_id', Supplier::tableName(), 'forsage_id');
        $this->createIndex('forsage_id', Attribute::tableName(), 'forsage_id');
        $this->createIndex('forsage_id', AttributeOption::tableName(), 'forsage_id');
        $this->createIndex('forsage_id', Brand::tableName(), 'forsage_id');
        $this->loadSettings();
    }

    public function down()
    {
        //$this->dropTable('{{%forsage_studio}}');
        $this->dropColumn(Product::tableName(), 'forsage_id');
        $this->dropColumn(Product::tableName(), 'in_box');
        $this->dropColumn(Supplier::tableName(), 'forsage_id');
        $this->dropColumn(Brand::tableName(), 'forsage_id');
        $this->dropColumn(Attribute::tableName(), 'forsage_id');
        $this->dropColumn(AttributeOption::tableName(), 'forsage_id');
        if (Yii::$app->get('settings')) {
            Yii::$app->settings->clear('forsage');
        }
    }

}
