<?php
/***************************************************************
 Copyright (C) 2017-2018,2021 Siemens AG
 Copyright (C) 2021 Orange by Piotr Pszczola <piotr.pszczola@orange.com>

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
use Fossology\UI\Api\Controllers\FileSearchController;
use Fossology\UI\Api\Controllers\JobController;
use Fossology\UI\Api\Controllers\ReportController;
use Fossology\UI\Api\Controllers\SearchController;
use Fossology\UI\Api\Controllers\UploadController;
use Fossology\UI\Api\Controllers\UserController;
use Fossology\UI\Api\Controllers\VersionController;
use Fossology\UI\Api\Controllers\LicenseController;
use Fossology\UI\Api\Middlewares\RestAuthMiddleware;
use Fossology\UI\Api\Controllers\GroupController;
use Fossology\UI\Api\Middlewares\FossologyInitMiddleware;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Slim\App;

const REST_VERSION_SLUG = "restVersion";

const VERSION_1   = "/v{" . REST_VERSION_SLUG . ":1}/";

const AUTH_METHOD = "JWT_TOKEN";

$startTime = microtime(true);

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

global $container;
/** @var TimingLogger $logger */
$timingLogger = $container->get("log.timing");
$timingLogger->logWithStartTime("bootstrap", $startTime);

/* Load UI templates */
$loader = $container->get('twig.loader');
$loader->addPath(dirname(dirname(__FILE__)).'/template');

/* Initialize global system configuration variables $SysConfig[] */
$timingLogger->tic();
ConfigInit($GLOBALS['SYSCONFDIR'], $SysConf);
$timingLogger->toc("setup init");

$timingLogger->tic();
plugin_load();

$app = new App($GLOBALS['container']);

/*
 * To check the order of middlewares, refer
 * https://www.slimframework.com/docs/v3/concepts/middleware.html
 *
 * FOSSology Init is the first middleware and Rest Auth is second.
 *
 * 1. The call enters from Rest Auth and initialize session variables.
 * 2. It then goes to FOSSology Init and initialize all plugins
 * 3. The normal flow continues.
 * 4. The call now enteres FOSSology Init again and plugins are unloaded.
 * 5. Then call then enters Rest Auth and leaves as is.
 */

// Middleware for plugin initialization
$app->add(new FossologyInitMiddleware());
// Middleware for authentication
$app->add(new RestAuthMiddleware());

//////////////////////////OPTIONS/////////////////////
$app->options(VERSION_1 . '{routes:.+}', AuthController::class . ':optionsVerification');

//////////////////////////AUTH/////////////////////
$app->get(VERSION_1 . 'auth', AuthController::class . ':getAuthHeaders');
$app->post(VERSION_1 . 'tokens', AuthController::class . ':createNewJwtToken');

//////////////////////////UPLOADS/////////////////////
$app->group(VERSION_1 . 'uploads',
  function (){
    $this->get('[/{id:\\d+}]', UploadController::class . ':getUploads');
    $this->delete('/{id:\\d+}', UploadController::class . ':deleteUpload');
    $this->patch('/{id:\\d+}', UploadController::class . ':moveUpload');
    $this->put('/{id:\\d+}', UploadController::class . ':copyUpload');
    $this->post('', UploadController::class . ':postUpload');
    $this->get('/{id:\\d+}/summary', UploadController::class . ':getUploadSummary');
    $this->get('/{id:\\d+}/licenses', UploadController::class . ':getUploadLicenses');
    $this->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////ADMIN-USERS/////////////////////
$app->group(VERSION_1 . 'users',
  function (){
    $this->get('[/{id:\\d+}]', UserController::class . ':getUsers');
    $this->delete('/{id:\\d+}', UserController::class . ':deleteUser');
    $this->get('/self', UserController::class . ':getCurrentUser');
    $this->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////GROUPS/////////////////////
$app->group(VERSION_1 . 'groups',
function (){
  $this->get('', GroupController::class . ':getGroups');
  $this->post('', GroupController::class . ':createGroup');
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

////////////////////////////VERSION/////////////////////
$app->group(VERSION_1 . 'version',
  function (){
    $this->get('', VersionController::class . ':getVersion');
  });

/////////////////////////FILE SEARCH////////////////////
$app->group(VERSION_1 . 'filesearch',
  function (){
    $this->post('', FileSearchController::class . ':getFiles');
    $this->any('/{params:.*}', BadRequestController::class);
  });

/////////////////////////LICENSE SEARCH/////////////////
$app->group(VERSION_1 . 'license',
  function (){
    $this->get('', LicenseController::class . ':getAllLicenses');
    $this->post('', LicenseController::class . ':createLicense');
    $this->get('/{shortname:.+}', LicenseController::class . ':getLicense');
    $this->patch('/{shortname:.+}', LicenseController::class . ':updateLicense');
    $this->any('/{params:.*}', BadRequestController::class);
  });

// Catch all routes
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function($req, $res) {
  $handler = $this->get('notFoundHandler');
  return $handler($req, $res);
});

$app->run();

$GLOBALS['container']->get("db.manager")->flushStats();
return 0;

