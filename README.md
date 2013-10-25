Installation
============

1. Grab package via composer:
 
        $ composer require aviasales/hotellook-yii-api

2. Add namespace alias to your application's config:

        ...
        'aliases' => array(
            'hotellook.composer.api' => 'application.vendor.aviasales.hotellook-yii-api',
        ),
        ...

3. Add application component

        ...
        'components' => array(
            ...
            'hotellookApi' => array(
                'class' => 'hotellook\composer\api\Agent',
                'host' => 'http://hotellook.com/api',
                'login' => YOUR_LOGIN,
                'token' => YOUR_TOKEN,
            ),
            ...
        ),
        ...
