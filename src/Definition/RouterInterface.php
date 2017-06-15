<?php

namespace Rapture\Router\Definition;

/**
 * Router Interface
 *
 * @package Rapture\Router
 * @author  Iulian N. <rapture@iuliann.ro>
 * @license LICENSE MIT
 */
interface RouterInterface
{
    /**
     * Get route data
     *
     * @param string $httpMethod HTTP method
     * @param string $uri        URI
     *
     * @return array
     */
    public function route(string $httpMethod, string $uri):array;
}
