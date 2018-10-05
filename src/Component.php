<?php

namespace luckyshopteam\sentry;

use yii\base\Component as BaseComponent;
use yii\base\InvalidConfigException;

class Component extends BaseComponent
{
    /**
     * Set to `false` in development environment to skip collecting errors
     *
     * @var bool
     */
    public $enabled = true;

    /**
     * @var string Sentry DSN
     * @note this is ignored if [[client]] is a Raven client instance.
     */
    public $dsn;

    /**
     * @var string environment name
     * @note this is ignored if [[client]] is a Raven client instance.
     */
    public $environment = 'production';

    /**
     * @var \Raven_Client|array Raven client or configuration array used to instantiate one
     * @throws InvalidConfigException
     */
    public $client = [];

    public function init()
    {
        $this->validateDsn();

        if (!$this->enabled) {
            return;
        }

        $this->setRavenClient();
        $this->setEnvironmentOptions();
    }

    private function validateDsn()
    {
        if (empty($this->dsn)) {
            throw new InvalidConfigException('Private DSN must be set!');
        }

        // throws \InvalidArgumentException if dsn is invalid
        \Raven_Client::parseDSN($this->dsn);
    }

    /**
     * Adds a tag to filter events by environment
     */
    private function setEnvironmentOptions()
    {
        if (empty($this->environment)) {
            return;
        }

        if (is_object($this->client) && property_exists($this->client, 'environment')) {
            $this->client->environment = $this->environment;
        }
    }

    private function setRavenClient()
    {
        if (is_array($this->client)) {
            $ravenClass = ArrayHelper::remove($this->client, 'class', '\Raven_Client');
            $options = $this->client;
            $this->client = new $ravenClass($this->dsn, $options);
        } elseif (!is_object($this->client) || $this->client instanceof Closure) {
            $this->client = Yii::createObject($this->client);
        }

        if (!is_object($this->client)) {
            throw new InvalidConfigException(get_class($this) . '::' . 'client must be an object');
        }
    }

    public function captureException($exception, $culpritOrOptions = null, $logger = null, $vars = null)
    {
        return $this->client->captureException($exception, $culpritOrOptions, $logger, $vars);
    }

    public function capture($data, $stack = null, $vars = null)
    {
        return $this->client->capture($data, $stack, $vars);
    }

}
