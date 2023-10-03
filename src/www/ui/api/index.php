<?php
/*
 SPDX-FileCopyrightText: © 2017-2018,2021 Siemens AG
 SPDX-FileCopyrightText: © 2021 Orange by Piotr Pszczola <piotr.pszczola@orange.com>
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
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

use Fossology\Lib\Util\TimingLogger;
use Fossology\UI\Api\Controllers\AuthController;
use Fossology\UI\Api\Controllers\BadRequestController;
use Fossology\UI\Api\Controllers\FileSearchController;
use Fossology\UI\Api\Controllers\FolderController;
use Fossology\UI\Api\Controllers\GroupController;
use Fossology\UI\Api\Controllers\InfoController;
use Fossology\UI\Api\Controllers\FileInfoController;
use Fossology\UI\Api\Controllers\JobController;
use Fossology\UI\Api\Controllers\CopyrightController;
use Fossology\UI\Api\Controllers\LicenseController;
use Fossology\UI\Api\Controllers\MaintenanceController;
use Fossology\UI\Api\Controllers\OverviewController;
use Fossology\UI\Api\Controllers\ObligationController;
use Fossology\UI\Api\Controllers\ReportController;
use Fossology\UI\Api\Controllers\SearchController;
use Fossology\UI\Api\Controllers\ConfController;
use Fossology\UI\Api\Controllers\UploadController;
use Fossology\UI\Api\Controllers\UploadTreeController;
use Fossology\UI\Api\Controllers\UserController;
use Fossology\UI\Api\Controllers\CustomiseController;
use Fossology\UI\Api\Helper\ResponseFactoryHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Middlewares\FossologyInitMiddleware;
use Fossology\UI\Api\Middlewares\RestAuthMiddleware;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Throwable;

// Extracts the version from the URL
function getVersionFromUri ($uri)
{
  $matches = [];
  preg_match('/\/repo\/api\/v(\d+)/', $uri, $matches);
  return isset($matches[1]) ? intval($matches[1]) : null;
}

// Determine the API version based on the URL
$requestedVersion = isset($_SERVER['REQUEST_URI']) ? getVersionFromUri($_SERVER['REQUEST_URI']) : null;
$apiVersion = in_array($requestedVersion, [ApiVersion::V1, ApiVersion::V2]) ? $requestedVersion : ApiVersion::V1; // Default to "1"

// Construct the base path
$BASE_PATH = "/repo/api/v" .$apiVersion;

const AUTH_METHOD = "JWT_TOKEN";

$GLOBALS['apiBasePath'] = $BASE_PATH;

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
$error = ConfigInit($GLOBALS['SYSCONFDIR'], $SysConf, false);

$dbConnected = true;
if ($error === -1) {
  $dbConnected = false;
}

$timingLogger->toc("setup init");

$timingLogger->tic();
if ($dbConnected) {
  plugin_load();
}

AppFactory::setContainer($container);
AppFactory::setResponseFactory(new ResponseFactoryHelper());
$app = AppFactory::create();
$app->setBasePath($BASE_PATH);

// Custom middleware to set the API version as a request attribute
$apiVersionMiddleware = function (Request $request, $handler) use ($apiVersion) {
  $request = $request->withAttribute('apiVersion', $apiVersion);
  return $handler->handle($request);
};

/*./
 * To check the order of middlewares, refer
 * https://www.slimframework.com/docs/v4/concepts/middleware.html
 *
 * FOSSology Init is the first middleware and Rest Auth is second.
 *
 * 1. The call enters from Rest Auth and initialize session variables.
 * 2. It then goes to FOSSology Init and initialize all plugins
 * 3. The normal flow continues.
 * 4. The call now enters FOSSology Init again and plugins are unloaded.
 * 5. The call then enters Rest Auth and leaves as is.
 * 6. Added ApiVersion middleware to set 'apiVersion' attribute in request.
 */
if ($dbConnected) {
  // Middleware for plugin initialization
  $app->add(new FossologyInitMiddleware());
  // Middleware for authentication
  $app->add(new RestAuthMiddleware());
  // Content length middleware
  $app->add(new ContentLengthMiddleware());
  // Api version middleware
  $app->add($apiVersionMiddleware);
} else {
  // DB not connected
  // Respond to health request as expected
  $app->get('/health', function($req, $res) {
    $handler = new InfoController($GLOBALS['container']);
    return $handler->getHealth($req, $res, -1);
  });
  // Handle any other request and respond explicitly
  $app->any('{route:.*}', function(ServerRequestInterface $req, ResponseHelper $res) {
    $error = new Info(503, "Unable to connect to DB.", InfoType::ERROR);
    return $res->withJson($error->getArray(), $error->getCode());
  });

  // Prevent further actions and exit
  $app->run();
  return 0;
}

