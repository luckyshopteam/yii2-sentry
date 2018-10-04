<?php
declare (strict_types=1);

namespace luckyshopteam\sentry;

trait ExceptionTrait
{
    protected $ready = true;

    protected $tags = [];

    protected $extra = [];

    public function ready(): bool
    {
        return $this->ready;
    }

    public function setReady(bool $ready)
    {
        $this->ready = $ready;

        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }

    public function addTag(string $tag, $value)
    {
        $this->tags[$tag] = (string) $value;

        return $this;
    }

    public function addExtra(string $name, $value)
    {
        $this->extra[$name] = $value;

        return $this;
    }
}