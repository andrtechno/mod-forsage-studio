<?php

namespace panix\mod\forsage\components;

/**
 * Find objects by external id
 */

use panix\mod\images\models\Image;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\Brand;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Product;
use yii\db\Query;

class ForsageExternalFinder
{

    const OBJECT_TYPE_MAIN_CATEGORY = 10;
    const OBJECT_TYPE_CATEGORY = 1;
    const OBJECT_TYPE_ATTRIBUTE = 2;
    const OBJECT_TYPE_PRODUCT = 3;
    const OBJECT_TYPE_BRAND = 4;
    const OBJECT_TYPE_ATTRIBUTE_OPTION = 5;
    const OBJECT_TYPE_IMAGE = 6;
    const OBJECT_TYPE_SUPPLIER = 7;

    /**
     * @static
     * @param $type
     * @param $externalId
     * @param bool $loadModel
     * @param bool $object_id
     * @return array|mixed $query
     */
    public static function getObject($type, $externalId, $loadModel = true, $object_id = false)
    {

        if ($object_id) {
            $query = \Yii::$app->db->createCommand()
                //->select("*")
                ->from('{{%exchange_forsage}}')
                ->where('object_type=:type AND external_id=:externalId AND object_id=:object_id', array(
                    ':type' => $type,
                    ':externalId' => $externalId,
                    ':object_id' => $object_id
                ))
                ->limit(1)
                ->queryOne();
        } else {

            $query = (new Query())
                ->select('*')
                ->from('{{%exchange_forsage}}')
                ->where('object_type=:type AND external_id=:externalId', [
                    ':type' => $type,
                    ':externalId' => $externalId
                ])
                ->limit(1)
                ->createCommand()
                ->queryOne();

        }

        if ($query === false)
            return false;

        if ($loadModel === true && $query['object_id']) {
            switch ($type) {
                case self::OBJECT_TYPE_CATEGORY:
                    return Category::findOne($query['object_id']);
                    break;

                case self::OBJECT_TYPE_MAIN_CATEGORY:
                    return Category::findOne($query['object_id']);
                    break;

                case self::OBJECT_TYPE_ATTRIBUTE:
                    return Attribute::findOne($query['object_id']);
                    break;

                case self::OBJECT_TYPE_PRODUCT:
                   // $model = Product::find()
                       // ->where(['id'=>$query['object_id']])
                        //->detachBehavior('discounts')
                       // ->one();
                    return Product::findOne($query['object_id']);
                    break;

                case self::OBJECT_TYPE_BRAND:
                    return Brand::findOne($query['object_id']);
                    break;

                case self::OBJECT_TYPE_ATTRIBUTE_OPTION:
                    return AttributeOption::findOne($query['object_id']);
                    break;

                case self::OBJECT_TYPE_SUPPLIER:
                    //return Suppliers::model()->findByPk($query['object_id']);
                    break;

                case self::OBJECT_TYPE_IMAGE:
                    return Image::findOne($query['object_id']);
                    break;
            }
        }
        return $query['object_id'];
    }

    public static function removeObject($type, $external_id, $loadModel = true)
    {

        Yii::app()->db->createCommand()->delete(
            '{{exchange_forsage}}',
            'object_type=:type AND external_id=:external_id',
            array(
                ':type' => $type,
                ':external_id' => $external_id

            )
        );

        $img = self::getObject($type, $external_id, $loadModel);
        if ($img)
            $img->delete();
    }

    public static function removeObjectByPk($type, $obj_id)
    {
        $query = Yii::app()->db->createCommand()->delete(
            '{{exchange_forsage}}',
            'object_type=:type AND object_id=:object_id',
            array(
                ':type' => $type,
                ':object_id' => $obj_id
            )
        );

    }

    public static function removeObjectByPk__Old($type, $obj_id)
    {
        $query = Yii::app()->db->createCommand()
            ->from('{{exchange_forsage}}')
            ->where('object_type=:type AND object_id=:obj_id', array(
                ':type' => $type,
                ':obj_id' => $obj_id
            ))
            ->delete();
    }

}
