<?php
declare (strict_types=1);

namespace luckyshopteam\sentry;

use Sentry\Severity;
use Yii;
use app\models\User;
use Throwable;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;

/**
 * Class SentryTarget
 *
 * TODO обсудить возможность добавления дополнительного параметра DSN, т.к несмотря на то, что мы можем настраивать используемый компонент сентри,
 * возможны кейсы когда ошибки определенного типа нам будет необходимо складывать в отдельный сентри проект, но с использованием единого компонента, так что бы не приходилось увеличивать в размерах основной конфиг приложения
 *
 * @package luckyshopteam\sentry
 */
class SentryTarget extends Target
{
    /**
     * @var string|SentryComponent
     */
    public $sentry = 'sentry';

    /**
     * TODO обсудить возможность передачи в параметры анонимной функции.
     *
     * @var array User data for sending to the Sentry API server.
     */
    public $userData;

    /**
     * Maps a Yii Logger level to a Sentry log level.
     *
     * @param integer $level The message level, e.g. [[\yii\log\Logger::LEVEL_ERROR]], [[\yii\log\Logger::LEVEL_WARNING]].
     * @return string Sentry log level.
     */
    public static function getLevelName($level)
    {
        static $levels = [
            Logger::LEVEL_ERROR         => 'error',
            Logger::LEVEL_WARNING       => 'warning',
            Logger::LEVEL_INFO          => 'info',
            Logger::LEVEL_TRACE         => 'debug',
            Logger::LEVEL_PROFILE_BEGIN => 'debug',
            Logger::LEVEL_PROFILE_END   => 'debug',
        ];

        return isset($levels[$level]) ? $levels[$level] : 'error';
    }

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration.
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->sentry = Instance::ensure($this->sentry, SentryComponent::class);

        if (!$this->sentry->enabled) {
            $this->enabled = false;
        }
    }

    /**
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    public function export()
    {
        $limit = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');

        foreach ($this->messages as $message) {
            if (current($message) instanceof Throwable) {
                $this->captureException($message);
            } else {
                $this->captureMessage($message);
            }
        }

        ini_set('memory_limit', $limit);
    }

    /**
     * Generates the context information to be logged.
     * The default implementation will dump user information, system variables, etc.
     * @return string the context information. If an empty string, it means no context information.
     */
    protected function getContextMessage()
    {
        return '';
    }

    /**
     * Отправка сообщения об ошибке
     * @param array $message
     * @return void
     */
    protected function captureException($message)
    {
        list($context, $level, $category, $timestamp, $traces) = $message; // Принебрегая принцыпам YAGNI, оставим пока как было

        $data = [
            'user'      => $this->getUserData(),
            'level'     => $level,     // возможно надо будет удалить
            'timestamp' => $timestamp, // возможно надо будет удалить
            'tags' => [
                'category' => $category,
            ],
        ];

        if ($context instanceof ExceptionInterface) {
            if (!$context->ready()) {
                return;
            }

            $data = ArrayHelper::merge($data, [
                'tags'  => $context->getTags(),
                'extra' => $context->getExtra(),
            ]);
        }

        $this->sentry->captureException($context, $data);
    }

    /**
     * Отправка информационных сообщений. Не Exception
     * @param array $message
     * @return void
     */
    protected function captureMessage($message)
    {
        list($context, $level, $category, $timestamp, $traces) = $message; // Принебрегая принцыпам YAGNI, оставим пока как было

        $data = [
            'user'      => $this->getUserData(),
            'tags'      => ['category' => $category],
            'timestamp' => $timestamp,// возможно надо будет удалить
        ];

        $payLoad = [
            'level' => new Severity(self::getLevelName($level)),
        ];

        if (is_string($context)) {
            $payLoad['message'] = $context;
        } elseif (is_array($context)) {
            $payLoad['message'] = ArrayHelper::remove($context, 'msg') ?? ArrayHelper::remove($context, 'message', 'no message');

            if (isset($context['traces'])) {
                $traces[] = ArrayHelper::remove($context, 'traces');
            }

            $vars = ArrayHelper::remove($context, 'vars', []);
            $tags = ArrayHelper::remove($context, 'tags', []);
            $extra = ArrayHelper::remove($context, 'extra', []);

            $data = ArrayHelper::merge($data, [
                'traces' => $traces, // возможно надо будет удалить
                'tags'   => array_merge($tags, $vars),
                'extra'  => array_merge($extra, $context),
            ]);

        } else {
            $payLoad['message'] = VarDumper::export($context);
        }

        $this->sentry->captureMessage($payLoad, $data);
    }

    /**
     * @return array
     */
    protected function getUserData()
    {
        $userData = [];

        if ($this->userData && Yii::$app->has('user') && $user = Yii::$app->user->identity) {
            /** @var User $user */
            foreach ($this->userData as $attribute) {
                if ($user->canGetProperty($attribute)) {
                    $userData[$attribute] = $user->$attribute;
                }
            }
        }

        return $userData;
    }
}
