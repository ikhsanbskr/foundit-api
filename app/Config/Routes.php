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
    $routes->get('my-posts', 'Api\ReportController::getMyPosts');
    $routes->get('matches/(:any)', 'Api\ReportController::getMatches/$1');
    $routes->get('ticket/(:segment)', 'Api\ReportController::getReportByTicket/$1');
    $routes->get('(:num)', 'Api\ReportController::getReport/$1');
    $routes->delete('(:num)', 'Api\ReportController::deletePost/$1');
    $routes->post('(:num)', 'Api\ReportController::updateReport/$1'); // Using POST for file uploads
  });

  // Officer
  $routes->group('officer', function ($routes) {
    $routes->get('dashboard', 'Api\OfficerController::getDashboardStats');
    $routes->get('inventory', 'Api\OfficerController::getInventory');
    $routes->get('compare-claim', 'Api\OfficerController::compareClaim');
    $routes->post('resolve-claim', 'Api\OfficerController::resolveClaim');
    $routes->post('secure-item/(:num)', 'Api\OfficerController::secureItem/$1');
    $routes->post('update-status/(:num)', 'Api\OfficerController::updateStatus/$1');
  });
});
