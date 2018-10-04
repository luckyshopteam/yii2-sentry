<?php
declare (strict_types=1);

namespace luckyshopteam\sentry;

use RuntimeException;
use Throwable;
use Yii;

class SilentException extends RuntimeException implements ExceptionInterface
{
    use ExceptionTrait;

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function save(string $category, bool $error = true): void
    {
        if ($error) {
            Yii::error($this, $category);
        } else {
            Yii::warning($this, $category);
        }
    }
}