//////////////////////////OPTIONS/////////////////////
$app->options('/{routes:.+}', AuthController::class . ':optionsVerification');

//////////////////////////AUTH/////////////////////
$app->post('/tokens', AuthController::class . ':createNewJwtToken');

//////////////////////////UPLOADS/////////////////////
$app->group('/uploads',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('[/{id:\\d+}]', UploadController::class . ':getUploads');
    $app->delete('/{id:\\d+}', UploadController::class . ':deleteUpload');
    $app->patch('/{id:\\d+}', UploadController::class . ':updateUpload');
    $app->put('/{id:\\d+}', UploadController::class . ':moveUpload');
    $app->post('', UploadController::class . ':postUpload');
    $app->put('/{id:\\d+}/permissions', UploadController::class . ':setUploadPermissions');
    $app->get('/{id:\\d+}/perm-groups', UploadController::class . ':getGroupsWithPermissions');
    $app->get('/{id:\\d+}/summary', UploadController::class . ':getUploadSummary');
    $app->get('/{id:\\d+}/licenses', UploadController::class . ':getUploadLicenses');
    $app->get('/{id:\\d+}/licenses/histogram', UploadController::class . ':getLicensesHistogram');
    $app->get('/{id:\\d+}/agents', UploadController::class . ':getAllAgents');
    $app->get('/{id:\\d+}/licenses/edited', UploadController::class . ':getEditedLicenses');
    $app->get('/{id:\\d+}/licenses/reuse', UploadController::class . ':getReuseReportSummary');
    $app->get('/{id:\\d+}/licenses/scanned', UploadController::class . ':getScannedLicenses');
    $app->get('/{id:\\d+}/agents/revision', UploadController::class . ':getAgentsRevision');
    $app->put('/{id:\\d+}/item/{itemId:\\d+}/licenses', UploadTreeController::class . ':handleAddEditAndDeleteLicenseDecision');
    $app->get('/{id:\\d+}/download', UploadController::class . ':uploadDownload');
    $app->get('/{id:\\d+}/copyrights', UploadController::class . ':getUploadCopyrights');
    $app->get('/{id:\\d+}/clearing-progress', UploadController::class . ':getClearingProgressInfo');
    $app->get('/{id:\\d+}/licenses/main', UploadController::class . ':getMainLicenses');
    $app->post('/{id:\\d+}/licenses/main', UploadController::class . ':setMainLicense');
    $app->delete('/{id:\\d+}/licenses/{shortName:[\\w\\- \\.]+}/main', UploadController::class . ':removeMainLicense');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/view', UploadTreeController::class. ':viewLicenseFile');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/prev-next', UploadTreeController::class . ':getNextPreviousItem');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/licenses', UploadTreeController::class . ':getLicenseDecisions');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/copyrights', CopyrightController::class . ':getFileCopyrights');
    $app->delete('/{id:\\d+}/item/{itemId:\\d+}/copyrights/{hash:.*}', CopyrightController::class . ':deleteFileCopyrights');
    $app->put('/{id:\\d+}/item/{itemId:\\d+}/copyrights/{hash:.*}', CopyrightController::class . ':updateFileCopyrights');
    $app->put('/{id:\\d+}/item/{itemId}/clearing-decision', UploadTreeController::class . ':setClearingDecision');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/bulk-history', UploadTreeController::class . ':getBulkHistory');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/clearing-history', UploadTreeController::class . ':getClearingHistory');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/highlight', UploadTreeController::class . ':getHighlightEntries');
    $app->patch('/{id:\\d+}/item/{itemId:\\d+}/copyrights/{hash:.*}', CopyrightController::class . ':restoreFileCopyrights');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/totalcopyrights', CopyrightController::class . ':getTotalFileCopyrights');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/tree/view', UploadTreeController::class . ':getTreeView');
    $app->get('/{id:\\d+}/item/{itemId:\\d+}/info', FileInfoController::class . ':getItemInfo');
    $app->post('/{id:\\d+}/item/{itemId:\\d+}/bulk-scan', UploadTreeController::class . ':scheduleBulkScan');
    $app->get('/{id:\\d+}/conf', ConfController::class . ':getConfInfo');
    $app->put('/{id:\\d+}/conf', ConfController::class . ':updateConfData');
    $app->any('/{params:.*}', BadRequestController::class);
  });


