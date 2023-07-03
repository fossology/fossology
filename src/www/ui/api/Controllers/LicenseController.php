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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseAcknowledgementDao;
use Fossology\Lib\Dao\LicenseDao;
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
   * @param ContainerInterface $container
   */
  public function __construct($container)
  {
    parent::__construct($container);
    $this->licenseDao = $this->container->get('dao.license');
    $this->adminLicenseAckDao = $this->container->get('dao.license.acknowledgement');
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
}
