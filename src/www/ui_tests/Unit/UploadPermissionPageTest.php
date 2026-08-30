<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-menu.php');

/**
 * @class UploadPermissionPageTest
 * @brief Test for UploadPermissionPage::insertPermission(), covering the
 *        broken upload membership check fix
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * Regression coverage for: insertPermission() must confirm the target
 * upload id is actually present in the caller's accessible upload list,
 * not merely that the list is non-empty. Before the fix, the loop only
 * checked truthiness of each entry's own upload_pk, so any non-empty
 * but unrelated list let the caller change permissions on an arbitrary
 * upload id they were never shown to have access to.
 */
class UploadPermissionPageTest extends \PHPUnit\Framework\TestCase
{
  /** @var int */
  private $assertCountBefore;
  /** @var UploadPermissionPage */
  private $page;

  protected function setUp(): void
  {
    if (!class_exists('UploadPermissionPage', false)) {
      $GLOBALS['SysConf'] = [];
      $GLOBALS['container'] = new class {
        public function get($name)
        {
          return new \stdClass();
        }
      };
      require_once(__DIR__ . '/../../ui/page/UploadPermissionPage.php');
    }

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    global $MenuList, $Plugins;
    $MenuList = array();
    $Plugins = array();

    // Bypass DefaultPlugin::__construct(): insertPermission() only needs
    // uploadPermDao, which is injected directly in each test below.
    $this->page = (new \ReflectionClass('UploadPermissionPage'))->newInstanceWithoutConstructor();
  }

  protected function tearDown(): void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  private function accessibleUploadList()
  {
    return [
      ['upload_pk' => 11, 'name' => 'owned-upload-a.zip'],
      ['upload_pk' => 12, 'name' => 'owned-upload-b.zip'],
    ];
  }

  /**
   * @test
   * -# Call insertPermission() for an upload id that is not present in
   *    the caller's accessible upload list, only unrelated ids are.
   * -# Check that it throws instead of granting the permission. This is
   *    the exact scenario from the reported vulnerability, a non-empty
   *    but unrelated list must not authorize an arbitrary upload id.
   */
  public function testRejectsUploadNotInAccessibleList()
  {
    $unrelatedUploadId = 999;

    $uploadPermDao = M::mock(UploadPermissionDao::class);
    $uploadPermDao->shouldNotReceive('insertPermission');
    Reflectory::setObjectsProperty($this->page, 'uploadPermDao', $uploadPermDao);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('This upload is missing or inaccessible');

    $this->page->insertPermission(4, $unrelatedUploadId, Auth::PERM_ADMIN,
      $this->accessibleUploadList());
  }

  /**
   * @test
   * -# Call insertPermission() for an upload id that genuinely is one of
   *    the entries in the accessible upload list.
   * -# Check that the permission is granted for that exact upload id.
   */
  public function testGrantsPermissionForUploadInAccessibleList()
  {
    $groupId = 4;
    $accessibleUploadId = 12;
    $permission = Auth::PERM_WRITE;

    $uploadPermDao = M::mock(UploadPermissionDao::class);
    $uploadPermDao->shouldReceive('insertPermission')
      ->withArgs([$accessibleUploadId, $groupId, $permission])->once();
    Reflectory::setObjectsProperty($this->page, 'uploadPermDao', $uploadPermDao);

    $this->page->insertPermission($groupId, $accessibleUploadId, $permission,
      $this->accessibleUploadList());
  }

  /**
   * @test
   * -# Call insertPermission() with an empty accessible upload list.
   * -# Check that it still throws, the pre-existing empty-list guard
   *    must keep working after the fix.
   */
  public function testRejectsWhenAccessibleListIsEmpty()
  {
    $uploadPermDao = M::mock(UploadPermissionDao::class);
    $uploadPermDao->shouldNotReceive('insertPermission');
    Reflectory::setObjectsProperty($this->page, 'uploadPermDao', $uploadPermDao);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('This upload is missing or inaccessible');

    $this->page->insertPermission(4, 12, Auth::PERM_ADMIN, []);
  }
}
