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

use Mockery as M;
use Symfony\Component\Yaml\Parser;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Controllers\InfoController;
use Fossology\UI\Api\Helper\ResponseHelper;

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
   * @param Response $response
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
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  public function testGetOpenApi()
  {
    $yaml = new Parser();
    $yamlDocArray = $yaml->parseFile(self::YAML_LOC);
    $expectedResponse = (new ResponseHelper())->withJson(array($yamlDocArray), 200);
    $actualResponse = $this->infoController->getOpenApi(null,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }
}
