Routing Middleware
==================

[![Build Status](https://travis-ci.org/Atriedes/routing-middleware.svg?branch=master)](https://travis-ci.org/Atriedes/routing-middleware)


PSR 7 routing middleware based on [nikic/fast-route](https://github.com/nikic/FastRoute)

Installation & Requirements
---------------------------

Install using composer

```console
$ composer require jowy/routing-middleware
```

This library has following dependencies:

- `zendframework/zend-diactoros`, used for PSR 7 implementation
- `zendframework/zend-stratigility`, provide abstraction for PSR 7 middleware
- `nikic/fast-route`, used for routing
- `doctrine/cache`, used for caching routes

Usage
-----

Usage on `zendframework/zend-stratigility`

```php
use Zend\Stratigility\MiddlewarePipe;
use Jowy\Routing\Routing;

$app = new MiddlewarePipe();
$route_middleware = new Routing($options);

$app->pipe($route_middleware);
```

Usage on `relay/relay`

It advised to use container to resolve middleware when using `relay/relay`

```php
use Pimple\Container;
use Relay\Relay;
use Jowy\Routing\Routing;

$container = new Container();

$container["middleware"] = [
    Routing::class => function() {
        return new Routing($options);
    }
];

$resolver = function ($class) use ($container) {
    return $container[$class];
}

new Relay(array_keys($container["middleware"], $resolver);
```

Options
-------

All options is in array, with key => value format.

- **collection** (callable)
    contains registered route from `RouteCollector`
    
    ```php
    [
        "collection" => function (RouteCollector $collector) {
            $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                return $res;
            });
            $collector->addRoute("GET", "/home", function (ServerRequestInterface $req, ResponseInterface $res) {
                return $res;
            });
        }
    ]
    ```
- **generator** (object)
    implementation of `FastRoute\DataGenerator`
    
    ```php
    [
        "generator" => new FastRoute\DataGenerator\GroupCountBased();
    ]
    ```

- **parser** (object)
    implementation of `FastRoute\RouteParser`
    
    ```php
    [
        "parser" => new FastRoute\RouteParser\Std();
    ]
    ```
- **dispatcher** (callable)
    callable that return implementation of `FastRoute\Dispatcher`
    
    ```php
    [
        "dispatcher" => function ($dispatch_data) {
            return new FastRoute\Dispatcher\GroupCountBased($dispatch_data);
        }
    ]
    ```
    
- **onNotFound** (callable)
    triggered when route not found
    
    ```php
    [
        "onNotFound" => function (ServerRequestInterface $req, ResponseInterface $res) {
            // your own custom error not found handler
        }
    ]
    ```

- **onMethodNotAllowed** (callable)
    trigerred when route is match but method is not
    
    ```php
    [
        "onMethodNotAllowed" => function (ServerRequestInterface $req, ResponseInterface $res) {
            // your own custom error method not found handler
        }
    ]
    ```

- **cache** (boolean)
    toggle routes caching, default value is false
    
    ```php
    [
        "cache" => true
    ]
    ```
    
- **cacheDriver** (object)
    if `cache` is enabled you have to pass this param to options. It must contain implementation of `Doctrine\Common\Cache\Cache`
    
    ```php
    [
        "cacheDriver" => new ArrayCache()
    ]
    ```

License
-------

MIT, see LICENSE 