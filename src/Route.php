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
     * @param string $name       Route name
     * @param string $httpMethod Route HTTP method
     * @param mixed  $handler    Route handler
     * @param string $regex      Route regex
     * @param array  $variables  Route variables
     *
     * @return void
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
     * @param string $str String to match on
     *
     * @return bool
     */
    public function matches($str)
    {
        $regex = '~^' . $this->regex . '$~';

        return (bool)preg_match($regex, $str);
    }
}
