<?php

namespace Rapture\Router;

/**
 * Data generator
 *
 * @credits https://github.com/nikic/FastRoute
 * @package Rapture\Router
 * @author  Nikita Popov <nikic@php.net>
 * @author  Iulian N. <rapture@iuliann.ro>
 * @license LICENSE MIT
 */
class Generator
{
    protected $staticRoutes = [];
    protected $methodToRegexToRoutesMap = [];
    protected $names = [];
    protected $approxChunkSize = 10;

    /**
     * @param array $regexToRoutesMap Regex to routes mapping
     *
     * @return array
     */
    protected function processChunk(array $regexToRoutesMap):array
    {
        $routeMap = [];
        $regexes = [];
        $numGroups = 0;
        foreach ($regexToRoutesMap as $regex => $route) {
            $numVariables = count($route->variables);
            $numGroups = max($numGroups, $numVariables);

            $regexes[] = $regex . str_repeat('()', $numGroups - $numVariables);
            $routeMap[$numGroups + 1] = [$route->handler, $route->variables];

            ++$numGroups;
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~';

        return ['regex' => $regex, 'routeMap' => $routeMap];
    }

    /**
     * Add route
     *
     * @param string $name       Route name
     * @param string $httpMethod HTTP method
     * @param array  $routeData  Route data
     * @param mixed  $handler    Route handler
     *
     * @return void
     */
    public function addRoute($name, $httpMethod, $routeData, $handler)
    {
        if ($this->isStaticRoute($routeData)) {
            $this->addStaticRoute($name, $httpMethod, $routeData, $handler);
        } else {
            $this->addVariableRoute($name, $httpMethod, $routeData, $handler);
        }
    }

    /**
     * Get routes data as [static, variable]
     *
     * @return array
     */
    public function getData()
    {
        if (empty($this->methodToRegexToRoutesMap)) {
            return [$this->staticRoutes, [], $this->names];
        }

        return [$this->staticRoutes, $this->generateVariableRouteData(), $this->names];
    }

    /**
     * @return array
     */
    protected function generateVariableRouteData()
    {
        $data = [];
        foreach ($this->methodToRegexToRoutesMap as $method => $regexToRoutesMap) {
            $chunkSize = $this->computeChunkSize(count($regexToRoutesMap));
            $chunks = array_chunk($regexToRoutesMap, $chunkSize, true);
            $data[$method] = array_map([$this, 'processChunk'], $chunks);
        }

        return $data;
    }

    /**
     * @param int $count Count
     *
     * @return float
     */
    protected function computeChunkSize($count)
    {
        $numParts = max(1, round($count / $this->approxChunkSize));

        return ceil($count / $numParts);
    }

    /**
     * @param array $routeData Route data
     *
     * @return bool
     */
    protected function isStaticRoute($routeData)
    {
        return count($routeData) === 1 && is_string($routeData[0]);
    }

    /**
     * @param string $name       Route name
     * @param string $httpMethod HTTP method
     * @param array  $routeData  Route data
     * @param mixed  $handler    Route handler
     *
     * @return void
     */
    protected function addStaticRoute(string $name, string $httpMethod, array $routeData, $handler)
    {
        $this->names[$name] = [$routeData[0], 0];

        $routeStr = $routeData[0];

        if (isset($this->staticRoutes[$httpMethod][$routeStr])) {
            throw new \LogicException(sprintf('Cannot register two routes matching "%s" for method "%s"', $routeStr, $httpMethod));
        }

        /** @var Route $route */
        if (isset($this->methodToRegexToRoutesMap[$httpMethod])) {
            foreach ($this->methodToRegexToRoutesMap[$httpMethod] as $route) {
                if ($route->matches($routeStr)) {
                    throw new \LogicException(
                        sprintf(
                            'Static route "%s" is shadowed by previously defined variable route "%s" for method "%s"',
                            $routeStr,
                            $route->regex,
                            $httpMethod
                        )
                    );
                }
            }
        }

        $this->staticRoutes[$httpMethod][$routeStr] = $handler;
    }

    /**
     * @param string $name       Route name
     * @param string $httpMethod Http method
     * @param array  $routeData  Route data
     * @param mixed  $handler    Route handler
     *
     * @return void
     */
    protected function addVariableRoute(string $name, string $httpMethod, array $routeData, $handler)
    {
        list($regex, $variables) = $this->buildRegexForRoute($routeData);

        $this->names[$name] = [
            implode(
                '',
                array_map(
                    function ($value) {
                        return is_array($value) ? '%s' : $value;
                    },
                    $routeData
                )
            ),
            count($variables),
        ];

        if (isset($this->methodToRegexToRoutesMap[$httpMethod][$regex])) {
            throw new \LogicException(sprintf('Cannot register two routes matching "%s" for method "%s"', $regex, $httpMethod));
        }

        $this->methodToRegexToRoutesMap[$httpMethod][$regex] = new Route($name, $httpMethod, $handler, $regex, $variables);
    }

    /**
     * @param array $routeData Route data
     *
     * @return array
     */
    protected function buildRegexForRoute($routeData)
    {
        $regex = '';
        $variables = [];
        foreach ($routeData as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }

            list($varName, $regexPart) = $part;

            if (isset($variables[$varName])) {
                throw new \LogicException(sprintf('Cannot use the same placeholder "%s" twice', $varName));
            }

            if ($this->regexHasCapturingGroups($regexPart)) {
                throw new \LogicException(sprintf('Regex "%s" for parameter "%s" contains a capturing group', $regexPart, $varName));
            }

            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $variables];
    }

    /**
     * @param string $regex Regex value
     *
     * @return bool|int
     */
    protected function regexHasCapturingGroups($regex)
    {
        if (false === strpos($regex, '(')) {
            // Needs to have at least a ( to contain a capturing group
            return false;
        }

        // Semi-accurate detection for capturing groups
        return preg_match(
            '~
                (?:
                    \(\?\(
                  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
                  | \\\\ .
                ) (*SKIP)(*FAIL) |
                \(
                (?!
                    \? (?! <(?![!=]) | P< | \' )
                  | \*
                )
            ~x',
            $regex
        );
    }
}
