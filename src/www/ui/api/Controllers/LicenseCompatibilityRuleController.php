<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for license compatibility rules
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Application\LicenseCompatibilityRulesYamlExport;
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\LicenseCompatibilityRule;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Factory\StreamFactory;

/**
 * @class LicenseCompatibilityRuleController
 * @brief Controller for license compatibility rules
 */
class LicenseCompatibilityRuleController extends RestController
{
  /**
   * Query parameter name for page listing
   */
  const PAGE_PARAM = "page";
  /**
   * Query parameter name for limiting listing
   */
  const LIMIT_PARAM = "limit";
  /**
   * Query parameter name to filter the rules on their description
   */
  const SEARCH_PARAM = "search";
  /**
   * Limit of rules in get query
   */
  const RULE_FETCH_LIMIT = 100;

  /**
   * @var CompatibilityDao $compatibilityDao
   * Compatibility DAO object
   */
  private $compatibilityDao;

  /**
   * @param ContainerInterface $container
   */
  public function __construct($container)
  {
    parent::__construct($container);
    $this->compatibilityDao = $this->container->get('dao.compatibility');
  }

  /**
   * Get the list of license compatibility rules, paginated upon request params
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getRules($request, $response, $args)
  {
    $this->throwNotAdminException();
    $apiVersion = ApiVersion::getVersion($request);
    $query = $request->getQueryParams();

    // Compare against "" instead of using empty(), which is true for "0".
    $limit = $query[self::LIMIT_PARAM] ?? "";
    if ($limit !== "") {
      $limit = filter_var($limit, FILTER_VALIDATE_INT);
      if ($limit === false || $limit < 1) {
        throw new HttpBadRequestException(
          "limit should be positive integer > 1");
      }
    } else {
      $limit = self::RULE_FETCH_LIMIT;
    }

    $searchTerm = $query[self::SEARCH_PARAM] ?? "";
    if (! is_string($searchTerm)) {
      throw new HttpBadRequestException("search should be a string");
    }
    $searchTerm = trim($searchTerm);
    if (! empty($searchTerm)) {
      $searchTerm = "%" . $searchTerm . "%";
    }

    $totalPages = $this->compatibilityDao->getTotalRulesCount($searchTerm);
    $totalPages = intval(ceil($totalPages / $limit));

    $page = $query[self::PAGE_PARAM] ?? "";
    if ($page !== "") {
      $page = filter_var($page, FILTER_VALIDATE_INT);
      if ($page === false || $page < 1) {
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

    $rules = [];
    foreach ($this->compatibilityDao->getAllRules($limit,
        $limit * ($page - 1), $searchTerm) as $row) {
      $rules[] = LicenseCompatibilityRule::fromArray($row)
        ->getArray($apiVersion);
    }
    return $response->withHeader("X-Total-Pages", $totalPages)
      ->withJson($rules, 200);
  }

  /**
   * Create a new license compatibility rule
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function createRule($request, $response, $args)
  {
    $this->throwNotAdminException();
    $rule = $this->parseRule($this->getParsedBody($request), true);

    $ruleId = $this->compatibilityDao->insertRule($rule["firstLic"],
      $rule["secondLic"], $rule["firstType"], $rule["secondType"],
      $rule["comment"], $rule["result"]);
    if ($ruleId < 0) {
      throw new HttpInternalServerErrorException(
        "Unable to create the compatibility rule.");
    }

    $info = new Info(201, "Rule $ruleId added successfully.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Update an existing license compatibility rule
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function updateRule($request, $response, $args)
  {
    $this->throwNotAdminException();
    $ruleId = intval($args['id']);
    if ($this->compatibilityDao->getRuleById($ruleId) === null) {
      throw new HttpNotFoundException("Compatibility rule does not exist.");
    }

    $rule = $this->parseRule($this->getParsedBody($request), false);
    if (empty($rule)) {
      throw new HttpBadRequestException("No rule values provided to update.");
    }

    try {
      $updated = $this->compatibilityDao->updateRuleFromArray([$ruleId => $rule]);
    } catch (\UnexpectedValueException $e) {
      throw new HttpBadRequestException($e->getMessage(), $e);
    }
    if ($updated < 1) {
      throw new HttpInternalServerErrorException(
        "Unable to update the compatibility rule.");
    }

    $info = new Info(200, "Rule $ruleId updated successfully.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Delete an existing license compatibility rule
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function deleteRule($request, $response, $args)
  {
    $this->throwNotAdminException();
    $ruleId = intval($args['id']);
    if ($this->compatibilityDao->getRuleById($ruleId) === null) {
      throw new HttpNotFoundException("Compatibility rule does not exist.");
    }

    if (! $this->compatibilityDao->deleteRule($ruleId)) {
      throw new HttpInternalServerErrorException(
        "Unable to delete the compatibility rule.");
    }

    $info = new Info(200, "Rule $ruleId deleted successfully.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Import license compatibility rules from a YAML file
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function importRules($request, $response, $args)
  {
    $this->throwNotAdminException();
    $apiVersion = ApiVersion::getVersion($request);

    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    /** @var \Fossology\UI\Page\AdminLicenseFromYAML $adminLicenseFromYaml */
    $adminLicenseFromYaml = $this->restHelper->getPlugin('admin_license_from_yaml');

