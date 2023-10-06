<?php
/*
 SPDX-FileCopyrightText: © 2021 HH Partners
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for licenses
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Application\LicenseCsvExport;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseAcknowledgementDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\LicenseStdCommentDao;
use Fossology\Lib\Exception;
use Fossology\Lib\Util\StringOperation;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\License;
use Fossology\UI\Api\Models\LicenseCandidate;
use Fossology\UI\Api\Models\Obligation;
use Fossology\UI\Page\AdminLicenseCandidate;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Factory\StreamFactory;

/**
 * @class LicenseController
 * @brief Controller for licenses
 */
class LicenseController extends RestController
{
  /**
   * Get header parameter name for page listing
   */
  const PAGE_PARAM = "page";
  /**
   * Get header parameter name for limiting listing
   */
  const LIMIT_PARAM = "limit";
  /**
   * Get header parameter name for active licenses
   */
  const ACTIVE_PARAM = "active";
  /**
   * Limit of licenses in get query
   */
  const LICENSE_FETCH_LIMIT = 100;
  /**
   * @var LicenseDao $licenseDao
   * License Dao object
   */
  private $licenseDao;

  /**
   * @var LicenseAcknowledgementDao $adminLicenseAckDao
   * LicenseAcknowledgementDao object
   */
  private $adminLicenseAckDao;

  /**
   * @var LicenseStdCommentDao $licenseStdCommentDao
   * License Dao object
   */
  private $licenseStdCommentDao;


  /**
   * @param ContainerInterface $container
   */
  public function __construct($container)
  {
    parent::__construct($container);
    $this->licenseDao = $this->container->get('dao.license');
    $this->adminLicenseAckDao = $this->container->get('dao.license.acknowledgement');
    $this->licenseStdCommentDao = $this->container->get('dao.license.stdc');
  }

