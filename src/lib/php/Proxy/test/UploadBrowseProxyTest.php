<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

use Exception;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Test\TestPgDb;

class UploadBrowseProxyTest extends \PHPUnit\Framework\TestCase
{
  private $testDb;
  private $groupId = 401;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->testDb->createPlainTables( array('upload','upload_clearing','perm_upload','upload_events') );
    $this->testDb->getDbManager()->insertTableRow('upload', array('upload_pk'=>1,'upload_filename'=>'for.all','user_fk'=>1,'upload_mode'=>1,'public_perm'=>Auth::PERM_READ,'pfile_fk'=>31415));
    $this->testDb->getDbManager()->insertTableRow('upload', array('upload_pk'=>2,'upload_filename'=>'for.this','user_fk'=>1,'upload_mode'=>1,'public_perm'=>Auth::PERM_NONE));
    $this->testDb->getDbManager()->insertTableRow('perm_upload', array('perm_upload_pk'=>1, 'upload_fk'=>2,'group_fk'=>$this->groupId,'perm'=>Auth::PERM_READ));
    $this->testDb->getDbManager()->insertTableRow('upload', array('upload_pk'=>3,'upload_filename'=>'for.noone','user_fk'=>1,'upload_mode'=>1,'public_perm'=>Auth::PERM_NONE));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
  }

  public function testConstructAndSanity()
  {
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager(), true);
    assertThat($uploadBrowseProxy,is(anInstanceOf(UploadBrowseProxy::class)));
  }

  public function testUpdateTableStatus()
  {
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, 'status_fk'=> UploadStatus::OPEN));
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $uploadBrowseProxy->updateTable('status_fk', $uploadId=1, $newStatus=UploadStatus::IN_PROGRESS);
    $updatedRow = $this->testDb->getDbManager()->getSingleRow('SELECT status_fk FROM upload_clearing WHERE upload_fk=$1 AND group_fk=$2',array($uploadId,$this->groupId));
    assertThat($updatedRow['status_fk'],equalTo($newStatus));
  }

  public function testUpdateTableStatusFromRejectByUser()
  {
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, 'status_fk'=> UploadStatus::REJECTED));
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $uploadBrowseProxy->updateTable('status_fk', $uploadId=1, $newStatus=UploadStatus::IN_PROGRESS);
    $updatedRow = $this->testDb->getDbManager()->getSingleRow('SELECT status_fk FROM upload_clearing WHERE upload_fk=$1 AND group_fk=$2',array($uploadId,$this->groupId));
    assertThat($updatedRow['status_fk'],equalTo(UploadStatus::REJECTED));
  }

  public function testUpdateTableStatusByAdvisor()
  {
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, 'status_fk'=> UploadStatus::OPEN));
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::ADVISOR, $this->testDb->getDbManager());
    $uploadBrowseProxy->updateTable('status_fk', $uploadId=1, $newStatus=UploadStatus::IN_PROGRESS);
    $updatedRow = $this->testDb->getDbManager()->getSingleRow('SELECT status_fk FROM upload_clearing WHERE upload_fk=$1 AND group_fk=$2',array($uploadId,$this->groupId));
    assertThat($updatedRow['status_fk'],equalTo($newStatus));
  }

  public function testUpdateTableStatusToRejectByUser()
  {
    $this->expectException(Exception::class);
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, 'status_fk'=> UploadStatus::OPEN));
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $uploadBrowseProxy->updateTable('status_fk', $uploadId=1, $newStatus=UploadStatus::REJECTED);
  }

  public function testUpdateTableStatusToRejectByAdvisor()
  {
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, 'status_fk'=> UploadStatus::OPEN));
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::ADVISOR, $this->testDb->getDbManager());
    $uploadBrowseProxy->updateTable('status_fk', $uploadId=1, $newStatus=UploadStatus::REJECTED);
    $updatedRow = $this->testDb->getDbManager()->getSingleRow('SELECT status_fk FROM upload_clearing WHERE upload_fk=$1 AND group_fk=$2',array($uploadId,$this->groupId));
    assertThat($updatedRow['status_fk'],equalTo($newStatus));
  }

  public function testUpdateTableNonEditableColum()
  {
    $this->expectException(Exception::class);
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $uploadBrowseProxy->updateTable('nonEditableColumn', 1, 123);
  }

  public function testUpdateTableAssigneeByAdvisor()
  {
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::ADVISOR, $this->testDb->getDbManager());
    $uploadBrowseProxy->updateTable('assignee', $uploadId=1, $newAssignee=123);
    $updatedRow = $this->testDb->getDbManager()->getSingleRow('SELECT assignee FROM upload_clearing WHERE upload_fk=$1 AND group_fk=$2',array($uploadId,$this->groupId));
    assertThat($updatedRow['assignee'],equalTo($newAssignee));
  }

  public function testUpdateTableAssigneeForbidden()
  {
    $this->expectException(Exception::class);
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $uploadBrowseProxy->updateTable('assignee', 1, 123);
  }


  private function wrapperTestMoveUploadToInfinity($uploadId, $order='DESC')
  {
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, UploadBrowseProxy::PRIO_COLUMN=>1));
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>2,'group_fk'=>$this->groupId, UploadBrowseProxy::PRIO_COLUMN=>2));

    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $uploadBrowseProxy->moveUploadToInfinity($uploadId, 'DESC'==$order);

    $updatedRow = $this->testDb->getDbManager()->getSingleRow('SELECT upload_fk FROM upload_clearing WHERE group_fk=$1 ORDER BY '.UploadBrowseProxy::PRIO_COLUMN." $order LIMIT 1",array($this->groupId));
    assertThat($updatedRow['upload_fk'],equalTo($uploadId));
  }

  public function testMoveUploadToInfinityTop()
  {
    $this->wrapperTestMoveUploadToInfinity(1, 'DESC');
  }

  public function testMoveUploadToInfinityDown()
  {
    $this->wrapperTestMoveUploadToInfinity(2, 'ASC');
  }


  private function wrapperTestMoveUploadBeyond($moveUpload=4, $beyondUpload=2, $expectedPrio = 1.5)
  {
    $this->testDb->getDbManager()->insertTableRow('upload', array('upload_pk'=>4,'upload_filename'=>'for.all4','user_fk'=>1,'upload_mode'=>1,'public_perm'=>Auth::PERM_READ));
    $this->testDb->getDbManager()->insertTableRow('upload', array('upload_pk'=>5,'upload_filename'=>'for.all5','user_fk'=>1,'upload_mode'=>1,'public_perm'=>Auth::PERM_READ));

    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, UploadBrowseProxy::PRIO_COLUMN=>1));
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>2,'group_fk'=>$this->groupId, UploadBrowseProxy::PRIO_COLUMN=>2));
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>4,'group_fk'=>$this->groupId, UploadBrowseProxy::PRIO_COLUMN=>4));
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>5,'group_fk'=>$this->groupId, UploadBrowseProxy::PRIO_COLUMN=>5));

    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $uploadBrowseProxy->moveUploadBeyond($moveUpload, $beyondUpload);
    $updatedRow = $this->testDb->getDbManager()->getSingleRow('SELECT '.UploadBrowseProxy::PRIO_COLUMN.' FROM upload_clearing WHERE upload_fk=$1 AND group_fk=$2',array($moveUpload,$this->groupId));
    assertThat($updatedRow[UploadBrowseProxy::PRIO_COLUMN],equalTo($expectedPrio));

  }

  public function testMoveUploadBeyondDown()
  {
    $this->wrapperTestMoveUploadBeyond(4,2,1.5);
  }

  public function testMoveUploadBeyondUp()
  {
    $this->wrapperTestMoveUploadBeyond(2,4,4.5);
  }

  public function testMoveUploadBeyondFarDown()
  {
    $this->wrapperTestMoveUploadBeyond(4,1,0.5);
  }

  public function testMoveUploadBeyondFarUp()
  {
    $this->wrapperTestMoveUploadBeyond(4,5,5.5);
  }


  public function testGetFolderPartialQuery()
  {
    $this->testDb->createPlainTables(array('foldercontents','uploadtree'));
    $folderId = 701;
    $uploadId = 1;
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, UploadBrowseProxy::PRIO_COLUMN=>1));
    $this->testDb->getDbManager()->insertTableRow('uploadtree',array('uploadtree_pk'=>201, 'upload_fk'=>$uploadId, 'lft'=>1, 'ufile_name'=>'A.zip','pfile_fk'=>31415));
    $this->testDb->getDbManager()->insertTableRow('foldercontents',array('foldercontents_pk'=>1, 'parent_fk'=>$folderId, 'foldercontents_mode'=>2, 'child_id'=>$uploadId));
    $params = array($folderId);
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $view = $uploadBrowseProxy->getFolderPartialQuery($params);
    $row = $this->testDb->getDbManager()->getSingleRow("SELECT count(*) FROM $view", $params);
    assertThat($row['count'],equalTo(1));
  }

  public function testGetFolderPartialQueryWithUserInTwoGoodGroups()
  {
    $this->testDb->createPlainTables(array('foldercontents','uploadtree'));
    $folderId = 701;
    $uploadId = 1;
    $otherGroupId = $this->groupId+1;
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$this->groupId, UploadBrowseProxy::PRIO_COLUMN=>1));
    $this->testDb->getDbManager()->insertTableRow('upload_clearing', array('upload_fk'=>1,'group_fk'=>$otherGroupId, UploadBrowseProxy::PRIO_COLUMN=>1));
    $this->testDb->getDbManager()->insertTableRow('uploadtree',array('uploadtree_pk'=>201, 'upload_fk'=>$uploadId, 'lft'=>1, 'ufile_name'=>'A.zip','pfile_fk'=>31415));
    $this->testDb->getDbManager()->insertTableRow('foldercontents',array('foldercontents_pk'=>1, 'parent_fk'=>$folderId, 'foldercontents_mode'=>2, 'child_id'=>$uploadId));
    $params = array($folderId);
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $view = $uploadBrowseProxy->getFolderPartialQuery($params);
    $row = $this->testDb->getDbManager()->getSingleRow("SELECT count(*) FROM $view", $params);
    assertThat($row['count'],equalTo(1));
  }


  public function testGetFolderPartialQueryWithInvalidParamCount()
  {
    $this->expectException(Exception::class);
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $params = array();
    $uploadBrowseProxy->getFolderPartialQuery($params);
  }

  public function testGetStatus()
  {
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager());
    $uploadId = 1;
    assertThat($uploadBrowseProxy->getStatus($uploadId), equalTo(UploadStatus::OPEN));
    $uploadBrowseProxy->updateTable('status_fk', $uploadId, $newStatus=UploadStatus::IN_PROGRESS);
    assertThat($uploadBrowseProxy->getStatus($uploadId), equalTo($newStatus));
  }

  public function testGetStatusException()
  {
    $this->expectException(Exception::class);
    $uploadBrowseProxy = new UploadBrowseProxy($this->groupId, UserDao::USER, $this->testDb->getDbManager(), false);
    $uploadBrowseProxy->getStatus(-1);
  }
}
