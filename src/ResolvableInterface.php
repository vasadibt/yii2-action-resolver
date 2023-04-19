<?php

namespace vasadibt\actionresolver;



interface ResolvableInterface
{
    /**
     * @param \yii\base\Request $request
     * @param \yii\base\Action $action
     *
     * @return object
     */
    public function resolve($request, $action);
}