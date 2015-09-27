[![Latest Stable Version](https://poser.pugx.org/ergy/slim-annotations-router/v/stable.svg)](https://packagist.org/packages/ergy/slim-annotations-router) [![Total Downloads](https://poser.pugx.org/ergy/slim-annotations-router/downloads.svg)](https://packagist.org/packages/ergy/slim-annotations-router) [![Latest Unstable Version](https://poser.pugx.org/ergy/slim-annotations-router/v/unstable.svg)](https://packagist.org/packages/ergy/slim-annotations-router) [![License](https://poser.pugx.org/ergy/slim-annotations-router/license.svg)](https://packagist.org/packages/ergy/slim-annotations-router)

# Slim Annotations Router
Controller and annotations based router for Slim Framework V3

## Installation
Via [Composer](https://getcomposer.org/) :
```bash
composer require ergy/slim-annotations-router
```
## Initialization
To initialize the annotations router, simply add this lines to your index.php file that loads your Slim application :
```php
$app = new \Slim\App();
$c = $app->getContainer();
$c['router'] = function() use ($app) {
  return new \Ergy\Slim\Annotations\Router($app,
    '/path_to_controller_files/', // Path to controller files, will be scanned recursively
    '/path_to_cache_files/' // Path to annotations router cache files, must be writeable by web server, if it doesn't exist, router will attempt to create it
  );
};
```
If your application contains controllers in multiple folders, you can add them by passing an array in the second parameter to the constructor instead of a string, eg : 
```php
return new \Ergy\Slim\Annotations\Router($app,
  ['/path_to_controller_files_1/', '/path_to_controller_files_2/', ...],
  '/path_to_cache_files/'
);
```
## Controller files
The annotations router detects all files with names ending in "Controller.php" and all public methods with names ending in "Action". It then parses their annotations to generate routes recognized by Slim framework.

The following annotations are supported :

| Name | Level | Type | Required | Description |
| ---- | ----- | ---- | :------: | ----------- |
| @RoutePrefix | Class | string | no | The route prefix, used for all routes in the current class|
| @Route | Method | Object | yes | The route definition |

The **@Route** object accepts the following syntaxes :
```php
// Route pattern (required), can contain regular expressions
@Route("/home")

// Route methods (optional), if not set only GET route will be generated
@Route("/home", methods={"GET","POST","PUT"})

// Route name (optional), used to generate route with Slim pathFor() method
@Route("/home", methods={"GET","POST","PUT"}, name="home")
```
Let's see an example :
```php
/**
 * File /my/app/controllers/home.php
 * @RoutePrefix("/home")
 */
class HomeController
{
  /**
   * @Route("/hello/{name:[\w]+}", methods={"GET","POST"}, name="home.hello")
   */
  public function helloAction($name)
  {
    echo 'Hello '.$name.' !';
  }
}
```
By opening the url **http://your_site_url/home/hello/foobar**, you should see "Hello foobar !".

## Get Slim dependency container
Once your controller loaded, you might need to interact with the current HTTP request and response, to get theses objects, your controller simply has to extends the *Ergy\Slim\Annotations\Controller* class.

Extending this class allows you to retrieve a reference to the Slim dependency container, so to retrieve the current request, you simply have to ask for **$this->request**.

Let's see an example with the previous class :
```php
/**
 * File /my/app/controllers/home.php
 * @RoutePrefix("/home")
 */
class HomeController extends Ergy\Slim\Annotations\Controller
{
  /**
   * @Route("/hello/{name:[\w]+}", methods={"GET","POST"}, name="home.hello")
   */
  public function helloAction($name)
  {
    echo 'Hello '.$name.' !';
    
    // Dump the current request details
    var_dump($this->request);
    
    // Dump the current response details
    var_dump($this->response);
  }
}
```
Since we have a reference to the Slim dependency container, we can retrieve any object it contains, for example if you want to retrieve the applications settings, simply ask for **$this->settings**.

## Routing events
The annotations router exposes the methods *beforeExecuteRoute* and *afterExecuteRoute*.

These methods take as a parameter a *Ergy\Slim\Annotations\RouteInfo* instance that gives you information about the running route.

Implementing these methods in your controller (or one of its parent classes) allow you to implement hook points before/after the actions are executed.

Example with the previous class :
```php
/**
 * File /my/app/controllers/home.php
 * @RoutePrefix("/home")
 */
class HomeController
{
  public function beforeExecuteRoute(Ergy\Slim\Annotations\RouteInfo $route)
  {
    echo 'before ';
  }
  
  public function afterExecuteRoute() // Parameter Ergy\Slim\Annotations\RouteInfo is optional
  {
    echo ' after';
  }
  
  /**
   * @Route("/hello/{name:[\w]+}", methods={"GET","POST"}, name="home.hello")
   */
  public function helloAction($name)
  {
    echo 'Hello '.$name.' !';
  }
}
```
By opening the url **http://your_site_url/home/hello/foobar**, you should see "before Hello foobar ! after".

### Operation cancellation
Previous hooks allow you to cancel operations if needed :
* You can cancel execution of current route and *afterExecuteRoute* method by returning *false* in the method *beforeExecuteRoute*.
* You can cancel the call to *afterExecuteRoute* method by returning *false* in the current route method.

For example :
```php
/**
 * File /my/app/controllers/home.php
 * @RoutePrefix("/home")
 */
class HomeController
{
  public function beforeExecuteRoute(Ergy\Slim\Annotations\RouteInfo $route)
  {
    echo 'before ';
  }
  
  public function afterExecuteRoute() // Parameter Ergy\Slim\Annotations\RouteInfo is optional
  {
    echo ' after';
  }
  
  /**
   * @Route("/hello/{name:[\w]+}", methods={"GET","POST"}, name="home.hello")
   */
  public function helloAction($name)
  {
    echo 'Hello '.$name.' !';
    return false;
  }
}
```
By opening the url **http://your_site_url/home/hello/foobar**, you should see "before Hello foobar !".