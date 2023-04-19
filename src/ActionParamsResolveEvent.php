<?php

namespace vasadibt\actionresolver;

use yii\base\Event;


class ActionParamsResolveEvent extends Event
{
    /**
     * @var \yii\base\Action
     */
    public $action;
    /**
     * @var \yii\base\Request
     */
    public $request;
    /**
     * @var \ReflectionNamedType
     */
    public $type;
    /**
     * @var bool
     */
    public $isResolved = false;
    /**
     * @var object
     */
    public $resolved;
}