<?php

namespace panix\mod\forsage\models;

use panix\mod\shop\models\Product as BaseProduct;

class Product extends BaseProduct
{
    public function afterFind()
    {

     //   $box = $this->eav_kolicestvo_v_asike->value;
        /*if ($this->discount) {
            $sum = $this->discount;
            if ('%' === substr($sum, -1, 1)) {
                $sum = $this->price * ((double)$sum) / 100;
            }
            $this->discountSum = $this->discount;
            $this->discountPrice = $this->price - $sum;
            $this->originalPrice = $this->price;
            $this->hasDiscount = $this->discount;
        }*/

        //$this->trigger(self::EVENT_AFTER_FIND);
       // $this->price =100;
    }


}
