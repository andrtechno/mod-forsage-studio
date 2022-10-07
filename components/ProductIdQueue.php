<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\helpers\Console;
use yii\queue\JobInterface;

class ProductIdQueue extends BaseObject implements JobInterface
{
    public $product_ids;

    public function execute($queue)
    {
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $fs = new $forsageClass;
        $count = count($this->product_ids);
        $i = 0;
        Console::startProgress($i, $count, ' - ', 100);
        foreach ($this->product_ids as $product_id) {
            $product = $fs->getProduct($product_id);
            if($product){
                $exec = $product->execute();
            }

            Console::updateProgress($i, $count, ' - ');
            $i++;
        }
        Console::endProgress(false);
        return true;
    }
}
