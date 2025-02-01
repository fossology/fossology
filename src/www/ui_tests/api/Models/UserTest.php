<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for User model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Helper\DbHelper;
use Mockery as M;

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/Plugin/FO_Plugin.php';

/**
 * @class UserTest
 * @brief Tests for User model
 */
class UserTest extends \PHPUnit\Framework\TestCase
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
   * @var UserDao $userDao
   * UserDao mock
   */
  private $userDao;

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
    $this->restHelper = M::mock(RestHelper::class);
    $this->userDao = M::mock(UserDao::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getUserDao')
      ->andReturn($this->userDao);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
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
  /**
   * @test
   * -# Test the data format returned by User::getArray($version) model when $version is V1
   * -# Create expected array
   * -# Create test object and set the values
   * -# Get the array from object and match with expected array
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test the data format returned by User::getArray($version) model when $version is V2
   * -# Create expected array
   * -# Create test object and set the values
   * -# Get the array from object and match with expected array
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * @param $version version to test
   * @return void
   * -# Test the data format returned by User::getArray($version) model
   */
  private function testDataFormat($version)
  {
    if($version==ApiVersion::V1){
      $expectedCurrentUser = [
        "id"                       => 2,
        "name"                     => 'fossy',
        "description"              => 'super user',
        "email"                    => 'fossy@localhost',
        "accessLevel"              => 'admin',
        "rootFolderId"             => 2,
        "defaultGroup"             => 0,
        "emailNotification"        => true,
        "agents"                   => [
          "bucket"                 => true,
          "copyright_email_author" => true,
          "ecc"                    => false,
          "keyword"                => false,
          "mimetype"               => false,
          "monk"                   => false,
          "nomos"                  => true,
          "ojo"                    => true,
          "package"                => false,
          "heritage"               => false,
          "patent"                 => false,
          "scanoss"                => false,
          "reso"                   => false,
          "compatibility"          => false
        ]
      ];
    } else{
      $expectedCurrentUser = [
        "id"                     => 2,
        "name"                   => 'fossy',
        "description"            => 'super user',
        "email"                  => 'fossy@localhost',
        "accessLevel"            => 'admin',
        "rootFolderId"           => 2,
        "defaultGroup"           => "fossy",
        "emailNotification"      => true,
        "agents"                            => [
          "bucket"               => true,
          "copyrightEmailAuthor" => true,
          "ecc"                  => false,
          "keyword"              => false,
          "mimetype"             => false,
          "monk"                 => false,
          "nomos"                => true,
          "ojo"                  => true,
          "pkgagent"             => false,
          "ipra"                 => false,
          "softwareHeritage"     => false,
          "scanoss"              => false,
          "reso"                 => false,
          "compatibility"        => false
        ]
      ];
    }
    $expectedNonAdminUser = [
      "id"           => 8,
      "name"         => 'userii',
      "description"  => 'very useri',
      "defaultGroup" => 0,
    ];

    $actualCurrentUserV1 = new User(2, 'fossy', 'super user', 'fossy@localhost',
      PLUGIN_DB_ADMIN, 2, true, "bucket,copyright,nomos,ojo", 0);

    $this->userDao->shouldReceive('getGroupNameById')->withArgs([0])->andReturn("fossy");

    $actualCurrentUserV2 = new User(2, 'fossy', 'super user', 'fossy@localhost',
      PLUGIN_DB_ADMIN, 2, true, "bucket,copyright,nomos,ojo", "fossy");
    $actualNonAdminUser = new User(8, 'userii', 'very useri', null, null, null,
      null, null, 0);
    if ($version == ApiVersion::V2) {
      $this->assertEquals($expectedCurrentUser, $actualCurrentUserV2->getArray($version));
    } else {
      $this->assertEquals($expectedCurrentUser, $actualCurrentUserV1->getArray($version));
    }
    $this->assertEquals($expectedNonAdminUser, $actualNonAdminUser->getArray());
  }
}
