<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\helpers\Console;
use yii\queue\JobInterface;

class ProductQueue extends BaseObject implements JobInterface
{
    public $product;

    public function execute($queue)
    {
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $fs = new $forsageClass;
        $fs->product = $this->product;
        if ($this->product) {
            if ($fs->execute()) {
                Yii::info('addProduct', 'forsage');
            } else {
                Yii::info('NotAddProduct', 'forsage');
            }
        }else{
            Yii::info('NoGetProduct', 'forsage');
        }
        return true;
    }
}