<?php

/**
 * Generation migrate by PIXELION CMS
 *
 * @author PIXELION CMS development team <dev@pixelion.com.ua>
 * @link http://pixelion.com.ua PIXELION CMS
 *
 * Class m170908_104527_forsage_studio
 */

use yii\db\Migration;
use panix\mod\shop\models\Product;

class m180917_193214_forsage_studio extends Migration
{


    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci ENGINE=InnoDB';
        $this->createTable('{{%forsage_studio}}', [
            'id' => $this->primaryKey()->unsigned(),
            'object_id' => $this->integer()->unsigned()->null(),
            'object_type' => $this->tinyInteger()->null(),
            'external_id' => $this->integer()->unsigned()->null(),
            'external_data' => $this->string(255)->null(),
        ], $tableOptions);

        $this->createIndex('object_id', '{{%forsage_studio}}', 'object_id');
        $this->createIndex('object_type', '{{%forsage_studio}}', 'object_type');
        $this->createIndex('external_id', '{{%forsage_studio}}', 'external_id');
        $this->addColumn(Product::tableName(),'forsage_id','int');

    }

    public function down()
    {
        $this->dropTable('{{%forsage_studio}}');
        $this->dropColumn(Product::tableName(),'forsage_id');
    }

}
