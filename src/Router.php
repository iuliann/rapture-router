<?php

namespace Rapture\Router;

use Rapture\Router\Definition\RouterInterface;

/**
 * Routes Collector
 *
 * @credits https://github.com/nikic/FastRoute
 * @package Rapture\Router
 * @author  Nikita Popov <nikic@php.net>
 * @author  Iulian N. <rapture@iuliann.ro>
 * @license LICENSE MIT
 */
class Router implements RouterInterface
{
    const FOUND       = 200;
    const NOT_FOUND   = 404;
    const NOT_ALLOWED = 405;

    /** @var Parser */
    protected $routeParser;

    /** @var Generator */
    protected $dataGenerator;

    protected $currentGroupPrefix;

    /** @var array */
    protected $staticRoutes = [];

    /** @var array */
    protected $variableRoutes = [];

    /** @var array */
    protected $namedRoutes = [];

    /**
     * Collector constructor
     */
    public function __construct()
    {
        $this->routeParser = new Parser();
        $this->dataGenerator = new Generator();
    }

    /**
     * Adds a single route to collection.
     *
     * The syntax used in the $route string depends on the used route parser.
     *
     * @param string $name       Route name
     * @param string $httpMethod HTTP method(s)
     * @param string $route      Route regex
     * @param mixed  $handler    Handler
     *
     * @return $this
     */
    public function addRoute(string $name, string $httpMethod, string $route, $handler)
    {
        $route = $this->currentGroupPrefix . $route;
        $routesData = $this->routeParser->parse($route);
        foreach ($routesData as $routeData) {
            $this->dataGenerator->addRoute($name, $httpMethod, $routeData, $handler);
        }

        return $this;
    }

    /**
     * Add multiple routes at once
     *
     * @param array $routes Routes
     *
     * @return $this
     */
    public function addRoutes(array $routes)
    {
        foreach ($routes as $group => $route) {
            if (is_string($group)) {
                $this->addGroup($group, $route);
            }
            else {
                $this->addRoute($route[0], $route[1], $route[2], $route[3]);
            }
        }

        return $this;
    }

    /**
     * Add group of routes with directory pairing.
     * Example:
     *  $router->addGroup(
     *      '/admin/user',
     *      [
     *          ['search', 'GET', '/search', 'Search']
     *      ],
     *  );
     *
     * @param string $group  Group name
     * @param array  $routes Routes
     *
     * @return $this
     */
    public function addGroup(string $group, array $routes = [])
    {
        $prefix = str_replace('/', '-', strtolower(trim($group, '/')));
        $ns     = implode(
            '\\',
            array_map(
                function ($value) {
                    return ucfirst($value);
                },
                explode(
                    '/',
                    trim($group, '/')
                )
            )
        );

        foreach ($routes as $route) {
            $this->addRoute("{$prefix}-{$route[0]}", $route[1], "{$group}{$route[2]}", "{$ns}\\{$route[3]}");
        }

        return $this;
    }

    /**
     * Must be called before fetching routes
     *
     * @return $this
     */
    public function processRoutes()
    {
        $routes = $this->dataGenerator->getData();

        $this->staticRoutes   = $routes[0];
        $this->variableRoutes = $routes[1];
        $this->namedRoutes    = $routes[2];

        return $this;
    }

    /**
     * Get named route
     *
     * @param string $name   Route name
     * @param mixed  $params Route params
     *
     * @return string
     */
    public function name(string $name, $params = []):string
    {
        if (isset($this->namedRoutes[$name])) {
            return rtrim(
                vsprintf(
                    $this->namedRoutes[$name][0],
                    (array)$params + array_fill(0, (int)$this->namedRoutes[$name][1], '')
                ),
                '/'
            );
        }

        throw new \InvalidArgumentException(sprintf('Invalid route name: "%s"', $name));
    }

    /**
     * Get route data
     *
     * @param string $httpMethod HTTP method
     * @param string $uri        URI to parse
     *
     * @return array
     */
    public function route(string $httpMethod, string $uri):array
    {
        if (isset($this->staticRoutes[$httpMethod][$uri])) {
            $handler = $this->staticRoutes[$httpMethod][$uri];

            return [self::FOUND, $handler, []];
        }

        if (isset($this->variableRoutes[$httpMethod])) {
            $result = $this->dispatchVariableRoute($this->variableRoutes[$httpMethod], $uri);
            if ($result[0] === self::FOUND) {
                return $result;
            }
        }

        // For HEAD requests, attempt fallback to GET
        if ($httpMethod === 'HEAD') {
            if (isset($this->staticRoutes['GET'][$uri])) {
                $handler = $this->staticRoutes['GET'][$uri];

                return [self::FOUND, $handler, []];
            }
            if (isset($this->variableRoutes['GET'])) {
                $result = $this->dispatchVariableRoute($this->variableRoutes['GET'], $uri);
                if ($result[0] === self::FOUND) {
                    return $result;
                }
            }
        }

        // If nothing else matches, try fallback routes
        if (isset($this->staticRoutes['*'][$uri])) {
            $handler = $this->staticRoutes['*'][$uri];

            return [self::FOUND, $handler, []];
        }
        if (isset($this->variableRoutes['*'])) {
            $result = $this->dispatchVariableRoute($this->variableRoutes['*'], $uri);
            if ($result[0] === self::FOUND) {
                return $result;
            }
        }

        // Find allowed methods for this URI by matching against all other HTTP methods as well
        $allowedMethods = [];

        foreach ($this->staticRoutes as $method => $uriMap) {
            if ($method !== $httpMethod && isset($uriMap[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        foreach ($this->variableRoutes as $method => $routeData) {
            if ($method === $httpMethod) {
                continue;
            }

            $result = $this->dispatchVariableRoute($routeData, $uri);
            if ($result[0] === self::FOUND) {
                $allowedMethods[] = $method;
            }
        }

        // If there are no allowed methods the route simply does not exist
        if ($allowedMethods) {
            return [self::NOT_ALLOWED, $allowedMethods];
        } else {
            return [self::NOT_FOUND];
        }
    }

    /**
     * @param array  $routeData Route data
     * @param string $uri       URI
     *
     * @return array
     */
    protected function dispatchVariableRoute($routeData, $uri)
    {
        foreach ($routeData as $data) {
            if (!preg_match($data['regex'], $uri, $matches)) {
                continue;
            }

            list($handler, $varNames) = $data['routeMap'][count($matches)];

            $vars = [];
            $i = 0;
            foreach ($varNames as $varName) {
                $vars[$varName] = $matches[++$i];
            }

            return [self::FOUND, $handler, $vars];
        }

        return [self::NOT_FOUND];
    }
}
