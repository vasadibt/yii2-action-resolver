<?php

namespace vasadibt\actionresolver;

use ReflectionMethod;
use ReflectionNamedType;
use Yii;
use yii\base\Action;
use yii\base\Exception;
use yii\base\InlineAction;
use yii\console\Controller as ConsoleController;
use yii\web\BadRequestHttpException;
use yii\web\Controller as WebController;
use yii\web\HttpException;
use yii\web\ServerErrorHttpException;

trait ActionResolveTrait
{
    public function bindActionParams($action, $params)
    {
        if ($this instanceof WebController) {
            return $this->bindWebActionParams($action, $params);
        }

        if ($this instanceof ConsoleController) {
            return $this->bindConsoleActionParams($action, $params);
        }

        throw new Exception(sprintf('Not supported class "%s".', get_class($this)));
    }

    /**
     * Binds the parameters to the action.
     * This method is invoked by [[\yii\base\Action]] when it begins to run with the given parameters.
     * This method will check the parameter names that the action requires and return
     * the provided parameters according to the requirement. If there is any missing parameter,
     * an exception will be thrown.
     * @param \yii\base\Action $action the action to be bound with parameters
     * @param array $params the parameters to be bound to the action
     * @return array the valid parameters that the action can run with.
     * @throws BadRequestHttpException if there are missing or invalid parameters.
     */
    public function bindWebActionParams($action, $params)
    {
        if ($action instanceof InlineAction) {
            $method = new ReflectionMethod($this, $action->actionMethod);
        } else {
            $method = new ReflectionMethod($action, 'run');
        }

        $args = [];
        $missing = [];
        $actionParams = [];
        $requestedParams = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $params)) {
                $isValid = true;
                if (PHP_VERSION_ID >= 80000) {
                    $isArray = ($type = $param->getType()) instanceof ReflectionNamedType && $type->getName() === 'array';
                } else {
                    $isArray = $param->isArray();
                }
                if ($isArray) {
                    $params[$name] = (array)$params[$name];
                } elseif (is_array($params[$name])) {
                    $isValid = false;
                } elseif (
                    PHP_VERSION_ID >= 70000
                    && ($type = $param->getType()) !== null
                    && $type->isBuiltin()
                    && ($params[$name] !== null || !$type->allowsNull())
                ) {
                    $typeName = PHP_VERSION_ID >= 70100 ? $type->getName() : (string)$type;

                    if ($params[$name] === '' && $type->allowsNull()) {
                        if ($typeName !== 'string') { // for old string behavior compatibility
                            $params[$name] = null;
                        }
                    } else {
                        switch ($typeName) {
                            case 'int':
                                $params[$name] = filter_var($params[$name], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                                break;
                            case 'float':
                                $params[$name] = filter_var($params[$name], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                                break;
                            case 'bool':
                                $params[$name] = filter_var($params[$name], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                                break;
                        }
                        if ($params[$name] === null) {
                            $isValid = false;
                        }
                    }
                }
                if (!$isValid) {
                    throw new BadRequestHttpException(
                        Yii::t('yii', 'Invalid data received for parameter "{param}".', ['param' => $name])
                    );
                }
                $args[] = $actionParams[$name] = $params[$name];
                unset($params[$name]);
            } elseif (
                PHP_VERSION_ID >= 70100
                && ($type = $param->getType()) !== null
                && $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
            ) {
                try {
                    $this->bindInjectedParamsWithResolvers($action, $type, $name, $args, $requestedParams);
                } catch (HttpException $e) {
                    throw $e;
                } catch (Exception $e) {
                    throw new ServerErrorHttpException($e->getMessage(), 0, $e);
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $actionParams[$name] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            throw new BadRequestHttpException(
                Yii::t('yii', 'Missing required parameters: {params}', ['params' => implode(', ', $missing)])
            );
        }

        $this->actionParams = $actionParams;

        // We use a different array here, specifically one that doesn't contain service instances but descriptions instead.
        if (Yii::$app->requestedParams === null) {
            Yii::$app->requestedParams = array_merge($actionParams, $requestedParams);
        }

        return $args;
    }

    /**
     * Binds the parameters to the action.
     * This method is invoked by [[Action]] when it begins to run with the given parameters.
     * This method will first bind the parameters with the [[options()|options]]
     * available to the action. It then validates the given arguments.
     *
     * @param Action $action the action to be bound with parameters
     * @param array $params the parameters to be bound to the action
     * @return array the valid parameters that the action can run with.
     *
     * @throws \yii\console\Exception if there are unknown options or missing arguments
     */
    public function bindConsoleActionParams($action, $params)
    {
        if ($action instanceof InlineAction) {
            $method = new ReflectionMethod($this, $action->actionMethod);
        } else {
            $method = new ReflectionMethod($action, 'run');
        }

        $args = [];
        $missing = [];
        $actionParams = [];
        $requestedParams = [];
        foreach ($method->getParameters() as $i => $param) {
            $name = $param->getName();
            $key = null;
            if (array_key_exists($i, $params)) {
                $key = $i;
            } elseif (array_key_exists($name, $params)) {
                $key = $name;
            }

            if ($key !== null) {
                if (PHP_VERSION_ID >= 80000) {
                    $isArray = ($type = $param->getType()) instanceof ReflectionNamedType && $type->getName() === 'array';
                } else {
                    $isArray = $param->isArray();
                }
                if ($isArray) {
                    $params[$key] = $params[$key] === '' ? [] : preg_split('/\s*,\s*/', $params[$key]);
                }
                $args[] = $actionParams[$key] = $params[$key];
                unset($params[$key]);
            } elseif (
                PHP_VERSION_ID >= 70100
                && ($type = $param->getType()) !== null
                && $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
            ) {
                try {
                    $this->bindInjectedParamsWithResolvers($action, $type, $name, $args, $requestedParams);
                } catch (Exception $e) {
                    throw new Exception($e->getMessage());
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $actionParams[$i] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            throw new Exception(Yii::t('yii', 'Missing required arguments: {params}', ['params' => implode(', ', $missing)]));
        }

        // We use a different array here, specifically one that doesn't contain service instances but descriptions instead.
        if (\Yii::$app->requestedParams === null) {
            \Yii::$app->requestedParams = array_merge($actionParams, $requestedParams);
        }

        return array_merge($args, $params);
    }

    /**
     * @param \ReflectionNamedType $type
     * @param string $name
     * @param array $args
     * @param array $requestedParams
     *
     * @return void
     */
    public function bindInjectedParamsWithResolvers(Action $action, ReflectionNamedType $type, string $name, array &$args, array &$requestedParams): void
    {
        $event = new ActionParamsResolveEvent([
            'action' => $action,
            'request' => Yii::$app->request,
            'type' => $type,
        ]);

        $this->trigger(ResolvableActionBehavior::EVENT_BEFORE_BIND_PARAM_INJECTION, $event);

        if ($event->isResolved) {
            $args[] = $event->resolved;
            $requestedParams[$name] = "Resolved argument: " . get_class($event->resolved) . " \$$name";
            return;
        }

        $this->bindInjectedParams($type, $name, $args, $requestedParams);
    }
}