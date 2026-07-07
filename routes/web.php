<?php

/** @var App\Core\Router $router */

use App\Controller\ActivityLogController;
use App\Controller\AuthController;
use App\Controller\HomeController;
use App\Controller\ProfileController;
use App\Controller\SessionController;
use App\Controller\UserController;

$router->get('/',                 [HomeController::class,  'index']);

$router->get('/login',            [AuthController::class,  'login']);
$router->post('/login',           [AuthController::class,  'login']);
$router->post('/logout',          [AuthController::class,  'logout']);
$router->get('/forgot-password',  [AuthController::class,  'forgotPassword']);
$router->post('/forgot-password', [AuthController::class,  'forgotPassword']);
$router->get('/reset-password',   [AuthController::class,  'resetPassword']);
$router->post('/reset-password',  [AuthController::class,  'resetPassword']);

$router->get('/profile',           [ProfileController::class, 'profile']);
$router->post('/profile',          [ProfileController::class, 'profile']);
$router->post('/profile/password', [ProfileController::class, 'changePassword']);

$router->get('/activity-logs/data', [ActivityLogController::class, 'data']);
$router->get('/activity-logs',      [ActivityLogController::class, 'index']);

$router->get('/sessions',                [SessionController::class, 'index']);
$router->post('/sessions/revoke',        [SessionController::class, 'revoke']);
$router->post('/sessions/revoke-others', [SessionController::class, 'revokeOthers']);

$router->get('/users',            [UserController::class,  'index']);
$router->get('/users/create',     [UserController::class,  'create']);
$router->post('/users/create',    [UserController::class,  'create']);
$router->get('/users/edit',       [UserController::class,  'edit']);
$router->post('/users/edit',      [UserController::class,  'edit']);
$router->post('/users/delete',    [UserController::class,  'delete']);
