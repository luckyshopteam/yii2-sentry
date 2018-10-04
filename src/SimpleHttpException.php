<?php
declare (strict_types=1);

namespace luckyshopteam\sentry;

use yii\web\HttpException;

class SimpleHttpException extends HttpException implements ExceptionInterface
{
    use ExceptionTrait;
}