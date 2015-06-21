<?php

namespace Jowy\Routing;

use Doctrine\Common\Cache\Cache;
use FastRoute\DataGenerator;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
     */
    public function __construct(array $options = [])
    {
        /**
         * generator type check
         */
        if (! $options["generator"] instanceof DataGenerator) {
            throw new \UnexpectedValueException(
                printf("Routing DataGenerator must be instance of %s", DataGenerator::class)
            );
        }

        /**
         * parser type check
         */
        if (! $options["parser"] instanceof RouteParser) {
            throw new \UnexpectedValueException(
                printf("Routing RouteParser must be instance of %s", RouteParser::class)
            );
        }

        /**
         * collection type check
         */
        if (! is_callable($options["collection"])) {
            throw new \UnexpectedValueException(
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
                throw new \UnexpectedValueException(
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
            throw new \UnexpectedValueException(
                printf("Routing Dispatcher must be instance of %s", Dispatcher::class)
            );
        }

        $this->options = $options;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return mixed
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $route_info = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        switch ($route_info[0]) {
            /**
             * if given uri dont match with our routes
             */
            case Dispatcher::NOT_FOUND:
                if (isset($this->options["onNotFound"]) && is_callable($this->options["onNotFound"])) {
                    $response = $this->options["onNotFound"]($request, $response);
                    break;
                }
                $response = $response->withStatus(404);
                $response->getBody()->write("Not Found");
                break;
            /**
             * if given uri match but method is not
             */
            case Dispatcher::METHOD_NOT_ALLOWED:
                if (isset($this->options["onMethodNotAllowed"]) && is_callable($this->options["onMethodNotAllowed"])) {
                    $response = $this->options["onMethodNotAllowed"]($request, $response);
                    break;
                }
                $response = $response->withStatus(405);
                $response->getBody()->write("Method Not Allowed");
                break;
            /**
             * finally dispatch to our route handler
             */
            case Dispatcher::FOUND:
                $response = $route_info[1]($request, $response, $route_info[2]);
                break;
        }

        return $next($request, $response);
    }
}
