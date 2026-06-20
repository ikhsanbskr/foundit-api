<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api', function ($routes) {
  $routes->post('login', 'Api\AuthController::login');

  // Master Data
  $routes->group('master', function ($routes) {
    $routes->get('categories', 'Api\MasterDataController::getCategories');
    $routes->get('categories/details/(:num)', 'Api\MasterDataController::getCategoryDetails/$1');
    $routes->get('banks', 'Api\MasterDataController::getBanks');
  }); // <-- Added this closing brace

  // Reports
  $routes->group('reports', function ($routes) {
    $routes->post('found', 'Api\ReportController::submitFound');
    $routes->get('found', 'Api\ReportController::getFoundReports');
  });
});