  /**
   * Get the license information based on the provided parameters
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getLicense($request, $response, $args)
  {
    $shortName = $args["shortname"];

    if (empty($shortName)) {
      $error = new Info(
        400,
        "Short name missing from request.",
        InfoType::ERROR
      );
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $license = $this->licenseDao->getLicenseByShortName($shortName,
      $this->restHelper->getGroupId());

    if ($license === null) {
      $error = new Info(
        404,
        "No license found with short name '{$shortName}'.",
        InfoType::ERROR
      );
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $obligations = $this->licenseDao->getLicenseObligations([$license->getId()],
      false);
    $obligations = array_merge($obligations,
      $this->licenseDao->getLicenseObligations([$license->getId()], true));
    $obligationList = [];
    foreach ($obligations as $obligation) {
      $obligationList[] = new Obligation(
        $obligation['ob_pk'],
        $obligation['ob_topic'],
        $obligation['ob_type'],
        $obligation['ob_text'],
        $obligation['ob_classification'],
        $obligation['ob_comment']
      );
    }

    $returnVal = new License(
      $license->getId(),
      $license->getShortName(),
      $license->getFullName(),
      $license->getText(),
      $license->getUrl(),
      $obligationList,
      $license->getRisk()
    );

    return $response->withJson($returnVal->getArray(), 200);
  }

  /**
   * Get list of all licenses, paginated upon request params
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getAllLicenses($request, $response, $args)
  {
    $info = null;
    $errorHeader = false;
    $query = $request->getQueryParams();
    $limit = $request->getHeaderLine(self::LIMIT_PARAM);
    if (! empty($limit)) {
      $limit = filter_var($limit, FILTER_VALIDATE_INT);
      if ($limit < 1) {
        $info = new Info(400, "limit should be positive integer > 1",
          InfoType::ERROR);
          $limit = self::LICENSE_FETCH_LIMIT;
      }
    } else {
      $limit = self::LICENSE_FETCH_LIMIT;
    }

    $kind = "all";
    if (array_key_exists("kind", $query) && !empty($query["kind"]) &&
      (array_search($query["kind"], ["all", "candidate", "main"]) !== false)) {
        $kind = $query["kind"];
    }

    $totalPages = $this->dbHelper->getLicenseCount($kind,
      $this->restHelper->getGroupId());
    $totalPages = intval(ceil($totalPages / $limit));

    $page = $request->getHeaderLine(self::PAGE_PARAM);
    if (! empty($page) || $page == "0") {
      $page = filter_var($page, FILTER_VALIDATE_INT);
      if ($page <= 0) {
        $info = new Info(400, "page should be positive integer > 0",
          InfoType::ERROR);
      }
      if ($page > $totalPages) {
        $info = new Info(400, "Can not exceed total pages: $totalPages",
          InfoType::ERROR);
        $errorHeader = ["X-Total-Pages", $totalPages];
      }
    } else {
      $page = 1;
    }
    if ($info !== null) {
      $retVal = $response->withJson($info->getArray(), $info->getCode());
      if ($errorHeader) {
        $retVal = $retVal->withHeader($errorHeader[0], $errorHeader[1]);
      }
      return $retVal;
    }
    $onlyActive = $request->getHeaderLine(self::ACTIVE_PARAM);
    if (! empty($onlyActive)) {
      $onlyActive = filter_var($onlyActive, FILTER_VALIDATE_BOOLEAN);
    } else {
      $onlyActive = false;
    }

    $licenses = $this->dbHelper->getLicensesPaginated($page, $limit,
      $kind, $this->restHelper->getGroupId(), $onlyActive);
    $licenseList = [];

    foreach ($licenses as $license) {
      $newRow = new License(
        $license['rf_pk'],
        $license['rf_shortname'],
        $license['rf_fullname'],
        $license['rf_text'],
        $license['rf_url'],
        null,
        $license['rf_risk'],
        $license['group_fk'] != 0
      );
      $licenseList[] = $newRow->getArray();
    }

    return $response->withHeader("X-Total-Pages", $totalPages)
      ->withJson($licenseList, 200);
  }

  /**
   * Create a new license
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function createLicense($request, $response, $args)
  {
    $newLicense = $this->getParsedBody($request);
    $newLicense = License::parseFromArray($newLicense);
    $newInfo = null;
    if ($newLicense === -1) {
      $newInfo = new Info(400, "Input contains additional properties.",
        InfoType::ERROR);
    } elseif ($newLicense === -2) {
      $newInfo = new Info(400, "Property 'shortName' is required.",
        InfoType::ERROR);
    } elseif (! $newLicense->getIsCandidate() && ! Auth::isAdmin()) {
      $newInfo = new Info(403, "Need to be admin to create non-candidate " .
        "license.", InfoType::ERROR);
    }
    if ($newInfo !== null) {
      return $response->withJson($newInfo->getArray(), $newInfo->getCode());
    }
    $tableName = "license_ref";
    $assocData = [
      "rf_shortname" => $newLicense->getShortName(),
      "rf_fullname" => $newLicense->getFullName(),
      "rf_text" => $newLicense->getText(),
      "rf_md5" => md5($newLicense->getText()),
      "rf_risk" => $newLicense->getRisk(),
      "rf_url" => $newLicense->getUrl(),
      "rf_detector_type" => 1
    ];
    $okToAdd = true;
    if ($newLicense->getIsCandidate()) {
      $tableName = "license_candidate";
      $assocData["group_fk"] = $this->restHelper->getGroupId();
      $assocData["rf_user_fk_created"] = $this->restHelper->getUserId();
      $assocData["rf_user_fk_modified"] = $this->restHelper->getUserId();
      $assocData["marydone"] = $newLicense->getMergeRequest();
      $okToAdd = $this->isNewLicense($newLicense->getShortName(),
        $this->restHelper->getGroupId());
    } else {
      $okToAdd = $this->isNewLicense($newLicense->getShortName());
    }
    if (! $okToAdd) {
      $newInfo = new Info(409, "License with shortname '" .
        $newLicense->getShortName() . "' already exists!", InfoType::ERROR);
      return $response->withJson($newInfo->getArray(), $newInfo->getCode());
    }
    try {
      $rfPk = $this->dbHelper->getDbManager()->insertTableRow($tableName,
        $assocData, __METHOD__ . ".newLicense", "rf_pk");
      $newInfo = new Info(201, $rfPk, InfoType::INFO);
    } catch (Exception $e) {
      $newInfo = new Info(409, "License with same text already exists!",
        InfoType::ERROR);
    }
    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }

  /**
   * Update a license
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function updateLicense($request, $response, $args)
  {
    $newParams = $this->getParsedBody($request);
    $shortName = $args["shortname"];
    $newInfo = null;
    if (empty($shortName)) {
      $newInfo = new Info(400, "Short name missing from request.",
        InfoType::ERROR);
      return $response->withJson($newInfo->getArray(), $newInfo->getCode());
    }

    $license = $this->licenseDao->getLicenseByShortName($shortName,
      $this->restHelper->getGroupId());

    if ($license === null) {
      $newInfo = new Info(404, "No license found with short name '$shortName'.",
        InfoType::ERROR);
      return $response->withJson($newInfo->getArray(), $newInfo->getCode());
    }
    $isCandidate = $this->restHelper->getDbHelper()->doesIdExist(
      "license_candidate", "rf_pk", $license->getId());

    $assocData = [];
    if (array_key_exists('fullName', $newParams)) {
      $assocData['rf_fullname'] = StringOperation::replaceUnicodeControlChar($newParams['fullName']);
    }
    if (array_key_exists('text', $newParams)) {
      $assocData['rf_text'] = StringOperation::replaceUnicodeControlChar($newParams['text']);
    }
    if (array_key_exists('url', $newParams)) {
      $assocData['rf_url'] = StringOperation::replaceUnicodeControlChar($newParams['url']);
    }
    if (array_key_exists('risk', $newParams)) {
      $assocData['rf_risk'] = intval($newParams['risk']);
    }
    do {
      if (empty($assocData)) {
        $newInfo = new Info(400, "Empty body sent.", InfoType::ERROR);
        break;
      }
      if ($isCandidate && ! $this->restHelper->getUserDao()->isAdvisorOrAdmin(
          $this->restHelper->getUserId(), $this->restHelper->getGroupId())) {
        $newInfo = new Info(403, "Operation not permitted for this group.",
          InfoType::ERROR);
        break;
      } elseif (!$isCandidate && !Auth::isAdmin()) {
        $newInfo = new Info(403, "Only admin can edit main licenses.",
          InfoType::ERROR);
        break;
      }
      $tableName = "license_ref";
      if ($isCandidate) {
        $tableName = "license_candidate";
      }
      $this->dbHelper->getDbManager()->updateTableRow($tableName, $assocData,
        "rf_pk", $license->getId(), __METHOD__ . ".updateLicense");
      $newInfo = new Info(200, "License " . $license->getShortName() .
        " updated.", InfoType::INFO);
    } while (false);
    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }

  /**
   * Check if the given shortname already exists in DB.
   *
   * @param string  $shortName Shortname to check
   * @param integer $groupId   Group ID if candidate license
   */
  private function isNewLicense($shortName, $groupId = 0)
  {
    $tableName = "ONLY license_ref";
    $where = "";
    $params = [$shortName];
    $statement = __METHOD__;
    if ($groupId != 0) {
      $tableName = "license_candidate";
      $where = "AND group_fk = $2";
      $params[] = $groupId;
      $statement .= ".candidate";
    }
    $sql = "SELECT count(*) cnt FROM " .
      "$tableName WHERE rf_shortname = $1 $where;";
    $result = $this->dbHelper->getDbManager()->getSingleRow($sql, $params,
      $statement);
    return $result["cnt"] == 0;
  }

