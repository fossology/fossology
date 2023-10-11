<?php
/*
 SPDX-FileCopyrightText: © 2020, 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for InfoController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\UI\Api\Controllers\InfoController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;
use Symfony\Component\Yaml\Parser;

/**
 * @class InfoControllerTest
 * @brief Test cases for InfoController
 */
class InfoControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var string YAML_LOC
   * Location of openapi.yaml file
   */
  const YAML_LOC = __DIR__ . '/../../../ui/api/documentation/openapi.yaml';

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
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->infoController = new InfoController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
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

  public function testGetInfo()
  {
    $yaml = new Parser();
    $yamlDocArray = $yaml->parseFile(self::YAML_LOC);
    $apiTitle = $yamlDocArray["info"]["title"];
    $apiDescription = $yamlDocArray["info"]["description"];
    $apiVersion = $yamlDocArray["info"]["version"];
    $apiContact = $yamlDocArray["info"]["contact"]["email"];
    $apiLicense = $yamlDocArray["info"]["license"];
    $security = array();
    foreach ($yamlDocArray["security"] as $secMethod) {
      $security[] = key($secMethod);
    }
    $GLOBALS["SysConf"] = [
      "BUILD" => [
        "VERSION" => "1.0.0",
        "BRANCH" => "tree",
        "COMMIT_HASH" => "deadbeef",
        "COMMIT_DATE" => "2022/01/01 00:01 +05:30",
        "BUILD_DATE" => "2022/01/01 00:02 +05:30"
      ]
    ];
    $fossInfo = [
      "version"    => "1.0.0",
      "branchName" => "tree",
      "commitHash" => "deadbeef",
      "commitDate" => "2021-12-31T18:31:00+00:00",
      "buildDate"  => "2021-12-31T18:32:00+00:00"
    ];
    $expectedResponse = (new ResponseHelper())->withJson(array(
      "name" => $apiTitle,
      "description" => $apiDescription,
      "version" => $apiVersion,
      "security" => $security,
      "contact" => $apiContact,
      "license" => [
        "name" => $apiLicense["name"],
        "url" => $apiLicense["url"]
      ],
      "fossology" => $fossInfo
    ), 200);
    $actualResponse = $this->infoController->getInfo(null,
      new ResponseHelper());
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  public function testGetOpenApiJson()
  {
    $requestHeadersJson = new Headers();
    $requestHeadersJson->setHeader('Accept', "application/vnd.oai.openapi+json");
    $body = (new StreamFactory())->createStream();
    $requestJson = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeadersJson, [], [], $body);
    $yaml = new Parser();
    $yamlDocArray = $yaml->parseFile(self::YAML_LOC);
    $expectedResponseJson = (new ResponseHelper())
      ->withJson($yamlDocArray, 200)
      ->withHeader("Content-Disposition", "inline; filename=\"openapi.json\"");
    $actualResponseJson = $this->infoController->getOpenApi($requestJson,
      new ResponseHelper());
    $this->assertEquals($expectedResponseJson->getStatusCode(),
      $actualResponseJson->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponseJson),
      $this->getResponseJson($actualResponseJson));
    $this->assertEquals(["application/json"],
      $actualResponseJson->getHeader("Content-Type"));
  }

  public function testGetOpenApiYaml()
  {
    $requestHeadersYaml = new Headers();
    $requestHeadersYaml->setHeader('Accept', "application/vnd.oai.openapi");
    $body = (new StreamFactory())->createStream();
    $requestJson = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeadersYaml, [], [], $body);
    $expectedResponseYaml = (new ResponseHelper())
      ->withHeader("Content-Type", "application/vnd.oai.openapi;charset=utf-8")
      ->withHeader("Content-Disposition", "inline; filename=\"openapi.yaml\"")
      ->withStatus(200);
    $expectedResponseYaml->getBody()->write(file_get_contents(self::YAML_LOC));
    $actualResponseYaml = $this->infoController->getOpenApi($requestJson,
      new ResponseHelper());
    $this->assertEquals($expectedResponseYaml->getStatusCode(),
      $actualResponseYaml->getStatusCode());
    $this->assertEquals($expectedResponseYaml->getBody()->getContents(),
      $actualResponseYaml->getBody()->getContents());
    $this->assertEquals(["application/vnd.oai.openapi;charset=utf-8"],
      $actualResponseYaml->getHeader("Content-Type"));
  }
}