////////////////////////////ADMIN-USERS/////////////////////
$app->group('/users',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('[/{id:\\d+}]', UserController::class . ':getUsers');
    $app->put('[/{id:\\d+}]', UserController::class . ':updateUser');
    $app->post('', UserController::class . ':addUser');
    $app->delete('/{id:\\d+}', UserController::class . ':deleteUser');
    $app->get('/self', UserController::class . ':getCurrentUser');
    $app->post('/tokens', UserController::class . ':createRestApiToken');
    $app->get('/tokens/{type:\\w+}', UserController::class . ':getTokens');
    $app->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////OBLIGATIONS/////////////////////
$app->group('/obligations',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('/list', ObligationController::class . ':obligationsList');
    $app->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////GROUPS/////////////////////
$app->group('/groups',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', GroupController::class . ':getGroups');
    $app->post('', GroupController::class . ':createGroup');
    $app->post('/{id:\\d+}/user/{userId:\\d+}', GroupController::class . ':addMember');
    $app->delete('/{id:\\d+}', GroupController::class . ':deleteGroup');
    $app->delete('/{id:\\d+}/user/{userId:\\d+}', GroupController::class . ':deleteGroupMember');
    $app->get('/deletable', GroupController::class . ':getDeletableGroups');
    $app->get('/{id:\\d+}/members', GroupController::class . ':getGroupMembers');
    $app->put('/{id:\\d+}/user/{userId:\\d+}', GroupController::class . ':changeUserPermission');
    $app->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////JOBS/////////////////////
$app->group('/jobs',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('[/{id:\\d+}]', JobController::class . ':getJobs');
    $app->get('/all', JobController::class . ':getAllJobs');
    $app->get('/dashboard/statistics', JobController::class . ':getJobStatistics');
    $app->get('/scheduler/operation/{operationName:[\\w\\- \\.]+}', JobController::class . ':getSchedulerJobOptionsByOperation');
    $app->post('/scheduler/operation/run', JobController::class . ':handleRunSchedulerOption');
    $app->post('', JobController::class . ':createJob');
    $app->get('/history', JobController::class . ':getJobsHistory');
    $app->get('/dashboard', JobController::class . ':getAllServerJobsStatus');
    $app->delete('/{id:\\d+}/{queue:\\d+}', JobController::class . ':deleteJob');
    $app->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////SEARCH/////////////////////
$app->group('/search',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', SearchController::class . ':performSearch');
  });

////////////////////////////MAINTENANCE/////////////////////
$app->group('/maintenance',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->post('', MaintenanceController::class . ':createMaintenance');
    $app->any('/{params:.*}', BadRequestController::class);
  });


////////////////////////////FOLDER/////////////////////
$app->group('/folders',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('[/{id:\\d+}]', FolderController::class . ':getFolders');
    $app->post('', FolderController::class . ':createFolder');
    $app->delete('/{id:\\d+}', FolderController::class . ':deleteFolder');
    $app->patch('/{id:\\d+}', FolderController::class . ':editFolder');
    $app->put('/{id:\\d+}', FolderController::class . ':copyFolder');
    $app->get('/{id:\\d+}/contents/unlinkable', FolderController::class . ':getUnlinkableFolderContents');
    $app->put('/contents/{contentId:\\d+}/unlink', FolderController::class . ':unlinkFolder');
    $app->get('/{id:\\d+}/contents', FolderController::class . ':getAllFolderContents');
    $app->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////REPORT/////////////////////
$app->group('/report',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', ReportController::class . ':getReport');
    $app->get('/{id:\\d+}', ReportController::class . ':downloadReport');
    $app->post('/import', ReportController::class . ':importReport');
    $app->any('/{params:.*}', BadRequestController::class);
  });

/////////////////////////CUSTOMISE////////////////////
$app->group('/customise',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', CustomiseController::class . ':getCustomiseData');
    $app->put('', CustomiseController::class . ':updateCustomiseData');
    $app->get('/banner', CustomiseController::class . ':getBannerMessage');
    $app->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////INFO/////////////////////
$app->group('/version',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', InfoController::class . ':getInfo');
  });
$app->group('/info',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', InfoController::class . ':getInfo');
  });
$app->group('/health',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', InfoController::class . ':getHealth');
  });
