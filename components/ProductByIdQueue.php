<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\helpers\Console;
use yii\queue\JobInterface;

class ProductByIdQueue extends BaseObject implements JobInterface
{
    public $id;

    public function execute($queue)
    {
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $fs = new $forsageClass;
        $product = $fs->getProduct($this->id);
        if ($product) {
            if ($product->execute()) {
                Yii::info('addProduct ' . $this->id, 'forsage');
            } else {
                Yii::info('NotAddProduct  ' . $this->id, 'forsage');
            }
        }else{
            Yii::info('NoGetProduct  ' . $this->id, 'forsage');
        }
        return true;
    }
}