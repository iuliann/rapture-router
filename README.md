# Rapture PHP Router

[![PhpVersion](https://img.shields.io/badge/php-7.0-orange.svg?style=flat-square)](#)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](#)

A simple fork from the [nikic/FastRoute](https://github.com/nikic/FastRoute) implementation with reverse routing.

It implements the [`GroupCountBased`](http://nikic.github.io/2014/02/18/Fast-request-routing-using-regular-expressions.html) algorithm.

## Requirements

- PHP v7.0
- php-pcre

## Install

```
composer require iuliann/rapture-router
```

## Quick start

For more info check `nikic/FastRoute`.
```php
// add multiple routes
$router = new \Rapture\Router\Router();
$router->addRoutes([
    ['user-add',   'GET', '/user/add',                     'User\Add'],
    ['user-edit',  'GET', '/user/edit/{id:\d+}[/{check}]', 'User\ViewDate'],
])->processRoutes(); // run once after each routes have been added

// add group
$router->addGroup(
    '/admin/user',
    [
        ['search', 'GET', '/search',        'Search'],
        ['view',   'GET', '/view/{id:\d+}', 'View'],
    ],
)->processRoutes();
// ...is same as...
$router->addRoute('admin-user-search', 'GET', '/admin/user/search', 'Admin\User\Search');
$router->addRoute('admin-user-view', 'GET', '/admin/user/view/{id:\d+}', 'Admin\User\View')
$router->processRoutes();


// [Router::FOUND, 'User\View', ['id' => 100]],
$router->route('GET', '/user/edit/100');

// [Router::NOT_FOUND],
$router->route('POST', '/user/edit/100');

// [Router::NOT_FOUND],
$router->route('GET', '/user/edit/100/'); // trailing slash
```

## About

### Author

Iulian N. `rapture@iuliann.ro`

### Testing

```
cd ./test && phpunit
```

### Credits

- https://github.com/nikic/FastRoute

### License

Rapture PHP Router is licensed under the MIT License - see the `LICENSE` file for details.
