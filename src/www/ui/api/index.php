<?php
/***************************************************************
 Copyright (C) 2017-2018 Siemens AG

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
use Fossology\UI\Api\Controllers\FolderController;
use Fossology\UI\Api\Controllers\JobController;
use Fossology\UI\Api\Controllers\ReportController;
use Fossology\UI\Api\Controllers\SearchController;
use Fossology\UI\Api\Controllers\UploadController;
use Fossology\UI\Api\Controllers\UserController;
use Fossology\UI\Api\Middlewares\RestAuthHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Slim\App;

const REST_VERSION_SLUG = "restVersion";

const VERSION_1   = "/v{" . REST_VERSION_SLUG . ":1}/";

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

$timingLogger->tic();
plugin_load();
plugin_preinstall();
plugin_postinstall();
$timingLogger->toc("setup plugins");

$app = new App($GLOBALS['container']);

// Middleware for authentication
$app->add(new RestAuthHelper());

//////////////////////////AUTH/////////////////////
$app->get(VERSION_1 . 'auth', AuthController::class . ':getAuthHeaders');

//////////////////////////UPLOADS/////////////////////
$app->group(VERSION_1 . 'uploads',
  function (){
    $this->get('[/{id:\\d+}]', UploadController::class . ':getUploads');
    $this->delete('/{id:\\d+}', UploadController::class . ':deleteUpload');
    $this->patch('/{id:\\d+}', UploadController::class . ':moveUpload');
    $this->put('/{id:\\d+}', UploadController::class . ':copyUpload');
    $this->post('', UploadController::class . ':postUpload');
    $this->any('/{params:.*}', BadRequestController::class);
  });


////////////////////////////ADMIN-USERS/////////////////////
$app->group(VERSION_1 . 'users',
  function (){
    $this->get('[/{id:\\d+}]', UserController::class . ':getUsers');
    $this->delete('/{id:\\d+}', UserController::class . ':deleteUser');
    $this->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////JOBS/////////////////////
$app->group(VERSION_1 . 'jobs',
  function (){
    $this->get('[/{id:\\d+}]', JobController::class . ':getJobs');
    $this->post('', JobController::class . ':createJob');
    $this->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////SEARCH/////////////////////
$app->group(VERSION_1 . 'search',
  function (){
    $this->get('', SearchController::class . ':performSearch');
  });

////////////////////////////FOLDER/////////////////////
$app->group(VERSION_1 . 'folders',
  function (){
    $this->get('[/{id:\\d+}]', FolderController::class . ':getFolders');
    $this->post('', FolderController::class . ':createFolder');
    $this->delete('/{id:\\d+}', FolderController::class . ':deleteFolder');
    $this->patch('/{id:\\d+}', FolderController::class . ':editFolder');
    $this->put('/{id:\\d+}', FolderController::class . ':copyFolder');
    $this->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////REPORT/////////////////////
$app->group(VERSION_1 . 'report',
  function (){
    $this->get('', ReportController::class . ':getReport');
    $this->get('/{id:\\d+}', ReportController::class . ':downloadReport');
    $this->any('/{params:.*}', BadRequestController::class);
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
  function ($request, $response, $error){
    $GLOBALS['container']->get('logger')->error($error);
    $error = new Info(500, "Something went wrong! Please try again later.",
      InfoType::ERROR);
    return $response->withJson($error->getArray(), $error->getCode());
  }
);
$GLOBALS['container']->setAlias('errorHandler', 'phpErrorHandler');

$app->run();
plugin_unload();

$GLOBALS['container']->get("db.manager")->flushStats();
return 0;