$app->group('/openapi',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', InfoController::class . ':getOpenApi');
  });

/////////////////////////FILE SEARCH////////////////////
$app->group('/filesearch',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->post('', FileSearchController::class . ':getFiles');
    $app->any('/{params:.*}', BadRequestController::class);
  });

/////////////////////////LICENSE SEARCH/////////////////
$app->group('/license',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('', LicenseController::class . ':getAllLicenses');
    $app->post('/import-csv', LicenseController::class . ':handleImportLicense');
    $app->get('/export-csv', LicenseController::class . ':exportAdminLicenseToCSV');
    $app->post('', LicenseController::class . ':createLicense');
    $app->put('/verify/{shortname:.+}', LicenseController::class . ':verifyLicense');
    $app->put('/merge/{shortname:.+}', LicenseController::class . ':mergeLicense');
    $app->get('/admincandidates', LicenseController::class . ':getCandidates');
    $app->get('/adminacknowledgements', LicenseController::class . ':getAllAdminAcknowledgements');
    $app->get('/stdcomments', LicenseController::class . ':getAllLicenseStandardComments');
    $app->put('/stdcomments', LicenseController::class . ':handleLicenseStandardComment');
    $app->post('/suggest', LicenseController::class . ':getSuggestedLicense');
    $app->get('/{shortname:.+}', LicenseController::class . ':getLicense');
    $app->patch('/{shortname:.+}', LicenseController::class . ':updateLicense');
    $app->delete('/admincandidates/{id:\\d+}',
      LicenseController::class . ':deleteAdminLicenseCandidate');
    $app->put('/adminacknowledgements', LicenseController::class . ':handleAdminLicenseAcknowledgement');
    $app->any('/{params:.*}', BadRequestController::class);
  });

////////////////////////////OVERVIEW/////////////////////
$app->group('/overview',
  function (\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('/database/contents', OverviewController::class . ':getDatabaseContents');
    $app->get('/disk/usage', OverviewController::class . ':getDiskSpaceUsage');
    $app->get('/info/php', OverviewController::class . ':getPhpInfo');
    $app->get('/database/metrics', OverviewController::class . ':getDatabaseMetrics');
    $app->get('/queries/active', OverviewController::class . ':getActiveQueries');
    $app->any('/{params:.*}', BadRequestController::class);
  });

/////////////////////ERROR HANDLING/////////////////
// Define Custom Error Handler
$customErrorHandler = function (
  ServerRequestInterface $request,
  Throwable $exception,
  bool $displayErrorDetails,
  bool $logErrors,
  bool $logErrorDetails,
  ?LoggerInterface $logger = null
) use ($app) {
  if ($logger === null) {
    $logger = $app->getContainer()->get('logger');
  }
  if ($logErrors) {
    $logger->error($exception->getMessage(), $exception->getTrace());
  }
  if ($displayErrorDetails) {
    $payload = ['error'=> $exception->getMessage(),
      'trace' => $exception->getTraceAsString()];
  } else {
    $error = new Info(500, "Something went wrong! Please try again later.",
      InfoType::ERROR);
    $payload = $error->getArray();
  }

  $response = $app->getResponseFactory()->createResponse(500)
    ->withHeader("Content-Type", "application/json");
  $response->getBody()->write(
      json_encode($payload, JSON_UNESCAPED_UNICODE)
  );

  return $response;
};

$errorMiddleware = $app->addErrorMiddleware(false, true, true,
  $container->get("logger"));
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

// Catch all routes
$errorMiddleware->setErrorHandler(
  HttpNotFoundException::class,
  function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
      $response = new ResponseHelper();
      $error = new Info(404, "Resource not found", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
  });

// Set the Not Allowed Handler
$errorMiddleware->setErrorHandler(
  HttpMethodNotAllowedException::class,
  function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
      $response = new Response();
      $response->getBody()->write('405 NOT ALLOWED');

      return $response->withStatus(405);
  });

$app->run();

$GLOBALS['container']->get("db.manager")->flushStats();
return 0;
