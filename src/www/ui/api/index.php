<?php
/***************************************************************
 Copyright (C) 2017 Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @dir
 * @brief REST api for FOSSology
 * @file
 * @brief Provides router for REST api requests
 */
namespace Fossology\UI\Api;

$GLOBALS['apiCall'] = true;

// setup autoloading
require_once dirname(dirname(dirname(__DIR__))) . "/vendor/autoload.php";
require_once dirname(dirname(dirname(dirname(__FILE__)))) .
  "/lib/php/bootstrap.php";

use Fossology\UI\Api\Controllers\AuthController;
use Fossology\UI\Api\Controllers\BadRequestController;
use Fossology\UI\Api\Controllers\JobController;
use Fossology\UI\Api\Controllers\SearchController;
use Fossology\UI\Api\Controllers\UploadController;
use Fossology\UI\Api\Controllers\UserController;
use Fossology\UI\Api\Helper\RestAuthHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Slim\App;

const BASE_PATH = "/v1/";

const AUTH_METHOD = "SIMPLE_KEY";

$startTime = microtime(true);

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

global $container;
/** @var TimingLogger $logger */
$timingLogger = $container->get("log.timing");
$timingLogger->logWithStartTime("bootstrap", $startTime);

/* Initialize global system configuration variables $SysConfig[] */
$timingLogger->tic();
ConfigInit($GLOBALS['SYSCONFDIR'], $SysConf);
$timingLogger->toc("setup init");

$app = new App($GLOBALS['container']);

// Middleware for authentication
$app->add(new RestAuthHelper());

//////////////////////////AUTH/////////////////////
$app->get(BASE_PATH . 'auth/', AuthController::class . ':getAuthHeaders');

//////////////////////////UPLOADS/////////////////////
$app->group(BASE_PATH . 'uploads',
  function (){
    $this->get('/[{id:\\d+}]', UploadController::class . ':getUploads');
    $this->delete('/{id:\\d+}', UploadController::class . ':deleteUpload');
    $this->patch('/{id:\\d+}', UploadController::class . ':moveUpload');
    $this->put('/{id:\\d+}', UploadController::class . ':copyUpload');
    $this->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////ADMIN-USERS/////////////////////
$app->group(BASE_PATH . 'users',
  function (){
    $this->get('/[{id:\\d+}]', UserController::class . ':getUsers');
    $this->delete('/{id:\\d+}', UserController::class . ':deleteUser');
    $this->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////JOBS/////////////////////
$app->group(BASE_PATH . 'jobs',
  function (){
    $this->get('/[{id:\\d+}]', JobController::class . ':getJobs');
    $this->post('/', JobController::class . ':createJob')
      ->add(
      function ($request, $response, $next){
        $response = $next($request, $response);
        plugin_unload();
        return $response;
      });
    $this->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////SEARCH/////////////////////
$app->group(BASE_PATH . 'search',
  function (){
    $this->get('/', SearchController::class . ':performSearch');
  });

//////////////////////////ERROR-HANDLERS/////////////////////
$slimContainer = $app->getContainer();
$slimContainer->set('notFoundHandler',
  function ($request, $response){
    $error = new Info(404, "Resource not found", InfoType::ERROR);
    return $response->withJson($error->getArray(), $error->getCode());
  }
);
$slimContainer->set('notAllowedHandler',
  function ($request, $response, $methods) {
    $error = new Info(405, 'Method must be one of: ' . implode(', ', $methods),
      InfoType::ERROR);
    return $response->withHeader('Allow', implode(', ', $methods))
      ->withJson($error->getArray(), $error->getCode());
  }
);
$slimContainer->set('phpErrorHandler',
  function ($request, $response){
    $error = new Info(500, "Something went wrong! Please try again later",
      InfoType::ERROR);
    return $response->withJson($error->getArray(), $error->getCode());
  }
);

$app->run();

