<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\helpers\Console;
use yii\queue\JobInterface;

class ProductQueue2 extends BaseObject implements JobInterface
{
    public $product;


    public function execute($queue)
    {
        $fs = new ForsageStudio();
       //$product = $fs->getProduct($this->product);
        $fs->product = $this->product;
        //print_r($this->product);
        $res = $fs->execute();
//print_r($fs);
        return true;
    }
}