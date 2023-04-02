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
    public $categories_clothes;
    public $categories_bags;
    public $accessories_type;
    public $brand;
    public $structure_shoes;
    public $structure_clothes;

    public function rules()
    {
        return [
            [['hook_key', 'apikey', 'brand'], "required"],
            //[['product_related_bilateral', 'group_attribute', 'smart_bc', 'smart_title'], 'boolean'],
            [['boots_type', 'clothes_type', 'bags_type', 'accessories_type', 'structure_shoes', 'structure_clothes'], 'integer'],
            [['boots_type', 'clothes_type', 'bags_type', 'accessories_type'], 'default'],
            [['apikey', 'hook_key', 'categories_clothes', 'categories_bags'], 'string'],
            [['out_stock_delete', 'brand'], 'boolean'],
            ['apikey', 'match', 'pattern' => "/^[a-zA-Z0-9\._\-]+$/u"],
            ['hook_key', 'match', 'pattern' => "/^[a-zA-Z0-9]+$/u", 'message' => 'Только буквы и цифры'],
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
            'out_stock_delete' => true,
            'apikey' => '',
            'hook_key' => CMS::gen(50),
            'clothes_type' => '',
            'bags_type' => '',
            'boots_type' => '',
            'brand' => true,
            'categories_bags' => '18,40,10,41,175,42,49,35,106,63,50',
            'categories_clothes' => '100,94,93,104,74,89,66,92,90,69,85,98,83,88,87,91,76,101,47,72,96,84,97,99,95,103,86,107,82,102,109,110,124,178,136,139,155,152,160,119,116,123,117,113,156,149,174,150,111,148,126,153,114,112,108,70',
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

    public function getCategories($with_descriptions = 0, $parentId = 0)
    {
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $fs = new $forsageClass;
        $result = [];

        $categories = $fs->getCategories($with_descriptions);
        if ($categories) {
            foreach ($categories as $category) {
                $result[] = [
                    'id' => $category['id'],
                    'text' => $category['name'],
                    'parent_id' => $category['parent_id'],
                    'state' => ['opened' => true],
                ];
            }
        }

        //return $test;
        return $this->buildTree($result, $parentId);
        //return $result2;
        //return $this->findRoot($categories);
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
