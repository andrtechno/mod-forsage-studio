# Module forsage

Module for PIXELION CMS

[Forsage API documentation](https://forsage.docs.apiary.io/)

[![Latest Stable Version](https://poser.pugx.org/panix/mod-forsage-studio/v/stable)](https://packagist.org/packages/panix/mod-forsage-studio)
[![Latest Unstable Version](https://poser.pugx.org/panix/mod-forsage-studio/v/unstable)](https://packagist.org/packages/panix/mod-forsage-studio)
[![Total Downloads](https://poser.pugx.org/panix/mod-forsage-studio/downloads)](https://packagist.org/packages/panix/mod-forsage-studio)
[![Monthly Downloads](https://poser.pugx.org/panix/mod-forsage-studio/d/monthly)](https://packagist.org/packages/panix/mod-forsage-studio)
[![Daily Downloads](https://poser.pugx.org/panix/mod-forsage-studio/d/daily)](https://packagist.org/packages/panix/mod-forsage-studio)
[![License](https://poser.pugx.org/panix/mod-forsage-studio/license)](https://packagist.org/packages/panix/mod-forsage-studio)


## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

#### Either run

```
php composer require --prefer-dist panix/mod-forsage-studio "*"
```

or add

```
"panix/mod-forsage-studio": "*"
```

to the require section of your `composer.json` file.

#### Add to web config.
```
'modules' => [
    'forsage' => [
        'class' => 'panix\mod\forsage\Module',
        'apiKey' => 'YOUR_API_KEY'
    ],
],
```

#### Mobule props
| Props           | Default     | Description                                       |
|-----------------|:-----------:|---------------------------------------------------|
| apiKey          |    ""       | Apikey                                            |
| unit            |    1        | Еденица измерение штука/ящик и.тд                 |
| type_id         |    1        | ID Типа товара                                    |
| outStockDelete  |    true     | Удалять товар с Базы-данных если **нет в наличии**    |


#### Migrate
```
php cmd migrate --migrationPath=vendor/panix/mod-forsage-studio/migrations
```


> [![PIXELION CMS!](https://pixelion.com.ua/uploads/logo.svg "PIXELION CMS")](https://pixelion.com.ua)  
<i>Content Management System "PIXELION CMS"</i>  
[www.pixelion.com.ua](https://pixelion.com.ua)

> The module is under development, any moment can change everything.



