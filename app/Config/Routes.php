<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Global override 404
$routes->set404Override('\App\Controllers\Api\Error404Controller::index');

// Rutas API

$routes->group('api', ['namespace' => 'App\Controllers\Api\App'], static function (RouteCollection $routes) {
    $routes->group('users', static function (RouteCollection $routes) {
        $routes->get('', 'UsersController::index');
        $routes->post('', 'UsersController::create');
        $routes->get('(:num)', 'UsersController::show/$1');
        $routes->put('(:num)', 'UsersController::update/$1');
        $routes->delete('(:num)', 'UsersController::delete/$1');
    });
});
