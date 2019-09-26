<?php

namespace luckyshopteam\sentry;

use Closure;
use function Sentry\captureEvent;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Yii;
use yii\base\Component as BaseComponent;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * Class SentryComponent
 *
 * @property string  $dsn
 * @property HubInterface  $client
 * @property boolean $enabled
 * @property boolean $environment
 *
 * @package luckyshopteam\sentry
 */
class SentryComponent extends BaseComponent
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
     * @var HubInterface|array Клиент для отправки сообщений, пока привязываемся к интерфейсу из sentry SDK
     * @throws InvalidConfigException
     */
    public $client = [];

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->validateDsn();

        if (!$this->enabled) {
            return;
        }

        $this->initClient();
        $this->setEnvironmentOptions();
    }

    /**
     * Валидация входных данных из конфига и инициализация клиента
     * @return void
     * @throws InvalidConfigException
     */
    public function initClient()
    {
        $data = ['dsn' => $this->dsn];

        if (is_array($this->client) && isset($this->client['class'])) {
            $this->client = Yii::createObject(array_merge($this->client, $data));
        } elseif (!$this->client) {
            \Sentry\init($data);

            $this->client = SentrySdk::getCurrentHub();
        }
        if (!is_object($this->client)) {
            throw new InvalidConfigException(get_class($this) . '::' . 'client must be an object');
        }
    }

    /**
     * @throws InvalidConfigException
     */
    private function validateDsn()
    {
        if (empty($this->dsn)) {
            throw new InvalidConfigException('Private DSN must be set!');
        }

        //TODO возможно валидация что бы url dsn указывал на наш ресурс
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

    public function captureException($exception, $data = [])
    {
        $this->captureEvent(['exception' => $exception], $data);
    }

    public function captureMessage($payLoad, $data = [])
    {
        $this->captureEvent($payLoad, $data);
    }

    /**
     * В сентри по сути все является ивентом, exception, message и тд. Разница лишь в параметрах, + ко всему этому нам необходим общий метод через который
     * происходит отправка сообщений, т.к сбор данных (extra, tags и данные пользователя) при ивенте - одинаков во всех случаях.
     *
     * @param array $payLoad Основная информация с настройками для ивента
     * @param array $data Дополнительные данные которые могут пригодиться (tags, extra, данные пользователи и тд)
     *
     * TODO обсудить создание дополнительного параметра userData для компонента, что бы при получении сообщения проверять если нет данных пользователя, то брать дефолтные.
     *
     * @return void
     */
    public function captureEvent($payLoad, $data = [])
    {
        if (!empty($data['extra'])) {
            $this->setExtra($data['extra']);
        }
        if (!empty($data['tags'])) {
            $this->setTags($data['tags']);
        }
        if (!empty($data['user'])) {
            $this->setUser($data['user']);
        }

        $this->client->captureEvent($payLoad);

        $this->client->configureScope(function (Scope $scope) : void {
            $scope->clear();
        });
    }

    /**
     * Добавление extra параметров
     * @param array $extra
     * @return void
     */
    protected function setExtra(array$extra): void
    {
        foreach ($extra as $key => $value) {
            $this->client->configureScope(function (Scope $scope) use($key, $value) : void {
                $scope->setExtra($key, $value);
            });
        }
    }

    /**
     * Добавление tags параметров
     * @param array $tags
     * @return void
     */
    protected function setTags(array $tags): void
    {
        foreach ($tags as $key => $value) {
            $this->client->configureScope(function (Scope $scope) use($key, $value) : void {
                $scope->setTag($key, $value);
            });
        }
    }

    /**
     * Добавление данных пользователя.
     * @param array $userData
     * @return void
     */
    protected function setUser(array $userData): void
    {
        $this->client->configureScope(function (Scope $scope) use($userData) : void {
            $scope->setUser($userData);
        });
    }
}
