<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

require_once __DIR__.'/../../Plugin/FO_Plugin.php';

class UploadPermissionDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var UploadPermissionDao */
  private $uploadPermissionDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->testDb->createPlainTables(array('upload','uploadtree'));

    $this->dbManager->prepare($stmt = 'insert.upload',
        "INSERT INTO upload (upload_pk, uploadtree_tablename) VALUES ($1, $2)");
    $uploadArray = array(array(1, 'uploadtree'), array(2, 'uploadtree_a'));
    foreach ($uploadArray as $uploadEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }
    $logger = M::mock('Monolog\Logger'); // new Logger("UploadDaoTest");
    $logger->shouldReceive('debug');
    $this->uploadPermissionDao = new UploadPermissionDao($this->dbManager, $logger);

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testmakeAccessibleToGroup()
  {
    $this->testDb->createPlainTables(array('perm_upload','group_user_member'));
    $userId = 501;
    $groupId = 601;
    $groupIdAlternative = 602;
    $this->dbManager->insertTableRow('group_user_member', array('group_fk'=>$groupId,'user_fk'=>$userId,'group_perm'=>Auth::PERM_READ));
    $this->dbManager->insertTableRow('group_user_member', array('group_fk'=>$groupIdAlternative,'user_fk'=>$userId,'group_perm'=>Auth::PERM_READ));

    $unaccessibleIsAccessible = $this->uploadPermissionDao->isAccessible($uploadId=1, $groupId);
    assertThat($unaccessibleIsAccessible,equalTo(false));

    $this->uploadPermissionDao->makeAccessibleToGroup($uploadId, $groupId, Auth::PERM_WRITE);
    $accessibleIsAccessible = $this->uploadPermissionDao->isAccessible($uploadId, $groupId);
    assertThat($accessibleIsAccessible,equalTo(true));
    $stillUnaccessibleIsAccessible = $this->uploadPermissionDao->isAccessible($uploadId, $groupIdAlternative);
    assertThat($stillUnaccessibleIsAccessible,equalTo(false));

    $this->uploadPermissionDao->makeAccessibleToAllGroupsOf($uploadId, $userId);
    $nowAccessibleIsAccessible = $this->uploadPermissionDao->isAccessible($uploadId, $groupIdAlternative);
    assertThat($nowAccessibleIsAccessible,equalTo(true));
  }

  public function testDeletePermissionId()
  {
    $this->testDb->createPlainTables(array('perm_upload'));
    $this->testDb->insertData(array('perm_upload'));
    $accessibleBefore = $this->uploadPermissionDao->isAccessible($uploadId=1, $groupId=2);
    assertThat($accessibleBefore,equalTo(true));
    $this->uploadPermissionDao->updatePermissionId(1,0);
    $accessibleAfter = $this->uploadPermissionDao->isAccessible($uploadId, $groupId);
    assertThat($accessibleAfter,equalTo(false));
  }

  public function testUpdatePermissionId()
  {
    $this->testDb->createPlainTables(array('perm_upload'));
    $this->testDb->insertData(array('perm_upload'));
    $_SESSION[Auth::USER_LEVEL] = PLUGIN_DB_READ;
    $adminBefore = $this->uploadPermissionDao->isEditable($uploadId=1, $groupId=2);
    assertThat($adminBefore,equalTo(true));
    $this->uploadPermissionDao->updatePermissionId(1,Auth::PERM_READ);
    $adminNomore = $this->uploadPermissionDao->isEditable($uploadId, $groupId);
    assertThat($adminNomore,equalTo(false));
    $this->uploadPermissionDao->updatePermissionId(1,Auth::PERM_WRITE);
    $adminAgain = $this->uploadPermissionDao->isEditable($uploadId, $groupId);
    assertThat($adminAgain,equalTo(true));
  }

  public function testInsertPermission()
  {
    $this->testDb->createPlainTables(array('perm_upload'));
    $accessibleBefore = $this->uploadPermissionDao->isAccessible($uploadId=1, $groupId=2);
    assertThat($accessibleBefore,equalTo(false));
    $this->uploadPermissionDao->insertPermission($uploadId, $groupId, Auth::PERM_READ);
    $accessibleAfter = $this->uploadPermissionDao->isAccessible($uploadId, $groupId);
    assertThat($accessibleAfter,equalTo(true));
    $this->uploadPermissionDao->insertPermission($uploadId, $groupId, Auth::PERM_NONE);
    $accessibleNomore = $this->uploadPermissionDao->isAccessible($uploadId, $groupId);
    assertThat($accessibleNomore,equalTo(false));
  }

  public function testGetPublicPermission()
  {
    $this->testDb->insertData(array('upload'));
    $perm = $this->uploadPermissionDao->getPublicPermission(3);
    assertThat($perm,equalTo(0));
  }

  public function testGetPermissionGroups()
  {
    $this->testDb->createPlainTables(array('perm_upload','groups'));
    $this->testDb->insertData(array('perm_upload','groups'));
    $permissionGroups = $this->uploadPermissionDao->getPermissionGroups(1);
    assertThat($permissionGroups,is(array(2=>array('perm_upload_pk'=>1, 'perm'=>10, 'group_pk'=>2, 'group_name'=>'fossy'))));
  }

  public function testAccessibilityViaNone()
  {
    $this->testDb->createPlainTables(array('perm_upload','groups'));
    $this->testDb->insertData(array('groups'));
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_NONE;
    $accessibilityWithBadGroup = $this->uploadPermissionDao->isAccessible($uploadId=2, $groupId=2);
    assertThat($accessibilityWithBadGroup, equalTo(false));
  }

  public function testAccessibilityViaGroup()
  {
    $this->testDb->createPlainTables(array('perm_upload','groups'));
    $this->testDb->insertData(array('groups','perm_upload'));
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_NONE;
    $accessibilityByGroup = $this->uploadPermissionDao->isAccessible($uploadId=2, $groupId=2);
    assertThat($accessibilityByGroup, equalTo(true));
  }


  public function testAccessibilityViaPublicForUnqualifiedUser()
  {
    $this->testDb->createPlainTables(array('perm_upload','groups'));
    $this->testDb->insertData(array('groups'));
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_NONE;
    $accessibilityByNone = $this->uploadPermissionDao->isAccessible($uploadId=2, $groupId=2);
    assertThat($accessibilityByNone, equalTo(false));

    $this->uploadPermissionDao->setPublicPermission($uploadId, Auth::PERM_READ);
    $accessibilityByPublic = $this->uploadPermissionDao->isAccessible($uploadId, $groupId);
    assertThat($accessibilityByPublic, equalTo(false));
  }

  public function testAccessibilityViaPublicForQualifiedUser()
  {
    $this->testDb->createPlainTables(array('perm_upload','groups'));
    $this->testDb->insertData(array('groups'));
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_READ;
    $accessibilityByNone = $this->uploadPermissionDao->isAccessible($uploadId=2, $groupId=2);
    assertThat($accessibilityByNone, equalTo(false));

    $this->uploadPermissionDao->setPublicPermission($uploadId, Auth::PERM_READ);
    $accessibilityByPublic = $this->uploadPermissionDao->isAccessible($uploadId, $groupId);
    assertThat($accessibilityByPublic, equalTo(true));
  }
  /**
   * @test
   * -# Test to check if a user can edit an upload
   *    UploadPermissionDao::isEditable()
   * -# Set the user's permission level to PERM_WRITE
   * -# Check if the user has the correct permission to edit the upload
   */
  public function testIsEditable()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->testDb->createPlainTables(array('perm_upload','groups'));
    $this->testDb->insertData(array('groups','perm_upload'));
    $this->uploadPermissionDao->insertPermission(2, 3,Auth::PERM_WRITE);
    $result = $this->uploadPermissionDao->isEditable(2, 3);
    $this->assertNotNull($result);
    $this->assertTrue($result);
  }
  /**
   * @test
   * -# Test to verify that access is granted when the correct permission is set using UploadPermissionDao::isAccessible().
   * -# Insert a write permission (PERM_WRITE) for a user (user ID: 2) and upload (upload ID: 3).
   * -# Ensure that the isAccessible() method returns true when the user has write access.
   */
  public function testIsAccessibleTrue()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->testDb->createPlainTables(array('perm_upload','groups'));
    $this->testDb->insertData(array('groups','perm_upload'));
    $this->uploadPermissionDao->insertPermission(2, 3,Auth::PERM_WRITE);
    $result = $this->uploadPermissionDao->isAccessible(2, 3);
    $this->assertNotNull($result);
    $this->assertTrue($result);
  }
  /**
   * @test
   * -# Test to verify access permissions for an upload using UploadPermissionDao::isAccessible().
   * -# Insert a permission with no access (PERM_NONE) for a user (user ID: 2).
   * -# Ensure that the isAccessible() method correctly returns false when no permission is granted.
   */
  public function testIsAccessibleFalse()
  {
    $this->testDb->createPlainTables(array('perm_upload','groups'));
    $this->testDb->insertData(array('groups','perm_upload'));
    $this->uploadPermissionDao->insertPermission(2, 3,Auth::PERM_NONE);
    $result = $this->uploadPermissionDao->isAccessible(2, 3);
    $this->assertNotNull($result);
    $this->assertFalse($result);
  }
}
