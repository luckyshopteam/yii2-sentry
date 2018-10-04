<?php
declare (strict_types=1);

namespace luckyshopteam\sentry;

use LogicException as BaseLogicException;

class LogicException extends BaseLogicException implements ExceptionInterface
{
    use ExceptionTrait;
}