<?php

namespace Rapture\Router;

/**
 * Class Route
 *
 * @credits https://github.com/nikic/FastRoute
 * @package Rapture\Router
 * @author  Nikita Popov <nikic@php.net>
 * @author  Iulian N. <rapture@iuliann.ro>
 * @license LICENSE MIT
 */
class Route
{
    public $name;
    public $httpMethod;
    public $regex;
    public $variables;
    public $handler;

    /**
     * Constructs a route (value object).
     *
     * @param string $httpMethod
     * @param mixed  $handler
     * @param string $regex
     * @param array  $variables
     */
    public function __construct($name, $httpMethod, $handler, $regex, $variables)
    {
        $this->name = $name;
        $this->httpMethod = $httpMethod;
        $this->handler = $handler;
        $this->regex = $regex;
        $this->variables = $variables;
    }

    /**
     * Tests whether this route matches the given string.
     *
     * @param string $str
     *
     * @return bool
     */
    public function matches($str)
    {
        $regex = '~^' . $this->regex . '$~';

        return (bool)preg_match($regex, $str);
    }
}
