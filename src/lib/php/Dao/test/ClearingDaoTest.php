<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Andreas Würl, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use Mockery as M;
use Mockery\MockInterface;

class ClearingDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var  TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao|MockInterface */
  private $uploadDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var int */
  private $now;
  /** @var array */
  private $items;
  private $groupId = 601;


  protected function setUp() : void
  {
    $this->uploadDao = M::mock(UploadDao::class);
    $this->uploadDao->shouldReceive('getUploadEntry')->withAnyArgs()->andReturn(["upload_fk" => 4]);

    $logger = new Logger('default');
    $logger->pushHandler(new ErrorLogHandler());

    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);

    $this->testDb->createPlainTables(
        array(
            'clearing_decision',
            'clearing_decision_event',
            'clearing_decision_type',
            'clearing_event',
            'clearing_licenses',
            'highlight_bulk',
            'license_ref',
            'license_ref_bulk',
            'license_set_bulk',
            'users',
            'uploadtree'
        ));

    $this->testDb->createInheritedTables();

    $userArray = array(
        array('myself', 1),
        array('in_same_group', 2),
        array('in_trusted_group', 3));
    foreach ($userArray as $ur) {
      $this->dbManager->insertInto('users', 'user_name, root_folder_fk', $ur);
    }

    $refArray = array(
        array(401, 'FOO', 'FOO', 'foo full', 'foo text'),
        array(402, 'BAR', 'BAR', 'bar full', 'bar text'),
        array(403, 'BAZ', 'BAZ', 'baz full', 'baz text'),
        array(404, 'QUX', 'QUX', 'qux full', 'qux text')
    );
    foreach ($refArray as $params) {
      $this->dbManager->insertInto('license_ref', 'rf_pk, rf_shortname, rf_spdx_id, rf_fullname, rf_text', $params, $logStmt = 'insert.ref');
    }

    $modd = 536888320;
    $modf = 33188;

    /*                          (pfile,item,lft,rgt)
      upload101:   upload101/    (  0, 299,  1,  4)
                   Afile         (201, 301,  1,  2)
                   Bfile         (202, 302,  3,  4)
      upload102:   upload102/    (  0, 300,  1,  8)
                   Afile         (201, 303,  1,  2)
                   A-dir/        (  0, 304,  3,  6)
                   A-dir/Afile   (201, 305,  4,  5)
                   Bfile         (202, 306,  7,  8)
    */
    $this->items = array(
        299=>array(101, 299,   0, $modd, 1, 4, "upload101"),
        300=>array(102, 300,   0, $modd, 1, 8, "upload102"),
        301=>array(101, 301, 201, $modf, 1, 2, "Afile"),
        302=>array(101, 302, 202, $modf, 3, 4, "Bfile"),
        303=>array(102, 303, 201, $modf, 1, 2, "Afile"),
        304=>array(102, 304,   0, $modd, 3, 6, "A-dir"),
        305=>array(102, 305, 201, $modf, 4, 5, "Afile"),
        306=>array(102, 306, 202, $modf, 7, 8, "Bfile"),
    );
    foreach ($this->items as $ur) {
      $this->dbManager->insertInto('uploadtree', 'upload_fk,uploadtree_pk,pfile_fk,ufile_mode,lft,rgt,ufile_name', $ur);
    }
    $this->now = time();

    $bulkLicArray = array(
        array(1, 401, 'TextFOO', false, 101, 299, $this->groupId),
        array(2, 402, 'TextBAR', false, 101, 299, $this->groupId),
        array(3, 403, 'TextBAZ', true,  101, 301, $this->groupId),
        array(4, 403, 'TextBAZ', false, 101, 299, $this->groupId),
        array(5, 404, 'TextQUX', true,  101, 299, $this->groupId),
        array(6, 401, 'TexxFOO', true,  101, 302, $this->groupId),
        array(7, 403, 'TextBAZ', false, 102, 300, $this->groupId),
        array(8, 403, 'TextBAZ', true,  102, 306, $this->groupId)
    );
    foreach ($bulkLicArray as $params) {
      $paramsRef = array($params[0], $params[2], $params[4], $params[5], $params[6]);
      $paramsSet = array($params[0], $params[1], $params[3]);
      $this->dbManager->insertInto('license_ref_bulk', 'lrb_pk, rf_text, upload_fk, uploadtree_fk, group_fk', $paramsRef, 'insert.bulkref');
      $this->dbManager->insertInto('license_set_bulk', 'lrb_fk, rf_fk, removing', $paramsSet, 'insert.bulkset');
    }

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  private function insertBulkEvents()
  {
    $bulkFindingsArray = array(
        array(1, 5001),
        array(1, 5001),// a second bulk match in the same file in a different place
        array(1, 5002),
        array(1, 5003),
        array(4, 5004),
        array(7, 5005)
    );
    foreach ($bulkFindingsArray as $params) {
      $this->dbManager->insertInto('highlight_bulk', 'lrb_fk, clearing_event_fk', $params, $logStmt = 'insert.bulkfinds');
    }

    $bulkClearingEvents = array(
        array(5001, 301),
        array(5002, 302),
        array(5003, 303),
        array(5004, 301),
        array(5005, 305)
    );
    foreach ($bulkClearingEvents as $params) {
      $this->dbManager->insertInto('clearing_event', 'clearing_event_pk, uploadtree_fk', $params, $logStmt = 'insert.bulkevents');
    }
  }

  private function buildProposals($licProp,$i=0)
  {
    foreach ($licProp as $lp) {
      list($item,$user,$group,$rf,$isRm,$t) = $lp;
      $this->dbManager->insertInto('clearing_event',
          'clearing_event_pk, uploadtree_fk, user_fk, group_fk, rf_fk, removed, type_fk, date_added',
          array($i,$item,$user,$group,$rf,$isRm,1, $this->getMyDate($this->now+$t)));
      $i++;
    }
  }

  private function buildDecisions($cDec,$j=0)
  {
    foreach ($cDec as $cd) {
      list($item,$user,$group,$type,$t,$scope,$eventIds) = $cd;
      $this->dbManager->insertInto('clearing_decision',
          'clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, date_added, scope',
          array($j,$item,$this->items[$item][2],$user,$group,$type, $this->getMyDate($this->now+$t),$scope));
      foreach ($eventIds as $eId) {
        $this->dbManager->insertTableRow('clearing_decision_event', array('clearing_decision_fk' => $j, 'clearing_event_fk' => $eId));
      }
      $j++;
    }
  }

  function tearDown() : void
  {
    $this->testDb = null;
    $this->dbManager = null;
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  private function getMyDate($ts)
  {
    return date('Y-m-d H:i:s T',$ts);
  }

  public function testRelevantClearingEvents()
  {
    $groupId = 701;
    $this->buildProposals(array(
        array(301,1,$groupId,401,false,-99),
        array(301,2,$groupId,402,true,-98),
        array(301,2,$groupId,401,true,-97)
    ),$firstEventId=0);
    $this->buildDecisions(array(
        array(301,1,$groupId,DecisionTypes::IDENTIFIED,-90,DecisionScopes::REPO,array($firstEventId,$firstEventId+1,$firstEventId+2))
    ));
    $itemTreeBounds = M::mock(ItemTreeBounds::class);
    $itemTreeBounds->shouldReceive('getItemId')->andReturn(301);
    $itemTreeBounds->shouldReceive('getUploadTreeTableName')->andReturn('uploadtree');
    $itemTreeBounds->shouldReceive('containsFiles')->andReturn(false);
    $itemTreeBounds->shouldReceive('getUploadId')->andReturn($this->items[301][0]);
    $itemTreeBounds->shouldReceive('getLeft')->andReturn($this->items[301][4]);
    $itemTreeBounds->shouldReceive('getRight')->andReturn($this->items[301][5]);
    $this->uploadDao->shouldReceive('getGlobalDecisionSettingsFromInfo')
      ->withArgs([$this->items[301][0]])->andReturn(false);

    $events1 = $this->clearingDao->getRelevantClearingEvents($itemTreeBounds, $groupId);

    assertThat($events1, arrayWithSize(2));
    assertThat($events1, hasKeyInArray(401));
    assertThat($events1, hasKeyInArray(402));
    assertThat($events1[401], is(anInstanceOf(ClearingEvent::class)));
    assertThat($events1[402]->getEventId(), is($firstEventId+1));
    assertThat($events1[401]->getEventId(), is($firstEventId+2));
  }

  function testWip()
  {
    $groupId = 701;
    $this->buildProposals(array(
        array(301,1,$groupId,401,false,-99),
        array(301,1,$groupId,402,false,-98),
        array(301,1,$groupId,401,true,-89),
    ),$firstEventId=0);
    $this->buildDecisions(array(
        array(301,1,$groupId,DecisionTypes::IDENTIFIED,-90,DecisionScopes::REPO,array($firstEventId,$firstEventId+1))
    ));
    $watchThis = $this->clearingDao->isDecisionCheck(301, $groupId, DecisionTypes::WIP);
    assertThat($watchThis,is(FALSE));
    $watchOther = $this->clearingDao->isDecisionCheck(303, $groupId, DecisionTypes::WIP);
    assertThat($watchOther,is(FALSE));
    $this->buildProposals(array(
        array(301,1,$groupId,403,false,-89),
    ),$firstEventId+3);
    $this->clearingDao->markDecisionAsWip(301, 1, $groupId);
    $watchThisNow = $this->clearingDao->isDecisionCheck(301, $groupId, DecisionTypes::WIP);
    assertThat($watchThisNow,is(TRUE));
    $watchOtherNow = $this->clearingDao->isDecisionCheck(303, $groupId, DecisionTypes::WIP);
    assertThat($watchOtherNow,is(FALSE));
  }

  private function collectBulkLicenses($bulks)
  {
    $bulkLics = array();
    foreach ($bulks as $bulk) {
      if (array_key_exists('removedLicenses', $bulk)) {
        $bulkLics = array_merge($bulkLics, $bulk['removedLicenses']);
      }
      if (array_key_exists('addedLicenses', $bulk)) {
        $bulkLics = array_merge($bulkLics, $bulk['addedLicenses']);
      }
    }
    return $bulkLics;
  }

  public function testBulkHistoryWithoutMatches()
  {
    $treeBounds = M::mock(ItemTreeBounds::class);
    $treeBounds->shouldReceive('getItemId')->andReturn(301);
    $treeBounds->shouldReceive('getLeft')->andReturn(1);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(101);
    $bulks = $this->clearingDao->getBulkHistory($treeBounds, $this->groupId);

    $bulkMatched = array_map(function($bulk){
      return $bulk['matched'];
    }, $bulks);
    $bulkText = array_map(function($bulk){
      return $bulk['text'];
    }, $bulks);

    assertThat($bulkMatched, arrayContaining(false, false, false, false, false));
    assertThat($this->collectBulkLicenses($bulks), arrayContaining('FOO', 'BAR', 'BAZ', 'BAZ', 'QUX'));
    assertThat($bulkText, arrayContaining('TextFOO', 'TextBAR', 'TextBAZ', 'TextBAZ', 'TextQUX'));
  }

  public function testBulkHistoryWithoutMatchesFromDifferentFolder()
  {
    $treeBounds = M::mock(ItemTreeBounds::class);
    $treeBounds->shouldReceive('getItemId')->andReturn(305);
    $treeBounds->shouldReceive('getLeft')->andReturn(4);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(102);
    $bulks = $this->clearingDao->getBulkHistory($treeBounds, $this->groupId);

    $bulkMatched = array_map(function($bulk){
      return $bulk['matched'];
    }, $bulks);
    assertThat($bulkMatched, arrayContaining(false));
  }

  public function testBulkHistoryWithAMatch()
  {
    $this->insertBulkEvents();

    $treeBounds = M::mock(ItemTreeBounds::class);
    $treeBounds->shouldReceive('getItemId')->andReturn(301);
    $treeBounds->shouldReceive('getLeft')->andReturn(1);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(101);
    $bulks = $this->clearingDao->getBulkHistory($treeBounds, $this->groupId);

    $clearingEventIds = array_map(function($bulk){
      return $bulk['id'];
    }, $bulks);
    $bulkMatched = array_map(function($bulk){
      return $bulk['matched'];
    }, $bulks);
    $bulkLicDirs = array_map(function($bulk){
      return count($bulk['removedLicenses'])>0;
    }, $bulks);
    $bulkTried = array_map(function($bulk){
      return $bulk['tried'];
    }, $bulks);

    assertThat($clearingEventIds, arrayContaining(5001, null, null, 5004, null));
    assertThat($bulkMatched, arrayContaining(true, false, false, true, false));
    assertThat($this->collectBulkLicenses($bulks), arrayContaining('FOO', 'BAR', 'BAZ', 'BAZ', 'QUX'));
    assertThat($bulkLicDirs, arrayContaining(false, false, true, false, true));
    assertThat($bulkTried, arrayContaining(true, true, true, true, true));
  }

  public function testBulkHistoryWithAMatchReturningAlsoNotTried()
  {
    $this->insertBulkEvents();

    $treeBounds = M::mock(ItemTreeBounds::class);
    $treeBounds->shouldReceive('getItemId')->andReturn(301);
    $treeBounds->shouldReceive('getLeft')->andReturn(1);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(101);
    $bulks = $this->clearingDao->getBulkHistory($treeBounds, $this->groupId, false);

    $clearingEventIds = array_map(function($bulk){
      return $bulk['id'];
    }, $bulks);
    $bulkMatched = array_map(function($bulk){
      return $bulk['matched'];
    }, $bulks);
    $bulkLicDirs = array_map(function($bulk){
      return count($bulk['removedLicenses'])>0;
    }, $bulks);
    $bulkTried = array_map(function($bulk){
      return $bulk['tried'];
    }, $bulks);

    assertThat($clearingEventIds, arrayContaining(5001, null, null, 5004, null, null));
    assertThat($bulkMatched, arrayContaining(true, false, false, true, false, false));
    assertThat($this->collectBulkLicenses($bulks), arrayContaining('FOO', 'BAR', 'BAZ', 'BAZ', 'QUX', 'FOO'));
    assertThat($bulkLicDirs, arrayContaining(false, false, true, false, true, true));
    assertThat($bulkTried, arrayContaining(true, true, true, true, true, false));
  }

  public function testGetClearedLicenseMultiplicities()
  {
    $user = 1;
    $groupId = 601;
    $rf = 401;
    $isRm = false;
    $t = -10815;
    $this->buildProposals(array(array(303,$user,$groupId,$rf,$isRm,$t),
        array(305,$user,$groupId,$rf,$isRm,$t+1)),$eventId=0);
    $type = DecisionTypes::IDENTIFIED;
    $scope = DecisionScopes::ITEM;
    $this->buildDecisions(array(array(303,$user,$groupId,$type,$t,$scope,array($eventId)),
        array(305,$user,$groupId,$type,$t,$scope,array($eventId+1))));
    $treeBounds = M::mock(ItemTreeBounds::class);

    $treeBounds->shouldReceive('getLeft')->andReturn(1);
    $treeBounds->shouldReceive('getRight')->andReturn(8);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(102);

    $this->uploadDao->shouldReceive('getGlobalDecisionSettingsFromInfo')
      ->withArgs([102])->andReturn(false);

    $map = $this->clearingDao->getClearedLicenseIdAndMultiplicities($treeBounds, $groupId);
    assertThat($map, is(array('FOO'=>array('count'=>2,'shortname'=>'FOO','spdx_id'=>'FOO','rf_pk'=>401))));
  }

  public function testGetClearedLicenses()
  {
    $user = 1;
    $groupId = 601;
    $rf = 401;
    $isRm = false;
    $t = -10815;
    $item = 303;
    $this->buildProposals(array(array($item,$user,$groupId,$rf,$isRm,$t),
        array($item,$user,$groupId,$rf+1,!$isRm,$t+1)),$eventId=0);
    $type = DecisionTypes::IDENTIFIED;
    $scope = DecisionScopes::ITEM;
    $this->buildDecisions(array(  array( $item,$user,$groupId,$type,$t,$scope,array($eventId,$eventId+1) )  ));
    $treeBounds = M::mock(ItemTreeBounds::class);

    $treeBounds->shouldReceive('getLeft')->andReturn(1);
    $treeBounds->shouldReceive('getRight')->andReturn(8);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(102);

    $this->uploadDao->shouldReceive('getGlobalDecisionSettingsFromInfo')
      ->withArgs([102])->andReturn(false);

    $map = $this->clearingDao->getClearedLicenses($treeBounds, $groupId);
    assertThat($map, equalTo(array(new LicenseRef($rf,'FOO','foo full', 'FOO'))));
  }


  public function testMainLicenseIds()
  {
    $this->testDb->createPlainTables(array('upload_clearing_license'));
    $uploadId = 101;
    $mainLicIdsInitially = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    assertThat($mainLicIdsInitially, is(emptyArray()));

    $this->clearingDao->makeMainLicense($uploadId,$this->groupId,$licenseId=402);
    $mainLicIdsAfterAddingOne = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    assertThat($mainLicIdsAfterAddingOne, arrayContaining(array($licenseId)));

    $this->clearingDao->makeMainLicense($uploadId,$this->groupId,$licenseId);
    $mainLicIdsAfterAddingOneTwice = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    assertThat($mainLicIdsAfterAddingOneTwice, is(arrayWithSize(1)));

    $this->clearingDao->makeMainLicense($uploadId,$this->groupId,$licenseId2=403);
    $mainLicIdsAfterAddingOther = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    assertThat($mainLicIdsAfterAddingOther, arrayContainingInAnyOrder(array($licenseId,$licenseId2)));

    $this->clearingDao->removeMainLicense($uploadId,$this->groupId,$licenseId2);
    $mainLicIdsAfterRemovingOne = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    assertThat($mainLicIdsAfterRemovingOne, is(arrayWithSize(1)));

    $this->clearingDao->removeMainLicense($uploadId,$this->groupId,$licenseId2);
    $mainLicIdAfterRemovingSomethingNotInSet = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    assertThat($mainLicIdAfterRemovingSomethingNotInSet, is(arrayWithSize(1)));

    $this->clearingDao->removeMainLicense($uploadId,$this->groupId+1,$licenseId);
    $mainLicIdAfterInsertToOtherGroup = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    assertThat($mainLicIdAfterInsertToOtherGroup, is(arrayWithSize(1)));

    $this->clearingDao->removeMainLicense($uploadId+1,$this->groupId,$licenseId);
    $mainLicIdAfterInsertToOtherUpload = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    assertThat($mainLicIdAfterInsertToOtherUpload, is(arrayWithSize(1)));
  }

  public function testupdateClearingEvent()
  {
    $this->testDb->createSequences(array('clearing_event_clearing_event_pk_seq'));
    $this->testDb->createConstraints(array('clearing_event_pkey'));
    $this->dbManager->queryOnce("ALTER TABLE clearing_event ALTER COLUMN clearing_event_pk SET DEFAULT nextval('clearing_event_clearing_event_pk_seq'::regclass)");

    $this->clearingDao->updateClearingEvent($uploadTreeId=301, $userId=1, $groupId=1, $licenseId=402, $what='comment', $changeCom='abc123');
    $rowPast = $this->dbManager->getSingleRow('SELECT * FROM clearing_event WHERE uploadtree_fk=$1 AND rf_fk=$2 ORDER BY clearing_event_pk DESC LIMIT 1',array($uploadTreeId,$licenseId),__METHOD__.'beforeReportinfo');
    assertThat($rowPast['comment'],equalTo($changeCom));

    $this->clearingDao->updateClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, $what='reportinfo', $changeRep='def456');
    $rowFuture = $this->dbManager->getSingleRow('SELECT * FROM clearing_event WHERE uploadtree_fk=$1 AND rf_fk=$2 ORDER BY clearing_event_pk DESC LIMIT 1',array($uploadTreeId,$licenseId),__METHOD__.'afterReportinfo');
    assertThat($rowFuture['comment'],equalTo($changeCom));
    assertThat($rowFuture['reportinfo'],equalTo($changeRep));
  }
}
