<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for RestHelper
 */

namespace Fossology\UI\Api\Helper;


use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\JobDao;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;
use Symfony\Component\HttpFoundation\Session\Session;

$GLOBALS['globalSession'] = new Session();
$GLOBALS['globalSession']->set('t','t');

function plugin_find($plugin)
{
  return RestHelperTest::$functions->plugin_find($plugin);
}

/**
 * @class RestHelperTest
 * @brief Test cases for RestHelper
 */
class RestHelperTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var \Mockery\MockInterface $functions
   * Mock of public functions
   */
  public static $functions;
  /**
   * @var UploadDao $uploadDao
   * UploadDao mock
   */
  private $uploadDao;
  /**
   * @var DbHelper $dbHelper
   * DbHelper mock
   */
  private $dbHelper;
  /**
   * @var UploadPermissionDao $uploadPermissionDao
   * UploadPermissionDao mock
   */
  private $uploadPermissionDao;
  /**
   * @var FolderDao $folderDao
   * FolderDao mock
   */
  private $folderDao;
  /**
   * @var UserDao $userDao
   * UserDao mock
   */
  private $userDao;
  /**
   * @var JobDao $jobDao
   * JobDao mock
   */
  private $jobDao;
  /**
   * @var ShowJobsDao $showJobDao
   * ShowJobsDao mock
   */
  private $showJobDao;
  /**
   * @var AuthHelper $authHelper
   * AuthHelper mock
   */
  private $authHelper;
  /**
   * @var Session $session
   * Session mock
   */
  private $session;
  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;
  /**
   * @var integer $userId
   * User id
   */
  private $userId;
  /**
   * @var integer $groupId
   * Group id
   */
  private $groupId;
  /**
   * @var \Mockery\MockInterface $contentMovePlugin
   * content_move plugin mock
   */
  private $contentMovePlugin;
  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->uploadPermissionDao = M::mock(UploadPermissionDao::class);
    $this->uploadDao  = M::mock(UploadDao::class);
    $this->userDao    = M::mock(UserDao::class);
    $this->folderDao  = M::mock(FolderDao::class);
    $this->dbHelper   = M::mock(DbHelper::class);
    $this->authHelper = M::mock(AuthHelper::class);
    $this->jobDao     = M::mock(JobDao::class);
    $this->showJobDao = M::mock(ShowJobsDao::class);
    $this->session    = $GLOBALS['globalSession'];
    $this->userId     = 2;
    $this->groupId    = 2;
    self::$functions = M::mock();
    $this->contentMovePlugin = M::mock('AdminContentMove');

    $this->session->set(Auth::USER_ID, $this->userId);
    $this->session->set(Auth::GROUP_ID, $this->groupId);
    $this->authHelper->shouldReceive('getSession')->andReturn($this->session);

    self::$functions->shouldReceive('plugin_find')
      ->withArgs(['content_move'])
      ->andReturn($this->contentMovePlugin);

    $this->restHelper = new RestHelper(
      $this->uploadPermissionDao,
      $this->uploadDao,
      $this->userDao,
      $this->folderDao,
      $this->dbHelper,
      $this->authHelper,
      $this->jobDao,
      $this->showJobDao);
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
   * @test
   * -# Test for RestHelper::copyUpload()
   * -# Check if the response for RestHelper::copyUpload() is 202
   */
  public function testCopyUpload()
  {
    $uploadId = 5;
    $newFolderId = 10;
    $isCopy = true;
    $uploadContentId = 44;

    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$newFolderId, $this->userId])
      ->once()
      ->andReturn(true);
    $this->uploadPermissionDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])
      ->once()
      ->andReturn(true);
    $this->folderDao->shouldReceive('getFolderContentsId')
      ->withArgs([$uploadId, 2])
      ->once()
      ->andReturn($uploadContentId);
    $this->contentMovePlugin->shouldReceive('copyContent')
      ->withArgs([[$uploadContentId], $newFolderId, $isCopy])
      ->once()
      ->andReturn("");

    $expected = new Info(202, "Upload $uploadId will be copied to folder " .
      $newFolderId, InfoType::INFO);
    $actual = $this->restHelper->copyUpload($uploadId, $newFolderId, $isCopy);

    $this->assertEquals($expected, $actual);
  }

  /**
   * @test
   * -# Test for RestHelper::validateTokenRequest()
   * -# Check if RestHelper::validateTokenRequest() accepts valid requests
   */
  public function testValidateTokenRequest()
  {
    $tokenExpire = strftime('%Y-%m-%d', strtotime('+3 day'));
    $tokenName = "myTok";
    $tokenScope = "r";
    $tokenValidity = 30;

    $this->authHelper->shouldReceive('getMaxTokenValidity')
      ->andReturn($tokenValidity);
    $this->restHelper->validateTokenRequest($tokenExpire, $tokenName,
      $tokenScope);
  }

  /**
   * @test
   * -# Test for RestHelper::validateTokenRequest() with expire > max valid
   * -# Check if response is 400 with valid message.
   */
  public function testValidateTokenRequestMaxExpire()
  {
    $tokenExpire = strftime('%Y-%m-%d', strtotime('+10 day'));
    $tokenName = "myTok";
    $tokenScope = "read";
    $tokenValidity = 3;

    $this->authHelper->shouldReceive('getMaxTokenValidity')
      ->andReturn($tokenValidity);
    $this->expectException(HttpBadRequestException::class);

    $this->restHelper->validateTokenRequest($tokenExpire, $tokenName,
      $tokenScope);
  }

  /**
   * @test
   * -# Test for RestHelper::validateTokenRequest() with invalid date format
   * -# Check if response is 400 with valid message.
   */
  public function testValidateTokenRequestInvalidExpire()
  {
    $tokenExpire = strftime('%d-%m-%Y', strtotime('+10 day'));
    $tokenName = "myTok";
    $tokenScope = "read";
    $tokenValidity = 30;

    $this->authHelper->shouldReceive('getMaxTokenValidity')
      ->andReturn($tokenValidity);
    $this->expectException(HttpBadRequestException::class);

    $this->restHelper->validateTokenRequest($tokenExpire, $tokenName,
      $tokenScope);
  }

  /**
   * @test
   * -# Test RestHelper::validateTokenRequest() with invalid scope
   * -# Check if the response is 400 with valid message.
   */
  public function testValidateTokenRequestInvalidScope()
  {
    $tokenExpire = strftime('%Y-%m-%d', strtotime('+10 day'));
    $tokenName = "myTok";
    $tokenScope = "rread";
    $tokenValidity = 30;

    $this->authHelper->shouldReceive('getMaxTokenValidity')
      ->andReturn($tokenValidity);
    $this->expectException(HttpBadRequestException::class);

    $this->restHelper->validateTokenRequest($tokenExpire, $tokenName,
      $tokenScope);
  }
}
