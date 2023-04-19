<?php

namespace vasadibt\actionresolver;


use Yii;
use yii\base\Behavior;

/**
 * ```php
 *  public function behaviors()
 *  {
 *     return [
 *         'resolver' => [
 *             'class' => \vasadibt\actionresolver\ResolvableActionBehavior::class,
 *             'resolvers' => [
 *                  // Define a new resolvable
 *                  [
 *                      'resolvable' => User::class, // User class need to implement
 *                      // Optional you can filter the fiering action
 *                      // 'actions' => ['*'],
 *                      // 'actions' => ['view', 'update', 'delete'],
 *                  ],
 *
 *                  // or a simple way
 *                  User::class,
 *
 *                  // or you can use callable resolving
 *                  [
 *                      'resolvable' => Post::class,
 *                      'actions' => ['update'],
 *                      'callable' => function(Request $request, Action $action){
 *                          $post = static::findOne($request->post('id'));
 *                          if($post === null){
 *                              throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
 *                          }
 *                          if (!Yii::$app->user->can('updatePost', ['post' => $post])) {
 *                              throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
 *                          }
 *                          return $post;
 *                      },
 *                  ],
 *              ],
 *          ],
 *      ];
 *  }
 * ```
 *
 *```php
 * class User implements \vasadibt\actionresolver\ResolvableInterface
 * {
 *      public function resolve($request, $action)
 *      {
 *          if($model = static::findOne($request->post('id'))){
 *              return $model;
 *          }
 *          throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
 *      }
 * }
 * ```
 */
class ResolvableActionBehavior extends Behavior
{
    const EVENT_BEFORE_BIND_PARAM_INJECTION = 'beforeBindParamInjection';

    /**
     * @var array the default configuration of access rules. Individual rule configurations
     * specified via [[rules]] will take precedence when the same property of the rule is configured.
     */
    public $resolverConfig = ['class' => '\vasadibt\actionresolver\ActionResolver'];
    /**
     * @var ActionResolver[]
     */
    public $resolvers = [];

    /**
     * Initializes the [[resolvers]] array by instantiating resolver objects from configurations.
     */
    public function init()
    {
        parent::init();

        foreach ($this->resolvers as $index => $resolver) {

            if (is_string($resolver)) {
                $resolver = ['resolvable' => $resolver];
            }

            if (is_array($resolver)) {
                $this->resolvers[$index] = Yii::createObject(array_merge($this->resolverConfig, $resolver));
            }
        }
    }

    /**
     * Declares event handlers for the [[owner]]'s events.
     * @return array events (array keys) and the corresponding event handler methods (array values).
     */
    public function events()
    {
        return [static::EVENT_BEFORE_BIND_PARAM_INJECTION => 'resolveActionParam'];
    }

    /**
     * @return void
     */
    public function resolveActionParam(ActionParamsResolveEvent $event)
    {
        foreach ($this->resolvers as $resolver){
            if (!$resolver->isApplicable($event)){
                continue;
            }

            $resolver->resolve($event);
            break;
        }
    }
}