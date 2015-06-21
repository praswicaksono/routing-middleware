<?php

namespace spec\Jowy\Routing;

use Doctrine\Common\Cache\FilesystemCache;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Uri;
use Zend\Stratigility\MiddlewareInterface;

class RoutingSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType("Jowy\\Routing\\Routing");
        $this->shouldImplement(MiddlewareInterface::class);
    }

    public function let()
    {
        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => new DataGenerator(),
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                }
            ]
        );
    }

    public function it_match_with_request_info()
    {
        $request = ServerRequestFactory::fromGlobals()->withUri(new Uri("http://google.com/"))->withMethod("GET");
        $response = new Response();

        $res = $this->__invoke(
            $request,
            $response,
            function (RequestInterface $req, ResponseInterface $res) {
                return $res;
            }
        );

        $res->shouldBeAnInstanceOf(ResponseInterface::class);
        $res->getStatusCode()->shouldBeLike(200);
    }

    public function it_dont_match_with_request_uri()
    {
        $request = ServerRequestFactory::fromGlobals()->withUri(new Uri("http://google.com/home"))->withMethod("GET");
        $response = new Response();

        $res = $this->__invoke(
            $request,
            $response,
            function (RequestInterface $req, ResponseInterface $res) {
                return $res;
            }
        );

        $res->shouldBeAnInstanceOf(ResponseInterface::class);
        $res->getStatusCode()->shouldBeLike(404);
    }

    public function it_dont_match_with_request_method()
    {
        $request = ServerRequestFactory::fromGlobals()->withUri(new Uri("http://google.com/"))->withMethod("POST");
        $response = new Response();

        $res = $this->__invoke(
            $request,
            $response,
            function (RequestInterface $req, ResponseInterface $res) {
                return $res;
            }
        );

        $res->shouldBeAnInstanceOf(ResponseInterface::class);
        $res->getStatusCode()->shouldBeLike(405);
    }

    public function it_handle_not_found_error()
    {
        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => new DataGenerator(),
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                },
                "onNotFound" => function (ServerRequestInterface $req, ResponseInterface $res) {
                    return $res->withStatus(404, "Custom Not Found");
                }
            ]
        );

        $request = ServerRequestFactory::fromGlobals()->withUri(new Uri("http://google.com/home"))->withMethod("GET");
        $response = new Response();

        $res = $this->__invoke(
            $request,
            $response,
            function (ServerRequestInterface $req, ResponseInterface $res) {
                return $res;
            }
        );

        $res->shouldBeAnInstanceOf(ResponseInterface::class);
        $res->getReasonPhrase()->shouldBeLike("Custom Not Found");
    }

    public function it_handle_method_not_allowed_error()
    {
        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => new DataGenerator(),
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                },
                "onMethodNotAllowed" => function (ServerRequestInterface $req, ResponseInterface $res) {
                    return $res->withStatus(405, "Custom Method Not Allowed");
                }
            ]
        );

        $request = ServerRequestFactory::fromGlobals()->withUri(new Uri("http://google.com/"))->withMethod("POST");
        $response = new Response();

        $res = $this->__invoke(
            $request,
            $response,
            function (RequestInterface $req, ResponseInterface $res) {
                return $res;
            }
        );

        $res->shouldBeAnInstanceOf(ResponseInterface::class);
        $res->getReasonPhrase()->shouldBeLike("Custom Method Not Allowed");
    }

    public function it_not_valid_routing_dispatcher()
    {
        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => new DataGenerator(),
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return null;
                }
            ]
        );

        $this->shouldThrow("\\UnexpectedValueException")->duringInstantiation();
    }

    public function it_not_valid_routing_collection()
    {
        $this->beConstructedWith(
            [
                "collection" => null,
                "generator" => new DataGenerator(),
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                }
            ]
        );

        $this->shouldThrow("\\UnexpectedValueException")->duringInstantiation();
    }

    public function it_not_valid_routing_parser()
    {
        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => new DataGenerator(),
                "parser" => null,
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                }
            ]
        );

        $this->shouldThrow("\\UnexpectedValueException")->duringInstantiation();
    }
    public function it_not_valid_routing_data_generator()
    {
        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => null,
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                }
            ]
        );

        $this->shouldThrow("\\UnexpectedValueException")->duringInstantiation();
    }

    public function it_cache_routes()
    {
        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => new DataGenerator(),
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                },
                "cache" => true,
                "cacheDriver" =>  new FilesystemCache("cache", ".cache")
            ]
        );

        $request = ServerRequestFactory::fromGlobals()->withUri(new Uri("http://google.com/"))->withMethod("GET");
        $response = new Response();

        $res = $this->__invoke(
            $request,
            $response,
            function (ServerRequestInterface $req, ResponseInterface $res) {
                return $res;
            }
        );

        $res->shouldBeAnInstanceOf(ResponseInterface::class);
        $res->getStatusCode()->shouldBeLike(200);
    }

    public function it_use_cached_routes()
    {
        $cache = new FilesystemCache("cache", ".cache");
        $cache->deleteAll();

        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => new DataGenerator(),
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                },
                "cache" => true,
                "cacheDriver" =>  $cache
            ]
        );

        $request = ServerRequestFactory::fromGlobals()->withUri(new Uri("http://google.com/"))->withMethod("GET");
        $response = new Response();

        $res = $this->__invoke(
            $request,
            $response,
            function (ServerRequestInterface $req, ResponseInterface $res) {
                return $res;
            }
        );

        $res->shouldBeAnInstanceOf(ResponseInterface::class);
        $res->getStatusCode()->shouldBeLike(200);
    }

    public function it_not_valid_cache_driver()
    {
        $this->beConstructedWith(
            [
                "collection" => function (RouteCollector $collector) {
                    $collector->addRoute("GET", "/", function (ServerRequestInterface $req, ResponseInterface $res) {
                        return $res;
                    });
                },
                "generator" => new DataGenerator(),
                "parser" => new RouteParser(),
                "dispatcher" => function (array $dispatch_data) {
                    return new Dispatcher($dispatch_data);
                },
                "cache" => true,
                "cacheDriver" => null
            ]
        );

        $this->shouldThrow("\\UnexpectedValueException")->duringInstantiation();
    }
}
