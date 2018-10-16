Yii2 Sentry
-------------
Установка
---------
```
composer require luckyshopteam/yii2-sentry @dev
```

Подключение 
---------

Подключите класс SentryComponent в компонентах приложения
```
'components' => [
    'sentry' => [
        'class' => luckyshopteam\sentry\SentryComponent::class,
        'enabled' => true,
        'dsn' => getenv('SENTRY_DSN'),
        'environment' => YII_ENV, // if not set, the default is `production`
    ],
],
```
Добавьте класс SentryTarget в параметр 'targets' для компонента 'log'
```
'components' => [
    'log' => [
        'traceLevel' => YII_DEBUG ? 3 : 0,
        'flushInterval' => 1,
        'targets' => [
            [
                'class' => luckyshopteam\sentry\SentryTarget::class,
                'exportInterval' => 1,
                'levels' => ['error', 'warning'],
                'except' => [
                    'yii\web\HttpException:429', // TooManyRequestsHttpException
                    'yii\web\HttpException:401', // UnauthorizedHttpException
                ],
                'userData' => ['id', 'email', 'role'],
            ],
        ]
    ],
],
```
