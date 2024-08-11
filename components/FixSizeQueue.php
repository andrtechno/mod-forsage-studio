<?php

namespace panix\mod\forsage\components;

use Yii;
use panix\mod\shop\models\Product;
use yii\base\BaseObject;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\queue\JobInterface;

/**
 * Class FixSizeQueue
 * @package panix\mod\forsage\components
 */
class FixSizeQueue extends BaseObject implements JobInterface
{
    public $id;
    public $images = true; //reload images
    public $attributes = true; //reload attributes

    public function execute($queue)
    {
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $fs = new $forsageClass;
        $product = $fs->getProduct($this->id);
        $_SERVER['FORSAGE_ID'] = $this->id;
        if ($product) {
            $product->execute_fixsize();
        }
        return ExitCode::OK;
    }
}
