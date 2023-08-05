<?php

namespace panix\mod\forsage\models;

use Yii;
use panix\engine\CMS;
use panix\engine\SettingsModel;

class SettingsForm extends SettingsModel
{

    public static $category = 'forsage';
    protected $module = 'forsage';

    public $out_stock_delete;
    public $apikey;
    public $hook_key;
    public $clothes_type;
    public $boots_type;
    public $bags_type;
    public $accessories_type;
    public $categories_clothes;
    public $categories_bags;
    public $categories_shoes;
    //public $accessories_type;
    public $brand;
    public $structure_shoes;
    public $structure_clothes;
    public $structure_bags;
    public $structure_accessories;
    public $product_name_tpl;
    public $tm;

    public function rules()
    {
        return [
            [['hook_key', 'apikey', 'brand', 'product_name_tpl'], "required"],
            //[['product_related_bilateral', 'group_attribute', 'smart_bc', 'smart_title'], 'boolean'],
            [['boots_type', 'clothes_type', 'accessories_type', 'structure_shoes', 'structure_clothes', 'structure_bags', 'structure_accessories'], 'integer'],
            //[['boots_type', 'clothes_type', 'bags_type', 'accessories_type', 'categories_shoes'], 'default'],
            [['apikey', 'hook_key', 'categories_clothes', 'categories_bags', 'categories_shoes', 'product_name_tpl'], 'string'],
            [['out_stock_delete', 'brand', 'tm'], 'boolean'],
            ['apikey', 'match', 'pattern' => "/^[a-zA-Z0-9\._\-]+$/u"],
            ['hook_key', 'match', 'pattern' => "/^[a-zA-Z0-9]+$/u", 'message' => 'Только буквы и цифры'],
            // [['categories_shoes'], 'safe'],
            [['bags_type'], 'default', 'value' => ''], //,'categories_shoes'
        ];
    }


    /**
     * @inheritdoc
     */
    public static function defaultSettings()
    {
        return [
            'structure_shoes' => 0,
            'structure_clothes' => 0,
            'structure_bags' => 0,
            'out_stock_delete' => true,
            'apikey' => '',
            'hook_key' => CMS::gen(50),
            'clothes_type' => '',
            'bags_type' => '',
            'boots_type' => '',
            'accessories_type' => '',
            'brand' => true,
            'tm' => true,
            'product_name_tpl' => '{category} {supplier} {sku}',
            'categories_shoes' => '',
            'categories_bags' => '18,40,10,41,175,42,49,35,106,63,50',
            'categories_clothes' => '100,94,93,104,74,89,66,92,90,69,85,98,83,88,87,91,76,101,47,72,96,84,97,99,95,103,86,107,82,102,109,110,124,178,136,139,155,152,160,119,116,123,117,113,156,149,174,150,111,148,126,153,114,112,108,70,122,138',
        ];
    }

    private $_categories = [];

    public function __construct($config = [])
    {
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $fs = new $forsageClass;
        $this->_categories = $fs->getCategories(1);
        parent::__construct($config);
    }

    public function getCategories($parentId = 0)
    {
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $fs = new $forsageClass;

        //$categories = Yii::$app->cache->get("forsage-categories".Yii::$app->language);

        //if ($categories === false) {
        // $data is not found in cache, calculate it from scratch
        $categories = $fs->getCategories(1);
        $result = [];
        if ($categories) {
            foreach ($categories as $category) {
                $name = $category['name'];
                if (isset($category['descriptions'])) {
                    if (Yii::$app->language == 'ru') {
                        if (isset($category['descriptions'][0])) {
                            $name = $category['descriptions'][0]['name'];
                        }
                    } else {
                        if (isset($category['descriptions'][1])) {
                            $name = $category['descriptions'][1]['name'];
                        }
                    }
                }
                $result[] = [
                    'id' => $category['id'],
                    'text' => $name,
                    'parent_id' => $category['parent_id'],
                    'state' => ['opened' => true],
                ];
            }
        }
        // store $data in cache so that it can be retrieved next time
        //Yii::$app->cache->set("forsage-categories".Yii::$app->language, $result, 3600);

        // }


        return $this->buildTree($result, $parentId);

    }

    public function getParent($elements, $parentId = 0)
    {
        if (isset($elements[$parentId])) {
            return $elements[$parentId];
        }
        return false;
    }

    public function getParentRoot($elements, $parentId = 0)
    {
        if (isset($elements[$parentId])) {
            return $elements[$parentId];
        }
        return false;
    }

    public function newcat(array $elements)
    {
        $branch = [];
        foreach ($elements as $element) {
            $branch[$element['id']] = $element;
        }
        return $branch;
    }

    public function buildTree(array &$elements, $parentId = 0)
    {
        $branch = [];

        foreach ($elements as $element) {

            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                // $branch[$element['id']] = $element;
                $branch[] = $element;
                //unset($elements[$element['id']]);
            }
        }
        return $branch;
    }


    public function __buildTree(array &$elements, $parentId = 0)
    {
        $branch = [];

        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[$element['id']] = $element;
                unset($elements[$element['id']]);
            }
        }
        return $branch;
    }


    public function makeDropDown($parents)

    {
        $data = array();
        $data['0'] = '-- ROOT --';
        foreach ($parents as $parent) {
            $data[$parent->id] = $parent->name;
            $this->subDropDown($parent->children);
        }
        return $data;

    }


    public function subDropDown($children, $space = '---')
    {

        global $data;
        foreach ($children as $child) {
            $data[$child->id] = $space . $child->name;
            $this->subDropDown($child->children, $space . '---');

        }
    }


}
