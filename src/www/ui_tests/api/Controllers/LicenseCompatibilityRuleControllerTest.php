<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for LicenseCompatibilityRuleController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\LicenseCompatibilityRuleController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\LicenseCompatibilityRule;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class LicenseCompatibilityRuleControllerTest
 * @brief Unit tests for LicenseCompatibilityRuleController
 */
class LicenseCompatibilityRuleControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var DbHelper $dbHelper
   * DbHelper mock
   */
  private $dbHelper;

  /**
   * @var DbManager $dbManager
   * DbManager mock
   */
  private $dbManager;

  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var CompatibilityDao $compatibilityDao
   * CompatibilityDao mock
   */
  private $compatibilityDao;

  /**
   * @var M\MockInterface $licenseYamlPlugin
   * admin_license_from_yaml mock
   */
  private $licenseYamlPlugin;

  /**
   * @var LicenseCompatibilityRuleController $ruleController
   * LicenseCompatibilityRuleController object
   */
  private $ruleController;

  /**
   * @var StreamFactory $streamFactory
   * Stream factory to create body streams.
   */
  private $streamFactory;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container, $SysConf;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->dbManager = M::mock(DbManager::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->compatibilityDao = M::mock(CompatibilityDao::class);
    $this->licenseYamlPlugin = M::mock('admin_license_from_yaml');

    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);
    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('admin_license_from_yaml'))
      ->andReturn($this->licenseYamlPlugin);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array(
      'dao.compatibility'))->andReturn($this->compatibilityDao);

    $SysConf['SYSCONFIG']['LicenseTypes'] = "Permissive, Strong Copyleft";

    $this->ruleController = new LicenseCompatibilityRuleController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->streamFactory = new StreamFactory();
  }

  /**
   * @brief Remove test objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * Helper function to get JSON array from response
   *
   * @param ResponseHelper $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * Generate a rule row as returned by the DAO
   *
   * @param int $ruleId Id of the rule
   * @return array
   */
  private function getRuleRow($ruleId)
  {
    return [
      "lr_pk" => $ruleId,
      "first_rf_fk" => 306,
      "second_rf_fk" => null,
      "first_rf_shortname" => "MIT",
      "second_rf_shortname" => null,
      "first_type" => "Permissive",
      "second_type" => "Strong Copyleft",
      "comment" => "Rule $ruleId",
      "compatibility" => "t"
    ];
  }

  /**
   * Create a request with the given JSON body
   *
   * @param string $method  HTTP method
   * @param array $body     Body to be sent as JSON
   * @param int $version    API version
   * @return Request
   */
  private function createJsonRequest($method, $body,
                                     $version = ApiVersion::V2)
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request($method, new Uri("HTTP", "localhost"),
      $requestHeaders, [], [],
      $this->streamFactory->createStream(json_encode($body)));
    return $request->withAttribute(ApiVersion::ATTRIBUTE_NAME, $version);
  }

  /**
   * Create a GET request with the given query string
   *
   * @param string $query Query string of the request
   * @param int $version  API version
   * @return Request
   */
  private function createGetRequest($query = "", $version = ApiVersion::V2)
  {
    $request = new Request("GET",
      new Uri("HTTP", "localhost", null, "/license-compatibility-rules",
        $query),
      new Headers(), [], [], $this->streamFactory->createStream());
    return $request->withAttribute(ApiVersion::ATTRIBUTE_NAME, $version);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() for version 1
   * -# Check if the rules are returned with the snake_case keys
   */
  public function testGetRulesV1()
  {
    $this->testGetRules(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() for version 2
   * -# Check if the rules are returned with the camelCase keys
   */
  public function testGetRulesV2()
  {
    $this->testGetRules();
  }

  /**
   * @param $version
   * @return void
   */
  private function testGetRules($version = ApiVersion::V2)
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $rows = [$this->getRuleRow(1), $this->getRuleRow(2)];

    $this->compatibilityDao->shouldReceive('getTotalRulesCount')
      ->withArgs([""])->andReturn(2);
    $this->compatibilityDao->shouldReceive('getAllRules')
      ->withArgs([100, 0, ""])->andReturn($rows);

    $expected = [];
    foreach ($rows as $row) {
      $expected[] = LicenseCompatibilityRule::fromArray($row)
        ->getArray($version);
    }

    $actualResponse = $this->ruleController->getRules(
      $this->createGetRequest("", $version), new ResponseHelper(), []);

    $this->assertEquals(200, $actualResponse->getStatusCode());
    $this->assertEquals(1,
      intval($actualResponse->getHeaderLine('X-Total-Pages')));
    $this->assertEquals($expected, $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() with pagination and
   *    a search term
   * -# Check if the DAO is called with the translated offset and search term
   */
  public function testGetRulesWithPaginationAndSearch()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $rows = [$this->getRuleRow(3)];

    $this->compatibilityDao->shouldReceive('getTotalRulesCount')
      ->withArgs(["%copyleft%"])->andReturn(3);
    $this->compatibilityDao->shouldReceive('getAllRules')
      ->withArgs([2, 2, "%copyleft%"])->andReturn($rows);

    $actualResponse = $this->ruleController->getRules(
      $this->createGetRequest("page=2&limit=2&search=copyleft"),
      new ResponseHelper(), []);

    $this->assertEquals(200, $actualResponse->getStatusCode());
    $this->assertEquals(2,
      intval($actualResponse->getHeaderLine('X-Total-Pages')));
    $this->assertCount(1, $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() with an invalid
   *    limit
   * -# Check if HttpBadRequestException is thrown
   */
  public function testGetRulesInvalidLimit()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->getRules($this->createGetRequest("limit=-1"),
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() with a zero limit
   * -# Check if HttpBadRequestException is thrown instead of falling back to
   *    the default limit
   */
  public function testGetRulesZeroLimit()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->getRules($this->createGetRequest("limit=0"),
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() with a limit which
   *    is not a number
   * -# Check if HttpBadRequestException is thrown
   */
  public function testGetRulesNonNumericLimit()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->getRules($this->createGetRequest("limit=abc"),
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() with a zero page
   * -# Check if HttpBadRequestException is thrown
   */
  public function testGetRulesZeroPage()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('getTotalRulesCount')
      ->withArgs([""])->andReturn(2);
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->getRules($this->createGetRequest("page=0"),
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() with a page above
   *    the total pages
   * -# Check if HttpBadRequestException is thrown
   */
  public function testGetRulesPageOutOfRange()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('getTotalRulesCount')
      ->withArgs([""])->andReturn(2);
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->getRules($this->createGetRequest("page=5&limit=2"),
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::getRules() as a non-admin user
   * -# Check if HttpForbiddenException is thrown
   */
  public function testGetRulesNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);

    $this->ruleController->getRules($this->createGetRequest(),
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::createRule() for a valid rule
   * -# Check if response status is 201
   */
  public function testCreateRule()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["license_ref", "rf_pk", 306])->andReturn(true);
    $this->compatibilityDao->shouldReceive('insertRule')
      ->withArgs([306, null, null, "Strong Copyleft", "New rule", true])
      ->andReturn(9);

    $request = $this->createJsonRequest("POST", [
      "firstLicenseId" => 306,
      "secondType" => "Strong Copyleft",
      "comment" => "New rule",
      "compatibility" => true
    ]);

    $expectedResponse = new Info(201, "Rule 9 added successfully.",
      InfoType::INFO);
    $actualResponse = $this->ruleController->createRule($request,
      new ResponseHelper(), []);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::createRule() with the version 1
   *    field names
   * -# Check if response status is 201
   */
  public function testCreateRuleWithV1FieldNames()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('insertRule')
      ->withArgs([null, null, "Permissive", null, "New rule", false])
      ->andReturn(10);

    $request = $this->createJsonRequest("POST", [
      "first_type" => "Permissive",
      "comment" => "New rule",
      "compatibility" => false
    ], ApiVersion::V1);

    $actualResponse = $this->ruleController->createRule($request,
      new ResponseHelper(), []);

    $this->assertEquals(201, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::createRule() without a
   *    description
   * -# Check if HttpBadRequestException is thrown
   */
  public function testCreateRuleWithoutComment()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->createRule($this->createJsonRequest("POST", [
      "firstType" => "Permissive",
      "compatibility" => true
    ]), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::createRule() with an empty
   *    description
   * -# Check if HttpBadRequestException is thrown
   */
  public function testCreateRuleWithEmptyComment()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->createRule($this->createJsonRequest("POST", [
      "firstType" => "Permissive",
      "comment" => "  ",
      "compatibility" => true
    ]), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::createRule() without any
   *    license or license type
   * -# Check if HttpBadRequestException is thrown
   */
  public function testCreateRuleWithoutLicenseAndType()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->createRule($this->createJsonRequest("POST", [
      "comment" => "Default rule",
      "compatibility" => true
    ]), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::createRule() with a license
   *    which does not exist
   * -# Check if HttpBadRequestException is thrown
   */
  public function testCreateRuleWithUnknownLicense()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["license_ref", "rf_pk", 999])->andReturn(false);
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->createRule($this->createJsonRequest("POST", [
      "firstLicenseId" => 999,
      "comment" => "New rule",
      "compatibility" => true
    ]), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::createRule() with a license
   *    type which is not configured
   * -# Check if HttpBadRequestException is thrown
   */
  public function testCreateRuleWithUnknownLicenseType()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->createRule($this->createJsonRequest("POST", [
      "firstType" => "Unknown Type",
      "comment" => "New rule",
      "compatibility" => true
    ]), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::createRule() when the rule can
   *    not be inserted
   * -# Check if HttpInternalServerErrorException is thrown
   */
  public function testCreateRuleFailure()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('insertRule')->andReturn(-2);
    $this->expectException(HttpInternalServerErrorException::class);

    $this->ruleController->createRule($this->createJsonRequest("POST", [
      "firstType" => "Permissive",
      "comment" => "New rule",
      "compatibility" => true
    ]), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::updateRule() for a valid
   *    request
   * -# Check if response status is 200
   */
  public function testUpdateRule()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $ruleId = 4;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([$ruleId])
      ->andReturn($this->getRuleRow($ruleId));
    $this->compatibilityDao->shouldReceive('updateRuleFromArray')
      ->withArgs([[$ruleId => ["comment" => "Updated rule",
        "result" => false]]])->andReturn(1);

    $request = $this->createJsonRequest("PUT", [
      "comment" => "Updated rule",
      "compatibility" => false
    ]);

    $expectedResponse = new Info(200, "Rule $ruleId updated successfully.",
      InfoType::INFO);
    $actualResponse = $this->ruleController->updateRule($request,
      new ResponseHelper(), ["id" => $ruleId]);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::updateRule() with the licenses
   *    reset to null
   * -# Check if response status is 200
   */
  public function testUpdateRuleResetLicense()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $ruleId = 4;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([$ruleId])
      ->andReturn($this->getRuleRow($ruleId));
    $this->compatibilityDao->shouldReceive('updateRuleFromArray')
      ->withArgs([[$ruleId => ["firstLic" => null, "firstType" => null]]])
      ->andReturn(1);

    $request = $this->createJsonRequest("PUT", [
      "firstLicenseId" => null,
      "firstType" => null
    ]);

    $actualResponse = $this->ruleController->updateRule($request,
      new ResponseHelper(), ["id" => $ruleId]);

    $this->assertEquals(200, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::updateRule() for a rule which
   *    does not exist
   * -# Check if HttpNotFoundException is thrown
   */
  public function testUpdateRuleNotFound()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([8])
      ->andReturn(null);
    $this->expectException(HttpNotFoundException::class);

    $this->ruleController->updateRule(
      $this->createJsonRequest("PUT", ["comment" => "Updated rule"]),
      new ResponseHelper(), ["id" => 8]);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::updateRule() with an empty
   *    request body
   * -# Check if HttpBadRequestException is thrown
   */
  public function testUpdateRuleEmptyBody()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([4])
      ->andReturn($this->getRuleRow(4));
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->updateRule($this->createJsonRequest("PUT", []),
      new ResponseHelper(), ["id" => 4]);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::updateRule() with a body
   *    holding no rule value
   * -# Check if HttpBadRequestException is thrown
   */
  public function testUpdateRuleWithoutRuleValues()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([4])
      ->andReturn($this->getRuleRow(4));
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->updateRule(
      $this->createJsonRequest("PUT", ["unknownField" => "value"]),
      new ResponseHelper(), ["id" => 4]);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::deleteRule() for a valid
   *    request
   * -# Check if response status is 200
   */
  public function testDeleteRule()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $ruleId = 4;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([$ruleId])
      ->andReturn($this->getRuleRow($ruleId));
    $this->compatibilityDao->shouldReceive('deleteRule')->withArgs([$ruleId])
      ->andReturn(true);

    $expectedResponse = new Info(200, "Rule $ruleId deleted successfully.",
      InfoType::INFO);
    $actualResponse = $this->ruleController->deleteRule(null,
      new ResponseHelper(), ["id" => $ruleId]);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::deleteRule() for a rule which
   *    does not exist
   * -# Check if HttpNotFoundException is thrown
   */
  public function testDeleteRuleNotFound()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([8])
      ->andReturn(null);
    $this->expectException(HttpNotFoundException::class);

    $this->ruleController->deleteRule(null, new ResponseHelper(),
      ["id" => 8]);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::deleteRule() when the rule can
   *    not be deleted
   * -# Check if HttpInternalServerErrorException is thrown
   */
  public function testDeleteRuleFailure()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([4])
      ->andReturn($this->getRuleRow(4));
    $this->compatibilityDao->shouldReceive('deleteRule')->withArgs([4])
      ->andReturn(false);
    $this->expectException(HttpInternalServerErrorException::class);

    $this->ruleController->deleteRule(null, new ResponseHelper(),
      ["id" => 4]);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test LicenseCompatibilityRuleController::importRules() for a valid
   *    request
   * -# Check if response status is 200
   */
  public function testImportRules()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->licenseYamlPlugin->shouldReceive('getFileInputName')
      ->andReturn("fileInput");
    $this->licenseYamlPlugin->shouldReceive('handleFileUpload')
      ->withArgs([null, true])
      ->andReturn([true, "Read yaml: 2 license rules", 200]);

    $expectedResponse = new Info(200, "Read yaml: 2 license rules",
      InfoType::INFO);
    $actualResponse = $this->ruleController->importRules(
      $this->createJsonRequest("POST", []), new ResponseHelper(), []);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test LicenseCompatibilityRuleController::importRules() when the upload
   *    is rejected
   * -# Check if HttpBadRequestException is thrown
   */
  public function testImportRulesInvalidFile()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->licenseYamlPlugin->shouldReceive('getFileInputName')
      ->andReturn("fileInput");
    $this->licenseYamlPlugin->shouldReceive('handleFileUpload')
      ->withArgs([null, true])->andReturn([false, "No file selected", 400]);
    $this->expectException(HttpBadRequestException::class);

    $this->ruleController->importRules($this->createJsonRequest("POST", []),
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::exportRules() for all the rules
   * -# Check if the YAML of the rules is returned
   */
  public function testExportRules()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $rows = [[
      "firstname" => "MIT",
      "secondname" => null,
      "firsttype" => null,
      "secondtype" => "Strong Copyleft",
      "compatibility" => "true",
      "comment" => "Rule 1"
    ]];
    $this->compatibilityDao->shouldReceive('getDefaultCompatibility')
      ->andReturn(false);
    $this->dbManager->shouldReceive('getRows')->andReturn($rows);

    $actualResponse = $this->ruleController->exportRules(
      $this->createGetRequest(), new ResponseHelper(), []);

    $this->assertEquals(200, $actualResponse->getStatusCode());
    $this->assertEquals("text/x-yaml; charset=UTF-8",
      $actualResponse->getHeaderLine('Content-type'));
    $actualResponse->getBody()->seek(0);
    $this->assertEquals(yaml_emit(["default" => false, "rules" => $rows]),
      $actualResponse->getBody()->getContents());
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::exportRules() for a rule which
   *    does not exist
   * -# Check if HttpNotFoundException is thrown
   */
  public function testExportRulesNotFound()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->compatibilityDao->shouldReceive('getRuleById')->withArgs([8])
      ->andReturn(null);
    $this->expectException(HttpNotFoundException::class);

    $this->ruleController->exportRules($this->createGetRequest("id=8"),
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRuleController::exportRules() as a non-admin
   *    user
   * -# Check if HttpForbiddenException is thrown
   */
  public function testExportRulesNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);

    $this->ruleController->exportRules($this->createGetRequest(),
      new ResponseHelper(), []);
  }
}
