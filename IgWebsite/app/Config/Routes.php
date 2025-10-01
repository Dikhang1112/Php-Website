<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
// Home
$routes->get('/', 'HomeController::index');
$routes->get('home', 'HomeController::home', ['filter' => 'client']);

// API: /api/users
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    $routes->get('users', 'ApiUserController::index');
});

// Web: /users
$routes->group('users', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->get('', 'UserController::showUsers', ['filter' => 'admin']); // cần alias 'admin'
    $routes->post('add', 'UserController::addUser');
});

// Login
$routes->group('login', function ($routes) {
    $routes->get('', 'ActionController::showLogin');
    $routes->post('', 'ActionController::submit');
});

// Verify & Logout
$routes->get('verify-email', 'VerifiedMailController::verify');
$routes->post('verify-email/resend', 'VerifiedMailController::resendEmail');
$routes->get('logout', 'ActionController::logout');

//Log open
$routes->group('log', function ($routes) {
    $routes->get('open/(:any)', 'TrackEmailController::open/$1');
});

//Download
$routes->get('download/(:any)', 'DownloadController::download/$1');
$routes->post('download-email/resend', 'DownloadController::resendDownload');


