<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Global override 404
$routes->set404Override('\App\Controllers\Api\Error404Controller::index');

// Rutas API

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function ($routes) {

    /**
     * AUTH (JWT o tokens)
     */
    $routes->post('auth/login', 'AuthController::login');          // POST
    $routes->post('auth/logout', 'AuthController::logout');        // POST (revoca token actual)
    $routes->post('auth/refresh', 'AuthController::refresh');      // POST (si manejas refresh)
    $routes->get('auth/me', 'AuthController::me');                 // GET (perfil del token)

    /**
     * TENANTS (multi-empresa)
     * Nota: si eres SaaS, esto suele ser "superadmin only".
     */
    $routes->get('tenants', 'TenantsController::index');           // GET list
    $routes->post('tenants', 'TenantsController::create');         // POST create
    $routes->get('tenants/(:num)', 'TenantsController::show/$1');  // GET by id
    $routes->put('tenants/(:num)', 'TenantsController::update/$1'); // PUT update
    $routes->patch('tenants/(:num)', 'TenantsController::patch/$1'); // PATCH partial
    $routes->delete('tenants/(:num)', 'TenantsController::delete/$1');// DELETE

    /**
     * BRANCHES (sucursales)
     * Recomendado: filtrar por tenant del token.
     */
    $routes->get('branches', 'BranchesController::index');             // GET list (del tenant actual)
    $routes->post('branches', 'BranchesController::create');           // POST
    $routes->get('branches/(:num)', 'BranchesController::show/$1');    // GET
    $routes->put('branches/(:num)', 'BranchesController::update/$1');  // PUT
    $routes->patch('branches/(:num)', 'BranchesController::patch/$1'); // PATCH
    $routes->delete('branches/(:num)', 'BranchesController::delete/$1'); // DELETE

    // si quieres rutas anidadas por tenant (opcional, útil en UI admin)
    $routes->get('tenants/(:num)/branches', 'BranchesController::indexByTenant/$1');          // GET
    $routes->post('tenants/(:num)/branches', 'BranchesController::createForTenant/$1');       // POST

    /**
     * USERS
     */
    $routes->get('users', 'UsersController::index');                   // GET list
    $routes->post('users', 'UsersController::create');                 // POST create
    $routes->get('users/(:num)', 'UsersController::show/$1');          // GET one
    $routes->put('users/(:num)', 'UsersController::update/$1');        // PUT full update
    $routes->patch('users/(:num)', 'UsersController::patch/$1');       // PATCH partial
    $routes->delete('users/(:num)', 'UsersController::delete/$1');     // DELETE

    // cambiar password / reset
    $routes->post('users/(:num)/password', 'UsersController::setPassword/$1'); // POST

    // default branch
    $routes->put('users/(:num)/default-branch', 'UsersController::setDefaultBranch/$1'); // PUT

    /**
     * USER_BRANCHES (allowlist de sucursales por usuario)
     * 2 estilos: nested (recomendado) y directo.
     */
    // nested (limpio)
    $routes->get('users/(:num)/branches', 'UserBranchesController::index/$1');          // GET list branches for user
    $routes->post('users/(:num)/branches', 'UserBranchesController::attach/$1');        // POST attach {branch_id}
    $routes->delete('users/(:num)/branches/(:num)', 'UserBranchesController::detach/$1/$2'); // DELETE detach branch_id

    // estilo directo a la tabla pivote (opcional)
    $routes->post('user-branches', 'UserBranchesController::create');                  // POST {user_id, branch_id}
    $routes->delete('user-branches', 'UserBranchesController::delete');                // DELETE {user_id, branch_id}

    /**
     * ROLES
     */
    $routes->get('roles', 'RolesController::index');                // GET list
    $routes->post('roles', 'RolesController::create');              // POST
    $routes->get('roles/(:num)', 'RolesController::show/$1');       // GET
    $routes->put('roles/(:num)', 'RolesController::update/$1');     // PUT
    $routes->patch('roles/(:num)', 'RolesController::patch/$1');    // PATCH
    $routes->delete('roles/(:num)', 'RolesController::delete/$1');  // DELETE

    /**
     * USER_ROLES (asignación de roles a usuarios)
     */
    $routes->get('users/(:num)/roles', 'UserRolesController::index/$1');            // GET roles de user
    $routes->post('users/(:num)/roles', 'UserRolesController::attach/$1');          // POST {role_id}
    $routes->delete('users/(:num)/roles/(:num)', 'UserRolesController::detach/$1/$2'); // DELETE role_id

    // estilo directo pivote (opcional)
    $routes->post('user-roles', 'UserRolesController::create');                     // POST {user_id, role_id}
    $routes->delete('user-roles', 'UserRolesController::delete');                   // DELETE {user_id, role_id}

    /**
     * AUTH TOKENS (sesiones / revocación)
     * Si usas JWT, aquí guardas jti y gestionas logout global.
     */
    $routes->get('tokens', 'TokensController::index');                 // GET tokens del usuario actual (o admin)
    $routes->get('users/(:num)/tokens', 'TokensController::indexByUser/$1'); // GET tokens de un usuario (admin)
    $routes->delete('tokens/(:num)', 'TokensController::revoke/$1');   // DELETE revoke token by id

    // revocar por token string (jti) (opcional)
    $routes->post('tokens/revoke', 'TokensController::revokeByToken'); // POST {token}
    $routes->post('tokens/revoke-all', 'TokensController::revokeAll'); // POST (revoca todos los del user actual)
    $routes->post('users/(:num)/tokens/revoke-all', 'TokensController::revokeAllByUser/$1'); // POST (admin)

    /**
     * AUDIT LOG
     * Recomendado: solo lectura, paginado y filtros.
     */
    $routes->get('audit', 'AuditController::index');                 // GET list (filters: action, entity, user_id, branch_id, date range)
    $routes->get('audit/(:num)', 'AuditController::show/$1');        // GET one (opcional)

});
