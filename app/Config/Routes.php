<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Global override 404
$routes->set404Override('\App\Controllers\Api\Error404Controller::index');

// Rutas API

$routes->get('/', 'Home::index');
