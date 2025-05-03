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
use Fossology\Lib\Util\StringOperation;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\License;
use Fossology\UI\Api\Models\LicenseCandidate;
use Fossology\UI\Api\Models\Obligation;
use Fossology\UI\Api\Models\AdminAcknowledgement;
use Fossology\UI\Api\Models\LicenseStandardComment;
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
   * @throws HttpErrorException
   */
  public function getLicense($request, $response, $args)
  {
    $shortName = $args["shortname"];

    if (empty($shortName)) {
      throw new HttpBadRequestException("Short name missing from request.");
    }

    $license = $this->licenseDao->getLicenseByShortName($shortName,
      $this->restHelper->getGroupId());

    if ($license === null) {
      throw new HttpNotFoundException(
        "No license found with short name '$shortName'.");
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
   * @throws HttpErrorException
   */
  public function getAllLicenses($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $query = $request->getQueryParams();
    if ($apiVersion == ApiVersion::V2) {
      $page = $query[self::PAGE_PARAM] ?? "";
      $limit = $query[self::LIMIT_PARAM] ?? "";
      $onlyActive = $query[self::ACTIVE_PARAM] ?? "";
    } else {
      $page = $request->getHeaderLine(self::PAGE_PARAM);
      $limit = $request->getHeaderLine(self::LIMIT_PARAM);
      $onlyActive = $request->getHeaderLine(self::ACTIVE_PARAM);
    }
    if (! empty($limit)) {
      $limit = filter_var($limit, FILTER_VALIDATE_INT);
      if ($limit < 1) {
        throw new HttpBadRequestException(
          "limit should be positive integer > 1");
      }
    } else {
      $limit = self::LICENSE_FETCH_LIMIT;
    }

    $kind = "all";
    if (array_key_exists("kind", $query) && !empty($query["kind"]) &&
      in_array($query["kind"], ["all", "candidate", "main"])) {
        $kind = $query["kind"];
    }

    $totalPages = $this->dbHelper->getLicenseCount($kind,
      $this->restHelper->getGroupId());
    $totalPages = intval(ceil($totalPages / $limit));

    if (! empty($page) || $page == "0") {
      $page = filter_var($page, FILTER_VALIDATE_INT);
      if ($page <= 0) {
        throw new HttpBadRequestException(
          "page should be positive integer > 0");
      }
      if ($totalPages != 0 && $page > $totalPages) {
        throw (new HttpBadRequestException(
          "Can not exceed total pages: $totalPages"))
          ->setHeaders(["X-Total-Pages" => $totalPages]);
      }
    } else {
      $page = 1;
    }
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
   * @throws HttpErrorException
   */
  public function createLicense($request, $response, $args)
  {
    $newLicense = $this->getParsedBody($request);
    $newLicense = License::parseFromArray($newLicense);
    if ($newLicense === -1) {
      throw new HttpBadRequestException(
        "Input contains additional properties.");
    }
    if ($newLicense === -2) {
      throw new HttpBadRequestException("Property 'shortName' is required.");
    }
    if (! $newLicense->getIsCandidate() && ! Auth::isAdmin()) {
      throw new HttpForbiddenException("Need to be admin to create " .
        "non-candidate license.");
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
      throw new HttpConflictException("License with shortname '" .
        $newLicense->getShortName() . "' already exists!");
    }
    try {
      $rfPk = $this->dbHelper->getDbManager()->insertTableRow($tableName,
        $assocData, __METHOD__ . ".newLicense", "rf_pk");
      $newInfo = new Info(201, $rfPk, InfoType::INFO);
    } catch (\Exception $e) {
      throw new HttpConflictException(
        "License with same text already exists!", $e);
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
   * @throws HttpErrorException
   */
  public function updateLicense($request, $response, $args)
  {
    $newParams = $this->getParsedBody($request);
    $shortName = $args["shortname"];
    if (empty($shortName)) {
      throw new HttpBadRequestException("Short name missing from request.");
    }

    $license = $this->licenseDao->getLicenseByShortName($shortName,
      $this->restHelper->getGroupId());

    if ($license === null) {
      throw new HttpNotFoundException(
        "No license found with short name '$shortName'.");
    }
    $isCandidate = $this->restHelper->getDbHelper()->doesIdExist(
      "license_candidate", "rf_pk", $license->getId());
    if (!$isCandidate && !Auth::isAdmin()) {
      throw new HttpForbiddenException(
        "Need to be admin to edit non-candidate license.");
    }
    if ($isCandidate && ! $this->restHelper->getUserDao()->isAdvisorOrAdmin(
        $this->restHelper->getUserId(), $this->restHelper->getGroupId())) {
      throw new HttpForbiddenException(
        "Operation not permitted for this group.");
    }

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
    if (empty($assocData)) {
      throw new HttpBadRequestException("Empty body sent.");
    }

    $tableName = "license_ref";
    if ($isCandidate) {
      $tableName = "license_candidate";
    }
    $this->dbHelper->getDbManager()->updateTableRow($tableName, $assocData,
      "rf_pk", $license->getId(), __METHOD__ . ".updateLicense");
    $newInfo = new Info(200, "License " . $license->getShortName() .
      " updated.", InfoType::INFO);
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
   * @throws HttpErrorException
   */
  public function handleImportLicense($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $this->throwNotAdminException();
    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    /** @var \Fossology\UI\Page\AdminLicenseFromCSV $adminLicenseFromCsv */
    $adminLicenseFromCsv = $this->restHelper->getPlugin('admin_license_from_csv');

    $uploadedFile = $symReq->files->get($adminLicenseFromCsv->getFileInputName($apiVersion),
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

    $res = $adminLicenseFromCsv->handleFileUpload($uploadedFile, $delimiter,
      $enclosure);

    if (!$res[0]) {
      throw new HttpBadRequestException($res[1]);
    }

    $newInfo = new Info($res[2], $res[1], InfoType::INFO);
    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }

  /**
   * Get list of all license candidates, paginated upon request params
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpForbiddenException
   */
  public function getCandidates($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $this->throwNotAdminException();
    /** @var \Fossology\UI\Page\AdminLicenseCandidate $adminLicenseCandidate */
    $adminLicenseCandidate = $this->restHelper->getPlugin("admin_license_candidate");
    $licenses = LicenseCandidate::convertDbArray($adminLicenseCandidate->getCandidateArrayData(), $apiVersion);
    return $response->withJson($licenses, 200);
  }

  /**
   * Delete license candidate by id.
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function deleteAdminLicenseCandidate($request, $response, $args)
  {
    $this->throwNotAdminException();
    $id = intval($args['id']);
    /** @var \Fossology\UI\Page\AdminLicenseCandidate $adminLicenseCandidate */
    $adminLicenseCandidate = $this->restHelper->getPlugin('admin_license_candidate');

    if (!$adminLicenseCandidate->getDataRow($id)) {
      throw new HttpNotFoundException("License candidate not found.");
    }
    $res = $adminLicenseCandidate->doDeleteCandidate($id,false);
    $message = $res->getContent();
    if ($res->getContent() !== 'true') {
      throw new HttpConflictException(
        "License used at following locations, can not delete: " .
        $message);
    }
    $resInfo = new Info(202, "License candidate will be deleted.",
      InfoType::INFO);
    return $response->withJson($resInfo->getArray(), $resInfo->getCode());
  }

  /**
   * Get all admin license acknowledgements
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getAllAdminAcknowledgements($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $this->throwNotAdminException();
    $rawData = $this->adminLicenseAckDao->getAllAcknowledgements();

    $acknowledgements = [];
    foreach ($rawData as $ack) {
        $acknowledgements[] = new AdminAcknowledgement(intval($ack['la_pk']), $ack['name'], $ack['acknowledgement'], $ack['is_enabled'] == "t");
    }

    $res = array_map(fn($acknowledgement) => $acknowledgement->getArray($apiVersion), $acknowledgements);
    return $response->withJson($res, 200);
  }

  /**
   * Add, Edit & toggle admin license acknowledgement.
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function handleAdminLicenseAcknowledgement($request, $response, $args)
  {
    $body = $this->getParsedBody($request);
    $errors = [];
    $success = [];

    if (empty($body)) {
      throw new HttpBadRequestException("Request body is missing or empty.");
    }
    if (!is_array($body)) {
      throw new HttpBadRequestException("Request body should be an array.");
    }
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
      }
      $success[] = $info->getArray();
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
    $apiVersion = ApiVersion::getVersion($request);
    $rawData = $this->licenseStdCommentDao->getAllComments();
    $comments = [];
    foreach ($rawData as $cmt) {
      $comments[] = new LicenseStandardComment(intval($cmt['lsc_pk']), $cmt['name'], $cmt['comment'], $cmt['is_enabled'] == "t");
    }
    $res = array_map(fn($comment) => $comment->getArray($apiVersion), $comments);
    return $response->withJson($res, 200);
  }

  /**
   * Add, Edit & toggle license standard comment.
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function handleLicenseStandardComment($request, $response, $args)
  {
    $this->throwNotAdminException();

    $body = $this->getParsedBody($request);
    $errors = [];
    $success = [];

    if (empty($body)) {
      throw new HttpBadRequestException("Request body is missing or empty.");
    }
    if (!is_array($body)) {
      throw new HttpBadRequestException("Request body should be an array.");
    }
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
      }
      $success[] = $info->getArray();
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
   * @throws HttpErrorException
   */
  public function verifyLicense($request, $response, $args)
  {
    $this->throwNotAdminException();
    $licenseShortName = $args["shortname"];
    $body = $this->getParsedBody($request);
    $parentName = $body["parentShortname"];

    if (empty($licenseShortName) || empty($parentName)) {
      throw new HttpBadRequestException(
        "License ShortName or Parent ShortName is missing.");
    }

    $license = $this->licenseDao->getLicenseByShortName($licenseShortName, $this->restHelper->getGroupId());
    if ($licenseShortName != $parentName) {
      $parentLicense = $this->licenseDao->getLicenseByShortName($parentName, $this->restHelper->getGroupId());
    } else {
      $parentLicense = $license;
    }

    if (empty($license) || empty($parentLicense)) {
      throw new HttpNotFoundException("License not found.");
    }

    try {
      /** @var \Fossology\UI\Page\AdminLicenseCandidate $adminLicenseCandidate */
      $adminLicenseCandidate = $this->restHelper->getPlugin('admin_license_candidate');
      $ok = $adminLicenseCandidate->verifyCandidate($license->getId(), $licenseShortName, $parentLicense->getId());
    } catch (\Throwable $th) {
      throw new HttpConflictException('The license text already exists.', $th);
    }

    if (!$ok) {
      throw new HttpBadRequestException('Short name must be unique');
    }
    $with = $parentLicense->getId() === $license->getId() ? '' : " as variant of ($parentName).";
    $info = new Info(200, 'Successfully verified candidate ('.$licenseShortName.')'.$with, InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * merge the license
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function mergeLicense($request, $response, $args)
  {
    $this->throwNotAdminException();
    $licenseShortName = $args["shortname"];
    $body = $this->getParsedBody($request);
    $parentName = $body["parentShortname"];

    if (empty($licenseShortName) || empty($parentName)) {
      throw new HttpBadRequestException(
        "License ShortName or Parent ShortName is missing.");
    }
    if ($licenseShortName == $parentName) {
      throw new HttpBadRequestException(
        "License ShortName and Parent ShortName are same.");
    }

    $license = $this->licenseDao->getLicenseByShortName($licenseShortName, $this->restHelper->getGroupId());
    $mergeLicense = $this->licenseDao->getLicenseByShortName($parentName, $this->restHelper->getGroupId());

    if (empty($license) || empty($mergeLicense)) {
      throw new HttpNotFoundException("License not found.");
    }

    /** @var \Fossology\UI\Page\AdminLicenseCandidate $adminLicenseCandidate */
    $adminLicenseCandidate = $this->restHelper->getPlugin('admin_license_candidate');
    $vars = $adminLicenseCandidate->getDataRow($license->getId());
    if (empty($vars)) {
      throw new HttpNotFoundException("Candidate license not found.");
    }

    try {
      $vars['shortname'] = $vars['rf_shortname'];
      $ok = $adminLicenseCandidate->mergeCandidate($license->getId(), $mergeLicense->getId(), $vars);
    } catch (\Throwable $th) {
      throw new HttpConflictException('The license text already exists.', $th);
    }

    if (!$ok) {
      throw new HttpInternalServerErrorException("Please try again later.");
    }
    $info = new Info(200, "Successfully merged candidate ($parentName) into ($licenseShortName).", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Get suggested license from reference text
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getSuggestedLicense($request, $response, $args)
  {
    $this->throwNotAdminException();
    $body =  $this->getParsedBody($request);
    $rfText = $body["referenceText"];
    if (empty($rfText)) {
      throw new HttpBadRequestException("Reference text is missing.");
    }
    /** @var \Fossology\UI\Page\AdminLicenseCandidate $adminLicenseCandidate */
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
      $suggestLicense = [];
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
   * @throws HttpErrorException
   */
  public function exportAdminLicenseToCSV($request, $response, $args)
  {
    $this->throwNotAdminException();
    $query = $request->getQueryParams();
    $rf = 0;
    if (array_key_exists('id', $query)) {
      $rf = intval($query['id']);
    }
    if ($rf != 0 &&
        (! $this->dbHelper->doesIdExist("license_ref", "rf_pk", $rf) &&
         ! $this->dbHelper->doesIdExist("license_candidate", "rf_pk", $rf))) {
      throw new HttpNotFoundException("License not found.");
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

  /**
   * Export licenses to JSON file
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function exportAdminLicenseToJSON($request, $response, $args)
  {
    $this->throwNotAdminException();
    $query = $request->getQueryParams();
    $rf = 0;
    if (array_key_exists('id', $query)) {
      $rf = intval($query['id']);
    }
    if ($rf != 0 &&
        (! $this->dbHelper->doesIdExist("license_ref", "rf_pk", $rf) &&
         ! $this->dbHelper->doesIdExist("license_candidate", "rf_pk", $rf))) {
      throw new HttpNotFoundException("License not found.");
    }
    $dbManager = $this->dbHelper->getDbManager();
    $licenseCsvExport = new LicenseCsvExport($dbManager);
    $content = $licenseCsvExport->createCsv($rf, false, true);
    $fileName = "fossology-license-export-" . date("YMj-Gis");
    $newResponse = $response->withHeader('Content-type', 'text/json, charset=UTF-8')
      ->withHeader('Content-Disposition', 'attachment; filename=' . $fileName . '.json')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('Cache-Control', 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0')
      ->withHeader('Expires', 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');
    $sf = new StreamFactory();
    return $newResponse->withBody(
      $content ? $sf->createStream($content) : $sf->createStream('')
    );
  }
}
