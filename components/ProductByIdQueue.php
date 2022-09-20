<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\helpers\Console;
use yii\queue\JobInterface;

class ProductByIdQueue extends BaseObject implements JobInterface
{
    public $product;

    public function execute($queue)
    {
        $fs = new ForsageStudio();
        $product = $fs->getProduct($this->product);
        if ($product) {
            if ($product->execute()) {
                Yii::info('addProduct ' . $this->product, 'forsage');
            } else {
                Yii::info('NotAddProduct  ' . $this->product, 'forsage');
            }
        }else{
            Yii::info('NoGetProduct  ' . $this->product, 'forsage');
        }
        return true;
    }
}