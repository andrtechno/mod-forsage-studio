<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class ProductDeleteQueue extends BaseObject implements JobInterface
{
    public $forsage_id;

    public function execute($queue)
    {
        $product = Product::findOne(['forsage_id' => $this->forsage_id]);
        if ($product) {
            $product->delete();
            return true;
        }
        return false;
    }
}
