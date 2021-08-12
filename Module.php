<?php

namespace panix\mod\forsage;

use Yii;
use panix\engine\WebModule;
use yii\base\BootstrapInterface;

class Module extends WebModule implements BootstrapInterface
{
    public $key;
    public $icon = '';

    public function bootstrap($app)
    {
        $app->urlManager->addRules(
            [
                ['pattern' => 'robots1', 'route' => 'seo/robots/index', 'suffix' => '.txt'],
            ],
            true
        );




    }

    public function getInfo()
    {
        return [
            'label' => Yii::t('seo/default', 'MODULE_NAME'),
            'author' => $this->author,
            'version' => '1.0',
            'icon' => $this->icon,
            'description' => Yii::t('seo/default', 'MODULE_DESC'),
            'url' => ['/admin/seo'],
        ];
    }



}
