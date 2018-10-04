<?php
declare (strict_types=1);

namespace luckyshopteam\sentry;

use app\models\User;
use Throwable;
use yii\di\Instance;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target as BaseTarget;

class Target extends BaseTarget
{
    /**
     * @var string|Component
     */
    public $sentry = 'sentry';

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration.
     */
    public function init()
    {
        parent::init();

        $this->sentry = Instance::ensure($this->sentry, Component::class);

        if (!$this->sentry->enabled) {
            $this->enabled = false;
        }
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
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    public function export()
    {
        $limit = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');
        foreach ($this->messages as $message) {
            list($context, $level, $category, $timestamp, $traces) = $message;

            $data = [
                'level' => static::getLevelName($level),
                'timestamp' => $timestamp,
                'tags' => [
                    'category' => $category,
                ],
            ];
            if (\Yii::$app->user->id ?? null) {
                /** @var User $user */
                $user = \Yii::$app->user->identity;
                $data['user'] = [
                    'id' => $user->id,
                    'email' => $user->email,
                    'group_id' => $user->group_id,
                    'role' => $user->role,
                ];
            }
            $vars = null;

            if ($context instanceof Throwable) {
                if ($context instanceof ExceptionInterface) {
                    if (!$context->ready()) continue;
                    /** @noinspection SlowArrayOperationsInLoopInspection */
                    $data['tags'] += $context->getTags();
                    $vars = $context->getExtra();
                }
                $this->sentry->captureException($context, $data, null, $vars);
                continue;
            }
            if (isset($context['msg']) || isset($context['message'])) {
                if (isset($context['msg'])) {
                    $data['message'] = $context['msg'];
                    unset($context['msg']);
                } else {
                    $data['message'] = $context['message'];
                    unset($context['message']);
                }
                $extra = $context;
                if (isset($extra['traces'])) {
                    $traces = (array) $traces;
                    $traces = array_unshift($traces, $extra['traces']);
                    unset($extra['traces']);
                }
                if (isset($extra['vars'])) {
                    $vars = (array) $vars;
                    $vars += $extra['vars'];
                    unset($extra['vars']);
                }
                if (isset($extra['tags'])) {
                    $vars = (array) $vars;
                    $data['tags'] += $extra['tags'];
                    unset($extra['tags']);
                }
                $data['extra'] = $extra;
            } else {
                // If the message is not a string, log it with VarDumper::export,
                // like the other log targets in Yii.
                $data['message'] = is_string($context) ? $context : VarDumper::export($context);
                if (is_array($context)) {
                    // But if it's an array, also send it as an array,
                    // so that it can be displayed nicely in Sentry.
                    $data['extra'] = $context;
                }
            }

            $this->sentry->capture($data, $traces, $vars);
        }
        ini_set('memory_limit', $limit);
    }

    /**
     * Maps a Yii Logger level to a Sentry log level.
     *
     * @param integer $level The message level, e.g. [[\yii\log\Logger::LEVEL_ERROR]], [[\yii\log\Logger::LEVEL_WARNING]].
     * @return string Sentry log level.
     */
    public static function getLevelName($level)
    {
        static $levels = [
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'debug',
            Logger::LEVEL_PROFILE_BEGIN => 'debug',
            Logger::LEVEL_PROFILE_END => 'debug',
        ];

        return isset($levels[$level]) ? $levels[$level] : 'error';
    }
}