    $uploadedFile = $symReq->files->get(
      $adminLicenseFromYaml->getFileInputName($apiVersion), null);

    $res = $adminLicenseFromYaml->handleFileUpload($uploadedFile, true);
    if (! $res[0]) {
      throw new HttpBadRequestException($res[1]);
    }

    $newInfo = new Info($res[2], $res[1], InfoType::INFO);
    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }

  /**
   * Export the license compatibility rules as a YAML file
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function exportRules($request, $response, $args)
  {
    $this->throwNotAdminException();
    $query = $request->getQueryParams();
    $ruleId = 0;
    if (array_key_exists('id', $query)) {
      $ruleId = intval($query['id']);
    }
    if ($ruleId != 0 &&
        $this->compatibilityDao->getRuleById($ruleId) === null) {
      throw new HttpNotFoundException("Compatibility rule does not exist.");
    }

    $licenseYamlExport = new LicenseCompatibilityRulesYamlExport(
      $this->dbHelper->getDbManager(), $this->compatibilityDao);
    $content = $licenseYamlExport->createYaml($ruleId);
    $fileName = "fossology-license-comp-rules-export-" . date("YMj-Gis");
    $newResponse = $response
      ->withHeader('Content-type', 'text/x-yaml; charset=UTF-8')
      ->withHeader('Content-Disposition',
        'attachment; filename=' . $fileName . '.yaml')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('Cache-Control',
        'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0')
      ->withHeader('Expires', 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');
    $sf = new StreamFactory();
    return $newResponse->withBody(
      $content ? $sf->createStream($content) : $sf->createStream('')
    );
  }

  /**
   * @brief Validate the rule values sent in the request body.
   *
   * Only the fields present in the body are returned, which allows updating a
   * rule partially. For a new rule, the description and the compatibility
   * result are mandatory and at least one license or license type has to be
   * given, otherwise the rule would shadow the default compatibility.
   *
   * @param array|null $body Parsed request body
   * @param boolean $isNewRule True while creating a rule
   * @return array Rule values as expected by CompatibilityDao
   * @throws HttpBadRequestException
   */
  private function parseRule($body, $isNewRule)
  {
    if (empty($body) || ! is_array($body)) {
      throw new HttpBadRequestException("Invalid request body.");
    }
    $rule = [];

    list($exists, $value) = $this->getRuleField($body, "firstLicenseId",
      "first_license_id");
    if ($exists) {
      $rule["firstLic"] = $this->validateLicenseId($value, "firstLicenseId");
    }
    list($exists, $value) = $this->getRuleField($body, "secondLicenseId",
      "second_license_id");
    if ($exists) {
      $rule["secondLic"] = $this->validateLicenseId($value, "secondLicenseId");
    }
    list($exists, $value) = $this->getRuleField($body, "firstType",
      "first_type");
    if ($exists) {
      $rule["firstType"] = $this->validateLicenseType($value, "firstType");
    }
    list($exists, $value) = $this->getRuleField($body, "secondType",
      "second_type");
    if ($exists) {
      $rule["secondType"] = $this->validateLicenseType($value, "secondType");
    }
    if (array_key_exists("comment", $body)) {
      if (! is_string($body["comment"]) || empty(trim($body["comment"]))) {
        throw new HttpBadRequestException(
          "comment should be a non-empty string.");
      }
      $rule["comment"] = trim($body["comment"]);
    }
    if (array_key_exists("compatibility", $body)) {
      $compatibility = filter_var($body["compatibility"],
        FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
      if ($compatibility === null) {
        throw new HttpBadRequestException("compatibility should be a boolean.");
      }
      $rule["result"] = $compatibility;
    }

    if (! $isNewRule) {
      return $rule;
    }
    foreach (["comment", "result"] as $mandatory) {
      if (! array_key_exists($mandatory, $rule)) {
        throw new HttpBadRequestException("comment and compatibility are " .
          "required to create a rule.");
      }
    }
    $rule += ["firstLic" => null, "secondLic" => null, "firstType" => null,
      "secondType" => null];
    if ($rule["firstLic"] === null && $rule["secondLic"] === null &&
        $rule["firstType"] === null && $rule["secondType"] === null) {
      throw new HttpBadRequestException("At least one license or license type " .
        "is required to create a rule.");
    }
    return $rule;
  }

  /**
   * @brief Read a rule field from the request body.
   *
   * Both the camelCase (version 2) and the snake_case (version 1) name of the
   * field are accepted.
   * @param array $body Parsed request body
   * @param string $nameV2 Name of the field in version 2
   * @param string $nameV1 Name of the field in version 1
   * @return array Array with the presence of the field and its value
   */
  private function getRuleField($body, $nameV2, $nameV1)
  {
    if (array_key_exists($nameV2, $body)) {
      return [true, $body[$nameV2]];
    }
    if (array_key_exists($nameV1, $body)) {
      return [true, $body[$nameV1]];
    }
    return [false, null];
  }

  /**
   * @brief Validate a license ID sent in the request body.
   * @param mixed $licenseId Value to validate, null matches every license
   * @param string $field    Name of the field to report in the error message
   * @return int|null Validated license ID
   * @throws HttpBadRequestException
   */
  private function validateLicenseId($licenseId, $field)
  {
    if ($licenseId === null || $licenseId === "") {
      return null;
    }
    $licenseId = filter_var($licenseId, FILTER_VALIDATE_INT);
    if ($licenseId === false || $licenseId < 1) {
      throw new HttpBadRequestException("$field should be positive integer.");
    }
    if (! $this->dbHelper->doesIdExist("license_ref", "rf_pk", $licenseId)) {
      throw new HttpBadRequestException(
        "No license found with id '$licenseId'.");
    }
    return $licenseId;
  }

  /**
   * @brief Validate a license type sent in the request body.
   * @param mixed $licenseType Value to validate, null matches every type
   * @param string $field      Name of the field to report in the error message
   * @return string|null Validated license type
   * @throws HttpBadRequestException
   */
  private function validateLicenseType($licenseType, $field)
  {
    if ($licenseType === null || $licenseType === "") {
      return null;
    }
    if (! is_string($licenseType)) {
      throw new HttpBadRequestException("$field should be a string.");
    }
    $licenseType = trim($licenseType);
    $licenseTypes = $this->getLicenseTypes();
    if (! in_array($licenseType, $licenseTypes)) {
      throw new HttpBadRequestException("Invalid $field '$licenseType', " .
        "allowed values are: " . implode(", ", $licenseTypes) . ".");
    }
    return $licenseType;
  }

  /**
   * @brief Get the license types configured on the server.
   * @return array List of the license types
   */
  private function getLicenseTypes()
  {
    global $SysConf;

    $licenseTypes = $SysConf['SYSCONFIG']['LicenseTypes'] ?? "";
    return array_filter(array_map('trim', explode(',', $licenseTypes)));
  }
}
