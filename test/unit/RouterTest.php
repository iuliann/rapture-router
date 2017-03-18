<?php

class RouterTest extends \PHPUnit_Framework_TestCase
{
    public function testRoutes()
    {
        $router = new \Rapture\Router\Router();

        $router->addRoutes([
            ['user-search','GET', '/user/search',                  'User\Search'],
            ['user-view',  'GET', '/user/{id}',                    'User\View'],
            ['user-edit',  'GET', '/user/edit/{id:\d+}[/{date}]',  'User\ViewDate'],
        ])->processRoutes();

        $this->assertEquals('/user/search', $router->name('user-search'));

        $this->assertEquals('/user/100', $router->name('user-view', 100));

        $this->assertEquals('/user/edit/100', $router->name('user-edit', 100));

        $this->assertEquals(
            [
                \Rapture\Router\Router::FOUND,
                'User\View',
                ['id' => 100]
            ],
            $router->route('GET', '/user/100')
        );

        $this->assertEquals(
            [
                \Rapture\Router\Router::NOT_FOUND,
            ],
            $router->route('GET', '/user/page/not/found')
        );

        $this->assertEquals(
            [
                \Rapture\Router\Router::FOUND,
                'User\ViewDate',
                [
                    'id' => '101',
                    'date' => '2015-01-02',
                ]
            ],
            $router->route('GET', '/user/edit/101/2015-01-02')
        );

        $this->assertEquals(
            [
                \Rapture\Router\Router::FOUND,
                'User\ViewDate',
                [
                    'id' => '101'
                ]
            ],
            $router->route('GET', '/user/edit/101')
        );

        $this->assertEquals(
            [
                \Rapture\Router\Router::NOT_FOUND,
            ],
            $router->route('GET', '/user/edit/101/')
        );
    }

    public function testGroup()
    {
        $router = new \Rapture\Router\Router();

        $router->addGroup(
            '/admin/user',
            [
                ['search', 'GET', '/search', 'Search'],
                ['view', 'GET', '/view/{id:\d+}', 'View'],
            ]
        )->processRoutes();

        $this->assertEquals('/admin/user/view/100', $router->name('admin-user-view', 100));
        $this->assertEquals(
            [
                \Rapture\Router\Router::FOUND,
                'Admin\User\View',
                [
                    'id' => '101'
                ]
            ],
            $router->route('GET', '/admin/user/view/101')
        );
    }
}
