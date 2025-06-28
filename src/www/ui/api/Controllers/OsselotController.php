<?php
/*
 SPDX-FileCopyrightText: © 2025 Vaibhav Sahu <sahusv4527@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpServiceUnavailableException;
use Fossology\Lib\Util\OsselotLookupHelper;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class OsselotController
 * @brief Controller for OSSelot REST API endpoints
 */
class OsselotController extends RestController
{
    /**
     * Get curated versions for a package from OSSelot
     *
     * @param object $request PSR-7 request object
     * @param object $response PSR-7 response object
     * @param array $args Route arguments containing package name
     * @return ResponseHelper JSON response with version list or error
     */
  public function getPackageVersions($request, $response, $args): ResponseHelper
  {
      global $SysConf;

    if (empty($SysConf['SYSCONFIG']['EnableOsselotReuse']) ||
          !$SysConf['SYSCONFIG']['EnableOsselotReuse']) {
        $error = new Info(501, "OSSelot integration is disabled", InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
    }

      $package = $args['package'] ?? "";
    if (strlen(trim($package)) == 0) {
        throw new HttpBadRequestException("Missing package name");
    }

    try {
        $helper = new OsselotLookupHelper();
        $versions = $helper->getVersions($package);

      if (empty($versions)) {
          $error = new Info(404, "No curated versions found for '$package'", InfoType::ERROR);
          return $response->withJson($error->getArray(), $error->getCode());
      }

        rsort($versions, SORT_NATURAL);

        return $response->withJson($versions, 200);

    } catch (\Exception $e) {
        $error = new Info(502, "OSSelot unavailable, try later", InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
    }
  }

    /**
     * Import OSSelot SPDX report for an upload
     *
     * @param object $request PSR-7 request object
     * @param object $response PSR-7 response object
     * @param array $args Route arguments containing upload ID
     * @return ResponseHelper JSON response with job info or error
     */
  public function importOsselotReport($request, $response, $args): ResponseHelper
  {
      global $SysConf;

    if (empty($SysConf['SYSCONFIG']['EnableOsselotReuse']) ||
          !$SysConf['SYSCONFIG']['EnableOsselotReuse']) {
        $err = new Info(501, "OSSelot integration is disabled", InfoType::ERROR);
        return $response->withJson($err->getArray(), $err->getCode());
    }

      $uploadId = (int)($args['id'] ?? 0);

    if (!$this->dbHelper->doesIdExist('upload', 'upload_pk', $uploadId)) {
        $err = new Info(404, "Upload does not exist", InfoType::ERROR);
        return $response->withJson($err->getArray(), $err->getCode());
    }

      $body = $this->getParsedBody($request);
      $pkg = $body['package'] ?? null;
      $ver = $body['version'] ?? null;
      $options = $body['options'] ?? null;

    if (!$pkg || !$ver || !is_array($options)) {
        throw new HttpBadRequestException("Missing required fields: package, version, options");
    }
      $requiredOptions = [
          'addLicenseInfoFromInfoInFile',
          'addLicenseInfoFromConcluded',
          'addConcludedAsDecisions',
          'addCopyrights'
      ];

      foreach ($requiredOptions as $optKey) {
        if (!array_key_exists($optKey, $options)) {
            throw new HttpBadRequestException("Option '$optKey' is required");
        }
      }

      $jobDao = $this->restHelper->getJobDao();
      if (method_exists($jobDao, 'hasPendingReportImport') &&
          $jobDao->hasPendingReportImport($uploadId)) {
          $err = new Info(409, "An import job is already in progress for this upload", InfoType::ERROR);
          return $response->withJson($err->getArray(), $err->getCode());
      }

      try {
          $helper = new OsselotLookupHelper();
          $cachedPath = $helper->fetchSpdxFile($pkg, $ver);

        if (!$cachedPath || !is_file($cachedPath) || !is_readable($cachedPath)) {
            $err = new Info(404, "No curated SPDX report found for '$pkg' version '$ver'", InfoType::ERROR);
            return $response->withJson($err->getArray(), $err->getCode());
        }

          $fileBase = $SysConf['FOSSOLOGY']['path'] . "/ReportImport/";
        if (!is_dir($fileBase) && !mkdir($fileBase, 0755, true)) {
            throw new \RuntimeException('Failed to create ReportImport directory');
        }

          $originalName = basename($cachedPath);
        if (!str_ends_with($originalName, '.rdf.xml')) {
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $originalName = str_ends_with($originalName, '.rdf') ?
                $baseName . '.rdf.xml' : $originalName . '.rdf.xml';
        }

          $targetFile = time() . '_' . random_int(0, getrandmax()) . '_osselot_' . $originalName;
          $targetPath = $fileBase . $targetFile;

        if (!copy($cachedPath, $targetPath)) {
            throw new \RuntimeException('Failed to copy SPDX file to target location');
        }

          $reportImportAgent = plugin_find('agent_reportImport');
        if (!$reportImportAgent || !method_exists($reportImportAgent, 'addReport') ||
              !method_exists($reportImportAgent, 'setAdditionalJqCmdArgs') ||
              !method_exists($reportImportAgent, 'AgentAdd')) {
            throw new \RuntimeException('ReportImport agent not available or missing required methods');
        }

          $importRequest = new Request();

          $addNewLicensesAs = $options['addNewLicensesAs'] ?? 'candidate';
        if (!in_array($addNewLicensesAs, ['candidate', 'approved', 'rejected'], true)) {
            $addNewLicensesAs = 'candidate';
        }
          $importRequest->request->set('addNewLicensesAs', $addNewLicensesAs);

          $booleanOptions = [
              'addLicenseInfoFromInfoInFile' => $options['addLicenseInfoFromInfoInFile'],
              'addLicenseInfoFromConcluded' => $options['addLicenseInfoFromConcluded'],
              'addConcludedAsDecisions' => $options['addConcludedAsDecisions'],
              'addConcludedAsDecisionsOverwrite' => $options['addConcludedAsDecisionsOverwrite'] ?? false,
              'addConcludedAsDecisionsTBD' => $options['addConcludedAsDecisionsTBD'] ?? false,
              'addCopyrights' => $options['addCopyrights']
          ];

          foreach ($booleanOptions as $key => $value) {
              $importRequest->request->set($key, $value ? 'true' : 'false');
          }

          $licenseMatch = $options['licenseMatch'] ?? 'spdxid';
          if (!in_array($licenseMatch, ['spdxid', 'name', 'text'], true)) {
              $licenseMatch = 'spdxid';
          }
          $importRequest->request->set('licenseMatch', $licenseMatch);

          $jqCmdArgs = $reportImportAgent->addReport($targetFile);
          $additionalArgs = $reportImportAgent->setAdditionalJqCmdArgs($importRequest);
          $jqCmdArgs .= $additionalArgs;

          $userId = Auth::getUserId();
          $groupId = Auth::getGroupId();

          $jobId = JobAddJob($userId, $groupId, "OSSelot Import", $uploadId);

          $error = "";
          $dependencies = array();
          $jobQueueId = $reportImportAgent->AgentAdd($jobId, $uploadId, $error, $dependencies, $jqCmdArgs);

          if ($jobQueueId < 0) {
            if (file_exists($targetPath)) {
                unlink($targetPath);
            }
              throw new \RuntimeException("Cannot schedule import job: " . $error);
          }

          $info = new Info(202, "Import job scheduled successfully", InfoType::INFO);
          $responseData = $info->getArray();
          $responseData['jobId'] = intval($jobQueueId);

          return $response->withJson($responseData, $info->getCode());

      } catch (\InvalidArgumentException $e) {
          $err = new Info(400, $e->getMessage(), InfoType::ERROR);
          return $response->withJson($err->getArray(), $err->getCode());
      } catch (\RuntimeException $e) {
        if (isset($targetPath) && file_exists($targetPath)) {
            unlink($targetPath);
        }

        if (strpos($e->getMessage(), 'Could not fetch') !== false ||
              strpos($e->getMessage(), 'No curated') !== false) {
            $err = new Info(404, "No curated SPDX report found for '$pkg' version '$ver'", InfoType::ERROR);
        } else {
            $err = new Info(502, "OSSelot service unavailable, try later", InfoType::ERROR);
        }
          return $response->withJson($err->getArray(), $err->getCode());
      } catch (\Exception $e) {
        if (isset($targetPath) && file_exists($targetPath)) {
            unlink($targetPath);
        }

          error_log("OSSelot import error: " . $e->getMessage());
          $err = new Info(502, "OSSelot service unavailable, try later", InfoType::ERROR);
          return $response->withJson($err->getArray(), $err->getCode());
      }
  }
}