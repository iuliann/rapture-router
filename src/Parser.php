<?php

namespace Rapture\Router;

/**
 * Class Collector
 *
 * @credits https://github.com/nikic/FastRoute
 * @package Rapture\Router
 * @author  Nikita Popov <nikic@php.net>
 * @author  Iulian N. <rapture@iuliann.ro>
 * @license LICENSE MIT
 */
class Parser
{
    const VARIABLE_REGEX = <<<'REGEX'
\{
    \s* ([a-zA-Z_][a-zA-Z0-9_-]*) \s*
    (?:
        : \s* ([^{}]*(?:\{(?-1)\}[^{}]*)*)
    )?
\}
REGEX;
    const DEFAULT_DISPATCH_REGEX = '[^/]+';

    /**
     * @param string $route Route string
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public function parse($route)
    {
        $routeWithoutClosingOptionals = rtrim($route, ']');
        $numOptionals = strlen($route) - strlen($routeWithoutClosingOptionals);

        // Split on [ while skipping placeholders
        $segments = preg_split('~' . self::VARIABLE_REGEX . '(*SKIP)(*F) | \[~x', $routeWithoutClosingOptionals);
        if ($numOptionals !== count($segments) - 1) {
            // If there are any ] in the middle of the route, throw a more specific error message
            if (preg_match('~' . self::VARIABLE_REGEX . '(*SKIP)(*F) | \]~x', $routeWithoutClosingOptionals)) {
                throw new \InvalidArgumentException("Optional segments can only occur at the end of a route");
            }
            throw new \InvalidArgumentException("Number of opening '[' and closing ']' does not match");
        }

        $currentRoute = '';
        $routeData = [];
        foreach ($segments as $n => $segment) {
            if ($segment === '' && $n !== 0) {
                throw new \InvalidArgumentException("Empty optional part");
            }

            $currentRoute .= $segment;
            $routeData[] = $this->parsePlaceholders($currentRoute);
        }

        return $routeData;
    }

    /**
     * Parses a route string that does not contain optional segments.
     *
     * @param string $route Route string
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    private function parsePlaceholders($route)
    {
        if (!preg_match_all('~' . self::VARIABLE_REGEX . '~x', $route, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [$route];
        }

        $offset = 0;
        $routeData = [];
        foreach ($matches as $set) {
            if ($set[0][1] > $offset) {
                $routeData[] = substr($route, $offset, $set[0][1] - $offset);
            }
            $routeData[] = [
                $set[1][0],
                isset($set[2]) ? trim($set[2][0]) : self::DEFAULT_DISPATCH_REGEX,
            ];
            $offset = $set[0][1] + strlen($set[0][0]);
        }

        if ($offset != strlen($route)) {
            $routeData[] = substr($route, $offset);
        }

        return $routeData;
    }
}
