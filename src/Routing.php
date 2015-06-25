<?php

namespace Jowy\Routing;

use Doctrine\Common\Cache\Cache;
use FastRoute\DataGenerator;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zend\Stratigility\MiddlewareInterface;

/**
 * Class Routing
 * @package Jowy\Routing
 */
class Routing implements MiddlewareInterface
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param array $options
     * @throws \InvalidArgumentException when passing invalid argument
     */
    public function __construct(array $options = [])
    {
        /**
         * generator type check
         */
        if (! $options["generator"] instanceof DataGenerator) {
            throw new \InvalidArgumentException(
                printf("Routing DataGenerator must be instance of %s", DataGenerator::class)
            );
        }

        /**
         * parser type check
         */
        if (! $options["parser"] instanceof RouteParser) {
            throw new \InvalidArgumentException(
                printf("Routing RouteParser must be instance of %s", RouteParser::class)
            );
        }

        /**
         * collection type check
         */
        if (! is_callable($options["collection"])) {
            throw new \InvalidArgumentException(
                printf("Routing Collection must be callable")
            );
        }

        /**
         * fetch cached routes if cache config enabled and cached routes exist
         */
        if (isset($options["cache"]) && $options["cache"] === true) {
            /**
             * check cache driver type
             */
            if (! isset($options["cacheDriver"]) && ! $options["cacheDriver"] instanceof Cache) {
                throw new \InvalidArgumentException(
                    printf("Routing CacheDriver must be instance of %s", Cache::class)
                );
            }

            /**
             * is cached routes exist?
             */
            if ($options["cacheDriver"]->contains("jowy.routing.cache")) {
                $dispatch_data = is_array($options["cacheDriver"]->fetch("jowy.routing.cache"))
                    ? ($options["cacheDriver"]->fetch("jowy.routing.cache"))
                    : null;
            } else {
                $dispatch_data = null;
            }
        }

        /**
         * build routes if no cached routes
         */
        if (! isset($dispatch_data) || $dispatch_data === null) {
            $route_collector = new RouteCollector($options["parser"], $options["generator"]);
            $options["collection"]($route_collector);
            $dispatch_data = $route_collector->getData();

        }

        /**
         * save compiled routes in cache storage if config allowed
         */
        if (isset($options["cache"]) && $options["cache"] === true) {
            $options["cacheDriver"]->save("jowy.routing.cache", var_export($dispatch_data, true));
        }

        /**
         * build dispatcher
         */
        $this->dispatcher = is_callable($options["dispatcher"]) ? $options["dispatcher"]($dispatch_data) : null;
        if (! $this->dispatcher instanceof Dispatcher) {
            throw new \InvalidArgumentException(
                printf("Routing Dispatcher must be instance of %s", Dispatcher::class)
            );
        }

        $this->options = $options;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @throws NotFoundHttpException when uri not matched
     * @throws MethodNotAllowedHttpException when uri is matched but http method isnt
     * @throws \InvalidArgumentException when passing invalid argument
     * @throws \UnexpectedValueException when returned response from handler is not implement ResponseInterface
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $route_info = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        switch ($route_info[0]) {
            /**
             * if given uri dont match with our routes
             */
            case Dispatcher::NOT_FOUND:
                throw new NotFoundHttpException("Not Found");
                break;
            /**
             * if given uri match but method is not
             */
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new MethodNotAllowedHttpException([$route_info[0][1]], "Method Not Allowed");
                break;
            /**
             * finally dispatch to our route handler
             */
            case Dispatcher::FOUND:
                $response = $this->handleFound($request, $response, $route_info);
                break;
        }

        if (! $response instanceof ResponseInterface) {
            throw new \UnexpectedValueException(
                sprintf("Controller must return object instance of %s", ResponseInterface::class)
            );
        }
        return $next($request, $response);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $route_info
     * @return ResponseInterface
     */
    private function handleFound(ServerRequestInterface $request, ResponseInterface $response, array $route_info)
    {
        if (is_callable($route_info[1])) {
            return $route_info[1]($request, $response, $route_info[2]);
        }

        list($class, $method) = explode(":", $route_info[1]);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf("%s is not exist", $class));
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new \InvalidArgumentException(sprintf("%s is not found on %s", $method, $class));
        }

        $reflection_method = new \ReflectionMethod($controller, $method);

        $args = $reflection_method->getParameters();

        /**
         * fill method params
         */
        $params = [];
        foreach ($args as $arg) {
            if ($arg->isArray()) {
                $params[] = $route_info[2];
                continue;
            }

            if ($arg->getClass()->name === ServerRequestInterface::class) {
                $params[] = $request;
            }

            if ($arg->getClass()->name === ResponseInterface::class) {
                $params[] = $response;
            }
        }

        /**
         * call handler
         */
        return call_user_func_array([$controller, $method], $params);
    }
}
