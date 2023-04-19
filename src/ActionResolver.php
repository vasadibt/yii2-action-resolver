<?php

namespace vasadibt\actionresolver;

use yii\base\Component;
use yii\base\Exception;

class ActionResolver extends Component
{
    public $resolvable;
    public $actions = ['*'];
    public $callable;

    /**
     * @return void
     */
    public function init()
    {
        parent::init();

        if ($this->callable === null
            && !(new $this->resolvable instanceof ResolvableInterface)
        ) {
            throw new Exception(sprintf('The "%s" class need to implement "%s" interface.', $this->resolvable, ResolvableInterface::class));
        }
    }


    /**
     * @param ActionParamsResolveEvent $event
     * @return bool
     */
    public function isApplicable(ActionParamsResolveEvent $event)
    {
        if ($this->actions != ['*'] && !in_array($event->action->id, $this->actions, true)) {
            return false;
        }

        return $event->type->getName() == $this->resolvable;
    }

    /**
     * @param ActionParamsResolveEvent $event
     */
    public function resolve(ActionParamsResolveEvent $event)
    {
        if ($this->callable) {
            $event->resolved = call_user_func($this->callable, $event->request, $event->action, $this->resolvable);
        } else {
            $event->resolved = $this->getResolvableObject()->resolve($event->request, $event->action);
        }

        $event->isResolved = true;
    }

    /**
     * @return ResolvableInterface
     */
    public function getResolvableObject(): ResolvableInterface
    {
        return new $this->resolvable;
    }
}