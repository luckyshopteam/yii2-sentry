<?php
declare (strict_types=1);

namespace luckyshopteam\sentry;

interface ExceptionInterface
{
    public function ready(): bool;

    public function getTags(): array;

    public function getExtra(): array;

    public function setReady(bool $ready);

    public function addTag(string $tag, $value);

    public function addExtra(string $name, $value);
}