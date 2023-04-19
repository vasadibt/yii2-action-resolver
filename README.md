# YII2 - Action Resolver

Extend Yii2 controller actions with custom resolved classes 

## Installation

Package is available on [Packagist](https://packagist.org/packages/vasadibt/yii2-action-resolver), you can install it using [Composer](https://getcomposer.org).


```shell
composer require vasadibt/yii2-action-resolver "^1.0"
```

or add to the require section of your `composer.json` file.

```
"vasadibt/yii2-action-resolver": "^1.0"
```

## Dependencies

- PHP 7.4+
- [yiisoft/yii2](https://github.com/yiisoft/yii2)
 
## Usage
 
You need to add `ActionResolveTrait` trait to your controller. 
THis will be extending your `bindInjectedParams` method with a new event trigger.
After that you can use easily the behavior:

```php

use vasadibt\actionresolver\ActionResolveTrait;
use vasadibt\actionresolver\ResolvableActionBehavior;

class CashregisterApiController extends \yii\web\Controller
{
    use ActionResolveTrait;
    
    public function behaviors()
    {
        return [
            'resolver' => [
                'class' => ResolvableActionBehavior::class,
                'resolvers' => [
                    // Define a new resolvable
                    [
                        'resolvable' => User::class, // User class need to implement `\vasadibt\actionresolver\ResolvableInterface`
                        // Optional you can filter the fiering action
                        // 'actions' => ['*'],
                        // 'actions' => ['view', 'update', 'delete'],
                    ],
                    
                    // or a simple way
                    User::class,
                    
                    // or you can use callable resolving
                    [
                        'resolvable' => Post::class,
                        'actions' => ['update'],
                        'callable' => function(Request $request, Action $action){
                            $post = static::findOne($request->post('id'));
                            if($post === null){
                                throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
                            }
                            if (!Yii::$app->user->can('updatePost', ['post' => $post])) {
                                throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
                            }
                            return $post;
                        },
                    ],
                ],
            ],
        ];
    }
    
    public function actionView(User $user)
    {
        return $this->render('view', ['user' => $user]);
    }
    
    public function actionUpdate(Request $request, Post $post)
    {
        if($post->load($request->post())){
            $post->save();
            return $this->redirect(['index']);
        }
        return $this->render('update', ['model' => $post]);
    }
    
    public function actionDelete(Post $post)
    {
        $post->delete();
        return $this->redirect(['index']);
       
    }
}
```

```php

use vasadibt\actionresolver\ResolvableInterface;

class User implements ResolvableInterface
{
    public function resolve($request, $action)
    {
        if($model = static::findOne($request->post('id'))){
            return $model;
        }
        throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
    }
}
 ```