  /**
   *  Handle the upload of a license
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function handleImportLicense($request, $response, $args)
  {

    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $adminLicenseFromCsv = $this->restHelper->getPlugin('admin_license_from_csv');

    $uploadedFile = $symReq->files->get($adminLicenseFromCsv->getFileInputName(),
      null);

    $requestBody =  $this->getParsedBody($request);
    $delimiter = ',';
    $enclosure = '"';
    if (array_key_exists("delimiter", $requestBody) && !empty($requestBody["delimiter"])) {
         $delimiter = $requestBody["delimiter"];
    }
    if (array_key_exists("enclosure", $requestBody) && !empty($requestBody["enclosure"])) {
        $enclosure = $requestBody["enclosure"];
    }

    $res = $adminLicenseFromCsv->handleFileUpload($uploadedFile,$delimiter,$enclosure);

    $newInfo = new Info($res[2], $res[1], $res[0] == 200 ? InfoType::INFO : InfoType::ERROR);

    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }

  /**
   * Get list of all license candidates, paginated upon request params
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getCandidates($request, $response, $args)
  {
    if (! Auth::isAdmin()) {
      $error = new Info(403, "You are not allowed to access the endpoint.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    /** @var AdminLicenseCandidate $adminLicenseCandidate */
    $adminLicenseCandidate = $this->restHelper->getPlugin("admin_license_candidate");
    $licenses = LicenseCandidate::convertDbArray($adminLicenseCandidate->getCandidateArrayData());
    return $response->withJson($licenses, 200);
  }

  /**
   * Delete license candidate by id.
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function deleteAdminLicenseCandidate($request, $response, $args)
  {
    $resInfo = null;
    if (!Auth::isAdmin()) {
      $resInfo = new Info(403, "Only admin can perform this operation.",
        InfoType::ERROR);
    } else {
      $id = intval($args['id']);
      $adminLicenseCandidate = $this->restHelper->getPlugin('admin_license_candidate');

      if ($adminLicenseCandidate->getDataRow($id)) {
        $res = $adminLicenseCandidate->doDeleteCandidate($id,false);
        $message = $res->getContent();
        $infoType = InfoType::ERROR;
        if ($res->getContent() === 'true') {
          $message = "License candidate will be deleted.";
          $infoType = InfoType::INFO;
          $resCode = 202;
        } else {
          $message = "License used at following locations, can not delete: " .
            $message;
          $resCode = 409;
        }
        $resInfo = new Info($resCode, $message, $infoType);
      } else {
        $resInfo = new Info(404, "License candidate not found.",
          InfoType::ERROR);
      }
    }
    return $response->withJson($resInfo->getArray(), $resInfo->getCode());
  }

  /**
   * Get all admin license acknowledgements
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getAllAdminAcknowledgements($request, $response, $args)
  {
    if (!Auth::isAdmin()) {
      $error = new Info(403, "You are not allowed to access the endpoint.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $res = $this->adminLicenseAckDao->getAllAcknowledgements();

    foreach ($res as $key => $ack) {
      $res[$key]['id'] = intval($ack['la_pk']);
      unset($res[$key]['la_pk']);
      $res[$key]['is_enabled'] = $ack['is_enabled'] == "t";
    }

    return $response->withJson($res, 200);
  }

  /**
   * Add, Edit & toggle admin license acknowledgement.
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function handleAdminLicenseAcknowledgement($request, $response, $args)
  {
    $body = $this->getParsedBody($request);
    $errors = [];
    $success = [];

    if (!isset($body) || empty($body)) {
      $error = new Info(400, "Request body is missing or empty.", InfoType::ERROR);
      $errors[] = $error->getArray();
    } else if (!is_array($body)) {
      $error = new Info(400, "Request body should be an array.", InfoType::ERROR);
      $errors[] = $error->getArray();
    } else {
      foreach (array_keys($body) as $index) {
        $ackReq = $body[$index];
        if ((!$ackReq['update'] && empty($ackReq['name'])) || ($ackReq['update'] && empty($ackReq['name']) && !$ackReq['toggle'])) {
          $error = new Info(400, "Acknowledgement name missing from the request #" . ($index + 1), InfoType::ERROR);
          $errors[] = $error->getArray();
          continue;
        } else if ((!$ackReq['update'] && empty($ackReq['ack'])) || ($ackReq['update'] && empty($ackReq['ack']) && !$ackReq['toggle'])) {
          $error = new Info(400, "Acknowledgement text missing from the request #" . ($index + 1), InfoType::ERROR);
          $errors[] = $error->getArray();
          continue;
        }

        if ($ackReq['update']) {

          if (empty($ackReq['id'])) {
            $error = new Info(400, "Acknowledgement ID missing from the request #" . ($index + 1), InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          }

          $sql = "SELECT la_pk, name FROM license_std_acknowledgement WHERE la_pk = $1;";
          $existingAck = $this->dbHelper->getDbManager()->getSingleRow($sql, [$ackReq['id']]);

          if (empty($existingAck)) {
            $error = new Info(404, "Acknowledgement not found for the request #" . ($index + 1), InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          } else if ($existingAck["name"] != $ackReq["name"] && $this->dbHelper->doesIdExist("license_std_acknowledgement", "name", $ackReq["name"])) {
            $error = new Info(400, "Name already exists.", InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          }

          if ($ackReq["name"] && $ackReq["ack"]) {
            $this->adminLicenseAckDao->updateAcknowledgement($ackReq["id"], $ackReq["name"], $ackReq["ack"]);
          }

          if ($ackReq["toggle"]) {
            $this->adminLicenseAckDao->toggleAcknowledgement($ackReq["id"]);
          }

          $info = new Info(200, "Successfully updated admin license acknowledgement with name '" . $existingAck["name"] . "'", InfoType::INFO);
          $success[] = $info->getArray();
        } else {

          if ($this->dbHelper->doesIdExist("license_std_acknowledgement", "name", $ackReq["name"])) {
            $error = new Info(400, "Name already exists for the request #" . ($index + 1), InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          }
          $res = $this->adminLicenseAckDao->insertAcknowledgement($ackReq["name"], $ackReq["ack"]);
          if ($res == -2) {
            $error = new Info(500, "Error while inserting new acknowledgement.", InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          }
          $info = new Info(201, "Acknowledgement added successfully.", InfoType::INFO);
          $success[] = $info->getArray();
        }
      }
    }
    return $response->withJson([
      'success' => $success,
      'errors' => $errors
    ], 200);
  }

  /**
   * Get all license standard comments
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getAllLicenseStandardComments($request, $response, $args)
  {
    $res = $this->licenseStdCommentDao->getAllComments();
    foreach ($res as $key => $ack) {
      $res[$key]['id'] = intval($ack['lsc_pk']);
      $res[$key]['is_enabled'] = $ack['is_enabled'] == "t";
      unset($res[$key]['lsc_pk']);
    }
    return $response->withJson($res, 200);
  }

  /**
   * Add, Edit & toggle license standard comment.
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function handleLicenseStandardComment($request, $response, $args)
  {
    $body = $this->getParsedBody($request);
    $errors = [];
    $success = [];

    if (!Auth::isAdmin()) {
      $error = new Info(403, "You are not allowed to access the endpoint.", InfoType::ERROR);
      $errors[] = $error->getArray();
    } else if (!isset($body) || empty($body)) {
      $error = new Info(400, "Request body is missing or empty.", InfoType::ERROR);
      $errors[] = $error->getArray();
    } else if (!is_array($body)) {
      $error = new Info(400, "Request body should be an array.", InfoType::ERROR);
      $errors[] = $error->getArray();
    } else {
      foreach (array_keys($body) as $index) {
        $commentReq = $body[$index];

        // Check if name and comment are present if update is false
        if ((!$commentReq['update'] && empty($commentReq['name']))) {
          $error = new Info(400, "Comment name missing from the request #" . ($index + 1), InfoType::ERROR);
          $errors[] = $error->getArray();
          continue;
        } else if ((!$commentReq['update'] && empty($commentReq['comment']))) {
          $error = new Info(400, "Comment text missing from the request #" . ($index + 1), InfoType::ERROR);
          $errors[] = $error->getArray();
          continue;
        } else if ($commentReq['update'] && empty($commentReq['name']) && empty($commentReq['comment']) && empty($commentReq['toggle'])) {
          $error = new Info(400, "Comment name, text or toggle missing from the request #" . ($index + 1), InfoType::ERROR);
          $errors[] = $error->getArray();
          continue;
        }

        if ($commentReq['update']) {

          if (empty($commentReq['id'])) {
            $error = new Info(400, "Standard Comment ID missing from the request #" . ($index + 1), InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          }

          $sql = "SELECT lsc_pk, name, comment FROM license_std_comment WHERE lsc_pk = $1;";
          $existingComment = $this->dbHelper->getDbManager()->getSingleRow($sql, [$commentReq['id']]);

          if (empty($existingComment)) {
            $error = new Info(404, "Standard comment not found for the request #" . ($index + 1), InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
            // check if the new name doesn't already exist
          } else if ($existingComment["name"] != $commentReq["name"] && $this->dbHelper->doesIdExist("license_std_comment", "name", $commentReq["name"])) {
            $error = new Info(400, "Name already exists.", InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          }

          // if both fields were specified and are not empty, update the comment
          if ($commentReq["name"] && $commentReq["comment"]) {
            $this->licenseStdCommentDao->updateComment($commentReq["id"], $commentReq["name"], $commentReq["comment"]);
          } else if ($commentReq["name"]) {
            $this->licenseStdCommentDao->updateComment($commentReq["id"], $commentReq["name"], $existingComment["comment"]);
          } else if ($commentReq["comment"]) {
            $this->licenseStdCommentDao->updateComment($commentReq["id"], $existingComment["name"], $commentReq["comment"]);
          }
          // toggle the comment if the toggle field is set to true
          if ($commentReq["toggle"]) {
            $this->licenseStdCommentDao->toggleComment($commentReq["id"]);
          }

          $info = new Info(200, "Successfully updated standard comment", InfoType::INFO);
          $success[] = $info->getArray();
        } else {

          if ($this->dbHelper->doesIdExist("license_std_comment", "name", $commentReq["name"])) {
            $error = new Info(400, "Name already exists for the request #" . ($index + 1), InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          }
          $res = $this->licenseStdCommentDao->insertComment($commentReq["name"], $commentReq["comment"]);
          if ($res == -2) {
            $error = new Info(500, "Error while inserting new comment.", InfoType::ERROR);
            $errors[] = $error->getArray();
            continue;
          }
          $info = new Info(201, "Comment with name '". $commentReq['name'] ."' added successfully.", InfoType::INFO);
          $success[] = $info->getArray();
        }
      }
    }
    return $response->withJson([
      'success' => $success,
      'errors' => $errors
    ], 200);
  }

  /**
   * Verify the license as new or having a variant
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function verifyLicense($request, $response, $args)
  {
    $licenseShortName = $args["shortname"];
    $body = $this->getParsedBody($request);
    $parentName = $body["parentShortname"];

    if (!Auth::isAdmin()) {
      $resInfo = new Info(403, "Only admin can perform this operation.",
        InfoType::ERROR);
      return $response->withJson($resInfo->getArray(), $resInfo->getCode());
    }
    if (empty($licenseShortName) || empty($parentName)) {
      $error = new Info(400, "License ShortName or Parent ShortName is missing.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $license = $this->licenseDao->getLicenseByShortName($licenseShortName, $this->restHelper->getGroupId());
    if ($licenseShortName != $parentName) {
      $parentLicense = $this->licenseDao->getLicenseByShortName($parentName, $this->restHelper->getGroupId());
    } else {
      $parentLicense = $license;
    }

    if (empty($license) || empty($parentLicense)) {
      $error = new Info(404, "License not found.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }

    try{
      $adminLicenseCandidate = $this->restHelper->getPlugin('admin_license_candidate');
      $ok = $adminLicenseCandidate->verifyCandidate($license->getId(), $licenseShortName, $parentLicense->getId());
    } catch (\Throwable $th) {
      $error = new Info(400, 'The license text already exists.', InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }

    if ($ok) {
      $with = $parentLicense->getId() === $license->getId() ? '' : " as variant of ($parentName).";
      $info = new Info(200, 'Successfully verified candidate ('.$licenseShortName.')'.$with, InfoType::INFO);
    } else {
      $info = new Info(400, 'Short name must be unique', InfoType::ERROR);
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * merge the license
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function mergeLicense($request, $response, $args)
  {
    $licenseShortName = $args["shortname"];
    $body = $this->getParsedBody($request);
    $parentName = $body["parentShortname"];

    if (!Auth::isAdmin()) {
      $error = new Info(403, "Only admin can perform this operation.",
        InfoType::ERROR);
    } else if (empty($licenseShortName) || empty($parentName)) {
      $error = new Info(400, "License ShortName or Parent ShortName is missing.", InfoType::ERROR);
    } else if ($licenseShortName == $parentName) {
      $error = new Info(400, "License ShortName and Parent ShortName are same.", InfoType::ERROR);
    }

    if (isset($error)) {
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $license = $this->licenseDao->getLicenseByShortName($licenseShortName, $this->restHelper->getGroupId());
    $mergeLicense = $this->licenseDao->getLicenseByShortName($parentName, $this->restHelper->getGroupId());

    if (empty($license) || empty($mergeLicense)) {
      $error = new Info(404, "License not found.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $adminLicenseCandidate = $this->restHelper->getPlugin('admin_license_candidate');
    $vars = $adminLicenseCandidate->getDataRow($license->getId());
    if ($vars === false) {
      $error = new Info(404, 'invalid license candidate', InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }

    try {
      $vars['shortname'] = $vars['rf_shortname'];
      $ok = $adminLicenseCandidate->mergeCandidate($license->getId(), $mergeLicense->getId(), $vars);
    } catch (\Throwable $th) {
      $error = new Info(400, 'The license text already exists.', InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }

    if ($ok) {
      $info = new Info(200, "Successfully merged candidate ($parentName) into ($licenseShortName).", InfoType::INFO);
    } else {
      $info = new Info(501, 'Sorry, this feature is not ready yet.', InfoType::ERROR);
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Get suggested license from reference text
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getSuggestedLicense($request, $response, $args)
  {
    $body =  $this->getParsedBody($request);
    $rfText = $body["referenceText"];
    if (!Auth::isAdmin()) {
      $resInfo = new Info(403, "Only admin can perform this operation.",
        InfoType::ERROR);
      return $response->withJson($resInfo->getArray(), $resInfo->getCode());
    }
    if (empty($rfText)) {
      $error = new Info(400, "Reference text is missing.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $adminLicenseCandidate = $this->restHelper->getPlugin('admin_license_candidate');

    list ($suggestIds, $rendered) = $adminLicenseCandidate->suggestLicenseId($rfText, true);

    $highlights = [];

    foreach ($rendered as $value) {
      $highlights[] = $value->getArray();
    }

    if (! empty($suggestIds)) {
      $suggest = $suggestIds[0];
      $suggestLicense = $adminLicenseCandidate->getDataRow($suggest, 'ONLY license_ref');
      $suggestLicense = [
        'id' => intval($suggestLicense['rf_pk']),
        'spdxName' => $suggestLicense['rf_spdx_id'],
        'shortName' => $suggestLicense['rf_shortname'],
        'fullName' => $suggestLicense['rf_fullname'],
        'text' => $suggestLicense['rf_text'],
        'url' => $suggestLicense['rf_url'],
        'notes' => $suggestLicense['rf_notes'],
        'risk' => intval($suggestLicense['rf_risk']),
        'highlights' => $highlights,
      ];
    }
    if (empty($suggestLicense)) {
      $suggestLicense = new \stdClass();
    }
    return $response->withJson($suggestLicense, 200);
  }

  /**
   * Export licenses to CSV file
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function exportAdminLicenseToCSV($request, $response, $args)
  {
    if (!Auth::isAdmin()) {
      $error = new Info(403, "You are not allowed to access the endpoint.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $query = $request->getQueryParams();
    $rf = 0;
    if (array_key_exists('id', $query)) {
      $rf = intval($query['id']);
    }
    if ($rf != 0 &&
        (! $this->dbHelper->doesIdExist("license_ref", "rf_pk", $rf) &&
         ! $this->dbHelper->doesIdExist("license_candidate", "rf_pk", $rf))) {
      $error = new Info(404, "License not found.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $dbManager = $this->dbHelper->getDbManager();
    $licenseCsvExport = new LicenseCsvExport($dbManager);
    $content = $licenseCsvExport->createCsv($rf);
    $fileName = "fossology-license-export-" . date("YMj-Gis");
    $newResponse = $response->withHeader('Content-type', 'text/csv, charset=UTF-8')
      ->withHeader('Content-Disposition', 'attachment; filename=' . $fileName . '.csv')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('Cache-Control', 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0')
      ->withHeader('Expires', 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');
    $sf = new StreamFactory();
    return $newResponse->withBody(
      $content ? $sf->createStream($content) : $sf->createStream('')
    );
  }
}
