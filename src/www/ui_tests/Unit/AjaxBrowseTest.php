<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Fossology\UI\Ajax\AjaxBrowse;
use Mockery as M;
use Symfony\Component\HttpFoundation\Request;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-menu.php');

/**
 * @class AjaxBrowseTest
 * @brief Test for AjaxBrowse::handle() comment guard
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AjaxBrowseTest extends \PHPUnit\Framework\TestCase
{
  /** @var AjaxBrowse */
  private $plugin;

  /** @var DbManager|M\MockInterface */
  private $dbManager;

  /** @var UploadDao|M\MockInterface */
  private $uploadDao;

  protected function setUp(): void
  {
    if (!class_exists(AjaxBrowse::class, false)) {
      $GLOBALS['SysConf'] = [];
      $GLOBALS['container'] = new class {
        public function get($name)
        {
          return new \stdClass();
        }
      };
      require_once(__DIR__ . '/../../ui/async/AjaxBrowse.php');
    }

    global $MenuList, $Plugins;
    $MenuList = array();
    $Plugins = array();

    $GLOBALS['SysConf']['auth'] = [Auth::USER_ID => 1, Auth::GROUP_ID => 2];
    $this->dbManager = M::mock(DbManager::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->plugin = (new \ReflectionClass(AjaxBrowse::class))
      ->newInstanceWithoutConstructor();
    Reflectory::setObjectsProperty($this->plugin, 'dbManager', $this->dbManager);
    Reflectory::setObjectsProperty($this->plugin, 'uploadDao', $this->uploadDao);

    // handle() first reads group_perm and checks access
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/group_perm/'), M::any(), M::any())
      ->andReturn(['group_perm' => 1]);
    $this->uploadDao->shouldReceive('isAccessible')->andReturn(true);
  }

  protected function tearDown(): void
  {
    M::close();
  }

  /**
   * @test
   * -# Change status to Closed with a blank comment.
   * -# Check that handle() returns 400 and upload_clearing is not updated.
   */
  public function testBlankCommentOnCloseReturns400(): void
  {
    $this->uploadDao->shouldReceive('getStatus')->andReturn(UploadStatus::OPEN);
    $this->dbManager->shouldNotReceive('getSingleRow')
      ->with(M::pattern('/UPDATE upload_clearing/'), M::any(), M::any());

    $request = new Request(['uploadId' => 5,
      'statusId' => UploadStatus::CLOSED, 'commentText' => '   ']);
    $response = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'handle', [$request]);
    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * @test
   * -# Keep the same status with an empty comment.
   * -# Check that the UPDATE runs with '' so the comment gets cleared.
   */
  public function testEmptyCommentWithSameStatusClearsIt(): void
  {
    $this->uploadDao->shouldReceive('getStatus')
      ->andReturn(UploadStatus::IN_PROGRESS);
    // UploadBrowseProxy's constructor fills missing upload_clearing rows
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO upload_clearing/'), M::any());
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/UPDATE upload_clearing/'),
        [UploadStatus::IN_PROGRESS, '', 2, 5], M::any())
      ->once();

    $request = new Request(['uploadId' => 5,
      'statusId' => UploadStatus::IN_PROGRESS, 'commentText' => '']);
    $response = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'handle', [$request]);
    $this->assertEquals(200, $response->getStatusCode());
  }
}
