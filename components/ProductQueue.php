<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\helpers\Console;
use yii\queue\JobInterface;

class ProductQueue extends BaseObject implements JobInterface
{
    public $products;


    public function execute($queue)
    {
        $fs = new ForsageStudio();
        $count = count($this->products );
        $i = 0;
        Console::startProgress($i, $count, ' - ', 100);
        foreach ($this->products as $product_id) {
            $product = $fs->getProduct($product_id);
            $product->execute();
            Console::updateProgress($i, $count, ' - ');
            $i++;
        }
        Console::endProgress(false);
        return true;
    }
}