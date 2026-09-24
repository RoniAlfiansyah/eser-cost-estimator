<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api', 'filter' => 'cors'], static function ($routes): void {
    $routes->options('ping', 'PingController::index');
    $routes->get('ping', 'PingController::index');
    $routes->options('login', 'AuthController::login');
    $routes->post('login', 'AuthController::login');
    $routes->options('register', 'AuthController::register');
    $routes->post('register', 'AuthController::register');
    $routes->options('logout', 'AuthController::logout');
    $routes->post('logout', 'AuthController::logout');
    $routes->options('me', 'AuthController::me');
    $routes->get('me', 'AuthController::me', ['filter' => 'api-auth']);
    $routes->options('users', 'UserController::index');
    $routes->get('users', 'UserController::index', ['filter' => 'api-auth']);
    $routes->post('users', 'UserController::store', ['filter' => 'api-auth']);
    $routes->options('users/(:num)', 'UserController::show/$1');
    $routes->get('users/(:num)', 'UserController::show/$1', ['filter' => 'api-auth']);
    $routes->put('users/(:num)', 'UserController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('users/(:num)', 'UserController::delete/$1', ['filter' => 'api-auth']);
    $routes->options('categories', 'CategoryController::index');
    $routes->get('categories', 'CategoryController::index', ['filter' => 'api-auth']);
    $routes->post('categories', 'CategoryController::store', ['filter' => 'api-auth']);
    $routes->options('categories/(:num)', 'CategoryController::show/$1');
    $routes->get('categories/(:num)', 'CategoryController::show/$1', ['filter' => 'api-auth']);
    $routes->put('categories/(:num)', 'CategoryController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('categories/(:num)', 'CategoryController::delete/$1', ['filter' => 'api-auth']);
    $routes->options('subcategories', 'SubcategoryController::index');
    $routes->get('subcategories', 'SubcategoryController::index', ['filter' => 'api-auth']);
    $routes->post('subcategories', 'SubcategoryController::store', ['filter' => 'api-auth']);
    $routes->options('subcategories/import', 'SubcategoryController::import');
    $routes->post('subcategories/import', 'SubcategoryController::import', ['filter' => 'api-auth']);
    $routes->options('subcategories/(:num)', 'SubcategoryController::show/$1');
    $routes->get('subcategories/(:num)', 'SubcategoryController::show/$1', ['filter' => 'api-auth']);
    $routes->put('subcategories/(:num)', 'SubcategoryController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('subcategories/(:num)', 'SubcategoryController::delete/$1', ['filter' => 'api-auth']);
    $routes->options('units', 'UnitController::index');
    $routes->get('units', 'UnitController::index', ['filter' => 'api-auth']);
    $routes->post('units', 'UnitController::store', ['filter' => 'api-auth']);
    $routes->options('units/(:num)', 'UnitController::show/$1');
    $routes->get('units/(:num)', 'UnitController::show/$1', ['filter' => 'api-auth']);
    $routes->put('units/(:num)', 'UnitController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('units/(:num)', 'UnitController::delete/$1', ['filter' => 'api-auth']);
    $routes->options('cost-items', 'CostItemController::index');
    $routes->get('cost-items', 'CostItemController::index', ['filter' => 'api-auth']);
    $routes->post('cost-items', 'CostItemController::store', ['filter' => 'api-auth']);
    $routes->options('cost-items/(:num)', 'CostItemController::show/$1');
    $routes->get('cost-items/(:num)', 'CostItemController::show/$1', ['filter' => 'api-auth']);
    $routes->put('cost-items/(:num)', 'CostItemController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('cost-items/(:num)', 'CostItemController::delete/$1', ['filter' => 'api-auth']);
    $routes->options('signatories', 'SignatoryController::index');
    $routes->get('signatories', 'SignatoryController::index', ['filter' => 'api-auth']);
    $routes->post('signatories', 'SignatoryController::store', ['filter' => 'api-auth']);
    $routes->options('signatories/(:num)', 'SignatoryController::show/$1');
    $routes->get('signatories/(:num)', 'SignatoryController::show/$1', ['filter' => 'api-auth']);
    $routes->put('signatories/(:num)', 'SignatoryController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('signatories/(:num)', 'SignatoryController::delete/$1', ['filter' => 'api-auth']);
    $routes->options('basic-costs', 'BasicCostController::index');
    $routes->get('basic-costs', 'BasicCostController::index', ['filter' => 'api-auth']);
    $routes->post('basic-costs', 'BasicCostController::store', ['filter' => 'api-auth']);
    $routes->options('basic-costs/(:num)', 'BasicCostController::show/$1');
    $routes->get('basic-costs/(:num)', 'BasicCostController::show/$1', ['filter' => 'api-auth']);
    $routes->put('basic-costs/(:num)', 'BasicCostController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('basic-costs/(:num)', 'BasicCostController::delete/$1', ['filter' => 'api-auth']);
    $routes->options('basic-costs/(:num)/submit', 'BasicCostController::submit/$1');
    $routes->post('basic-costs/(:num)/submit', 'BasicCostController::submit/$1', ['filter' => 'api-auth']);
    $routes->options('basic-costs/(:num)/request-revision', 'BasicCostController::requestRevision/$1');
    $routes->post('basic-costs/(:num)/request-revision', 'BasicCostController::requestRevision/$1', ['filter' => 'api-auth']);
    $routes->options('basic-costs/(:num)/approve', 'BasicCostController::approve/$1');
    $routes->post('basic-costs/(:num)/approve', 'BasicCostController::approve/$1', ['filter' => 'api-auth']);
    $routes->options('scheduler', 'SchedulerController::index');
    $routes->match(['get', 'post', 'delete'], 'scheduler', 'SchedulerController::index', ['filter' => 'api-auth']);
    $routes->options('cost-proposals', 'CostProposalController::index');
    $routes->get('cost-proposals', 'CostProposalController::index', ['filter' => 'api-auth']);
    $routes->post('cost-proposals', 'CostProposalController::store', ['filter' => 'api-auth']);
    $routes->options('cost-proposals/(:num)', 'CostProposalController::show/$1');
    $routes->get('cost-proposals/(:num)', 'CostProposalController::show/$1', ['filter' => 'api-auth']);
    $routes->put('cost-proposals/(:num)', 'CostProposalController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('cost-proposals/(:num)', 'CostProposalController::delete/$1', ['filter' => 'api-auth']);
    $routes->options('cost-proposals/(:num)/submit', 'CostProposalController::submit/$1');
    $routes->post('cost-proposals/(:num)/submit', 'CostProposalController::submit/$1', ['filter' => 'api-auth']);
    $routes->options('cost-proposals/(:num)/request-revision', 'CostProposalController::requestRevision/$1');
    $routes->post('cost-proposals/(:num)/request-revision', 'CostProposalController::requestRevision/$1', ['filter' => 'api-auth']);
    $routes->options('cost-proposals/(:num)/approve', 'CostProposalController::approve/$1');
    $routes->post('cost-proposals/(:num)/approve', 'CostProposalController::approve/$1', ['filter' => 'api-auth']);
    $routes->options('cost-proposals/(:num)/issue', 'CostProposalController::issue/$1');
    $routes->post('cost-proposals/(:num)/issue', 'CostProposalController::issue/$1', ['filter' => 'api-auth']);
    $routes->options('public/cost-proposals/(:segment)', 'CostProposalController::publicShow/$1');
    $routes->get('public/cost-proposals/(:segment)', 'CostProposalController::publicShow/$1');
    $routes->options('templates', 'TemplateController::index');
    $routes->get('templates', 'TemplateController::index', ['filter' => 'api-auth']);
    $routes->post('templates', 'TemplateController::store', ['filter' => 'api-auth']);
    $routes->options('templates/(:num)', 'TemplateController::show/$1');
    $routes->get('templates/(:num)', 'TemplateController::show/$1', ['filter' => 'api-auth']);
    $routes->put('templates/(:num)', 'TemplateController::update/$1', ['filter' => 'api-auth']);
    $routes->delete('templates/(:num)', 'TemplateController::delete/$1', ['filter' => 'api-auth']);
});

$routes->group('', ['namespace' => 'App\Controllers\Auth'], static function ($routes): void {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::attemptLogin');
    $routes->get('logout', 'AuthController::logout', ['filter' => 'auth']);
});

$routes->group('', ['namespace' => 'App\Controllers\Dashboard', 'filter' => 'auth'], static function ($routes): void {
    $routes->get('dashboard', 'DashboardController::index');
});

$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => 'role:admin'], static function ($routes): void {
    $routes->get('users', 'UserManagementController::index');
    $routes->get('users/create', 'UserManagementController::create');
    $routes->post('users', 'UserManagementController::store');
    $routes->get('users/(:num)/edit', 'UserManagementController::edit/$1');
    $routes->post('users/(:num)/update', 'UserManagementController::update/$1');
    $routes->post('users/(:num)/toggle-status', 'UserManagementController::toggleStatus/$1');
    $routes->post('users/(:num)/delete', 'UserManagementController::delete/$1');
    $routes->get('audit-logs', 'AuditLogController::index');
});

$routes->group('manager', ['namespace' => 'App\Controllers\Manager', 'filter' => 'role:admin,manager'], static function ($routes): void {
    $routes->get('approvals', 'ApprovalController::index');
});

$routes->group('staff', ['namespace' => 'App\Controllers\Staff', 'filter' => 'role:admin,manager,staff'], static function ($routes): void {
    $routes->get('projects', 'ProjectController::index');
});
