<?php

namespace panix\mod\forsage;

use panix\engine\CMS;
use Yii;
use panix\engine\WebModule;
use yii\base\BootstrapInterface;
use yii\helpers\ArrayHelper;

class Module extends WebModule implements BootstrapInterface
{

    public $icon = '';
    public $unit = 1;
    public $onlySuppliers = [];
    public $forsageClass = '\panix\mod\forsage\components\ForsageStudio';
    public $excludeCategories = [];
    public $sizeGroup = [
        '44-99' => '44 и более',
        '40-45' => '40-45',
        '38-43' => '38-43',
        '36-41' => '36-41',
        '31-37' => '31-37',
        '26-32' => '26-32',
        '20-26' => '20-26',
        '0-20' => 'до 20',
    ];

    public function bootstrap($app)
    {
        $app->urlManager->addRules(
            [
                'forsage/webhook/<hook:\w+>' => 'forsage/default/webhook',
            ],
            true
        );
    }

    public function getInfo()
    {
        return [
            'label' => Yii::t('forsage/default', 'MODULE_NAME'),
            'author' => $this->author,
            'version' => '1.0',
            'icon' => $this->icon,
            'description' => Yii::t('forsage/default', 'MODULE_DESC'),
            'url' => ['/admin/forsage'],
        ];
    }


}
