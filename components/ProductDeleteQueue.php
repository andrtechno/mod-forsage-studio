<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class ProductDeleteQueue extends BaseObject implements JobInterface
{
    public $id;

    public function execute($queue)
    {
        $product = Product::findOne($this->id);
        if ($product) {
            $product->delete();
        }
        return true;
    }
}
