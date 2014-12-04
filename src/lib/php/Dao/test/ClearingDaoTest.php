<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl, Johannes Najjar

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Dao;

use DateTime;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Mockery\MockInterface;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class ClearingDaoTest extends \PHPUnit_Framework_TestCase
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


  public function setUp()
  {
    $this->uploadDao = M::mock(UploadDao::classname());

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
            'users',
            'uploadtree'
        ));
    
    $this->testDb->createInheritedTables();

    $this->testDb->insertData(array('clearing_decision_type'));

    $userArray = array(
        array('myself', 1),
        array('in_same_group', 2),
        array('in_trusted_group', 3));
    foreach ($userArray as $ur)
    {
      $this->dbManager->insertInto('users', 'user_name, root_folder_fk', $ur);
    }

    $refArray = array(
        array(401, 'FOO', 'foo text'),
        array(402, 'BAR', 'bar text'),
        array(403, 'BAZ', 'baz text'),
        array(404, 'QUX', 'qux text')
    );
    foreach ($refArray as $params)
    {
      $this->dbManager->insertInto('license_ref', 'rf_pk, rf_shortname, rf_text', $params, $logStmt = 'insert.ref');
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
    foreach ($this->items as $ur)
    {
      $this->dbManager->insertInto('uploadtree', 'upload_fk,uploadtree_pk,pfile_fk,ufile_mode,lft,rgt,ufile_name', $ur);
    }
    $this->now = time();

    $bulkLicArray = array(
        array(1, 401, 'TextFOO', false, 101, 299),
        array(2, 402, 'TextBAR', false, 101, 299),
        array(3, 403, 'TextBAZ', true,  101, 301),
        array(4, 403, 'TextBAZ', false, 101, 299),
        array(5, 404, 'TextQUX', true,  101, 299),
        array(6, 401, 'TexxFOO', true,  101, 302),
        array(7, 403, 'TextBAZ', false, 102, 300),
        array(8, 403, 'TextBAZ', true,  102, 306)
    );
    foreach ($bulkLicArray as $params)
    {
      $this->dbManager->insertInto('license_ref_bulk', 'lrb_pk, rf_fk, rf_text, removing, upload_fk, uploadtree_fk', $params, $logStmt = 'insert.bulkref');
    }
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
    foreach ($bulkFindingsArray as $params)
    {
      $this->dbManager->insertInto('highlight_bulk', 'lrb_fk, clearing_event_fk', $params, $logStmt = 'insert.bulkfinds');
    }

    $bulkClearingEvents = array(
        array(5001, 301),
        array(5002, 302),
        array(5003, 303),
        array(5004, 301),
        array(5005, 305)
    );
    foreach ($bulkClearingEvents as $params)
    {
      $this->dbManager->insertInto('clearing_event', 'clearing_event_pk, uploadtree_fk', $params, $logStmt = 'insert.bulkevents');
    }
  }

  private function buildProposals($licProp,$i=0)
  {
    foreach($licProp as $lp){
      list($item,$user,$group,$rf,$isRm,$t) = $lp;
      $this->dbManager->insertInto('clearing_event',
          'clearing_event_pk, uploadtree_fk, user_fk, group_fk, rf_fk, removed, type_fk, date_added',
          array($i,$item,$user,$group,$rf,$isRm,1, $this->getMyDate($this->now+$t)));
      $i++;
    }
  }

  private function buildDecisions($cDec,$j=0)
  {
    foreach($cDec as $cd){
      list($item,$user,$group,$type,$t,$scope,$eventIds) = $cd;
      $this->dbManager->insertInto('clearing_decision',
          'clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, date_added, scope',
          array($j,$item,$this->items[$item][2],$user,$group,$type, $this->getMyDate($this->now+$t),$scope));
      foreach ($eventIds as $eId)
      {
        $this->dbManager->insertTableRow('clearing_decision_event', array('clearing_decision_fk' => $j, 'clearing_event_fk' => $eId));
      }
      $j++;
    }
  }

  function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function getMyDate($in)
  {
    $date = new DateTime();
    return $date->setTimestamp($in)->format('Y-m-d H:i:s T');
  }

  private function getMyDate2($in)
  {
    $date = new DateTime();
    return $date->setTimestamp($in);
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
    $itemTreeBounds = M::mock(ItemTreeBounds::classname());
    $itemTreeBounds->shouldReceive('getItemId')->andReturn(301);
    $itemTreeBounds->shouldReceive('getUploadTreeTableName')->andReturn('uploadtree');
    $itemTreeBounds->shouldReceive('containsFiles')->andReturn(false);
    $itemTreeBounds->shouldReceive('getUploadId')->andReturn($this->items[301][0]);
    $itemTreeBounds->shouldReceive('getLeft')->andReturn($this->items[301][4]);
    $itemTreeBounds->shouldReceive('getRight')->andReturn($this->items[301][5]);

    $events1 = $this->clearingDao->getRelevantClearingEvents($itemTreeBounds, $groupId);

    assertThat($events1, arrayWithSize(2));
    assertThat($events1, hasKeyInArray(401));
    assertThat($events1, hasKeyInArray(402));
    assertThat($events1[401], is(anInstanceOf(ClearingEvent::classname())));
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
    $watchThis = $this->clearingDao->isDecisionWip(301, $groupId);
    assertThat($watchThis,is(FALSE));
    $watchOther = $this->clearingDao->isDecisionWip(303, $groupId);
    assertThat($watchOther,is(FALSE));
    $this->buildProposals(array(
        array(301,1,$groupId,403,false,-89),
    ),$firstEventId+3);
    $this->clearingDao->markDecisionAsWip(301, 1, $groupId);
    $watchThisNow = $this->clearingDao->isDecisionWip(301, $groupId);
    assertThat($watchThisNow,is(TRUE));
    $watchOtherNow = $this->clearingDao->isDecisionWip(303, $groupId);
    assertThat($watchOtherNow,is(FALSE));
  }

  public function testInsertMultipleClearingEvents()
  {
    $groupId = 701;
    $licenses = array(401,402);
    $oldlicenses = array(401,403);
    $removed = false;
    $uploadTreeId = 304;
    $uploadTreeIdPp = 305;
    $userid = 1;
    $jobfk = 501;
    $comment="<commit>";
    $remark="<remark>";

    foreach($oldlicenses as $lic)
    {
      $aDecEvent = array('uploadtree_fk'=>$uploadTreeIdPp, 'user_fk'=>$userid, 'group_fk'=>$groupId,
          'rf_fk'=>$lic, 'removed'=>$removed, 'job_fk' =>$jobfk,
          'type_fk'=>ClearingEventTypes::USER, 'comment'=>$comment, 'reportinfo'=>$remark);
      $this->dbManager->insertTableRow('clearing_event', $aDecEvent, $sqlLog=__METHOD__.'.oldclearing');
    }

    $this->clearingDao->insertMultipleClearingEvents($licenses, $removed, $uploadTreeId, $userid, $groupId, $jobfk, $comment, $remark);

    $refs = $this->dbManager->createMap('clearing_event', 'rf_fk', 'rf_fk');
    $expected = array_unique(array_merge($licenses, $oldlicenses));
    sort($refs);
    sort($expected);
    assertThat(array_values($refs),equalTo($expected));
  }

 
  public function testBulkHistoryWithoutMatches()
  {
    $treeBounds = M::mock(ItemTreeBounds::classname());
    $treeBounds->shouldReceive('getItemId')->andReturn(301);
    $treeBounds->shouldReceive('getLeft')->andReturn(1);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(101);
    $bulks = $this->clearingDao->getBulkHistory($treeBounds);

    $bulkMatched = array_map(function($bulk){ return $bulk['matched']; }, $bulks);
    $bulkText = array_map(function($bulk){ return $bulk['text']; }, $bulks);
    $bulkLics = array_map(function($bulk){ return $bulk['lic']; }, $bulks);
    assertThat($bulkMatched, arrayContaining(false, false, false, false, false));
    assertThat($bulkLics, arrayContaining('FOO', 'BAR', 'BAZ', 'BAZ', 'QUX'));
    assertThat($bulkText, arrayContaining('TextFOO', 'TextBAR', 'TextBAZ', 'TextBAZ', 'TextQUX'));
  }

  public function testBulkHistoryWithoutMatchesFromDifferentFolder()
  {
    $treeBounds = M::mock(ItemTreeBounds::classname());
    $treeBounds->shouldReceive('getItemId')->andReturn(305);
    $treeBounds->shouldReceive('getLeft')->andReturn(4);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(102);
    $bulks = $this->clearingDao->getBulkHistory($treeBounds);

    $bulkMatched = array_map(function($bulk){ return $bulk['matched']; }, $bulks);
    assertThat($bulkMatched, arrayContaining(false));
  }

  public function testBulkHistoryWithAMatch()
  {
    $this->insertBulkEvents();

    $treeBounds = M::mock(ItemTreeBounds::classname());
    $treeBounds->shouldReceive('getItemId')->andReturn(301);
    $treeBounds->shouldReceive('getLeft')->andReturn(1);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(101);
    $bulks = $this->clearingDao->getBulkHistory($treeBounds);

    $clearingEventIds = array_map(function($bulk){ return $bulk['id']; }, $bulks);
    $bulkMatched = array_map(function($bulk){ return $bulk['matched']; }, $bulks);
    $bulkLics = array_map(function($bulk){ return $bulk['lic']; }, $bulks);
    $bulkLicDirs = array_map(function($bulk){ return $bulk['removing']; }, $bulks);
    $bulkTried = array_map(function($bulk){ return $bulk['tried']; }, $bulks);

    assertThat($clearingEventIds, arrayContaining(5001, null, null, 5004, null));
    assertThat($bulkMatched, arrayContaining(true, false, false, true, false));
    assertThat($bulkLics, arrayContaining('FOO', 'BAR', 'BAZ', 'BAZ', 'QUX'));
    assertThat($bulkLicDirs, arrayContaining(false, false, true, false, true));
    assertThat($bulkTried, arrayContaining(true, true, true, true, true));
  }

  public function testBulkHistoryWithAMatchReturningAlsoNotTried()
  {
    $this->insertBulkEvents();

    $treeBounds = M::mock(ItemTreeBounds::classname());
    $treeBounds->shouldReceive('getItemId')->andReturn(301);
    $treeBounds->shouldReceive('getLeft')->andReturn(1);
    $treeBounds->shouldReceive('getUploadTreeTableName')->andReturn("uploadtree");
    $treeBounds->shouldReceive('getUploadId')->andReturn(101);
    $bulks = $this->clearingDao->getBulkHistory($treeBounds, false);

    $clearingEventIds = array_map(function($bulk){ return $bulk['id']; }, $bulks);
    $bulkMatched = array_map(function($bulk){ return $bulk['matched']; }, $bulks);
    $bulkLics = array_map(function($bulk){ return $bulk['lic']; }, $bulks);
    $bulkLicDirs = array_map(function($bulk){ return $bulk['removing']; }, $bulks);
    $bulkTried = array_map(function($bulk){ return $bulk['tried']; }, $bulks);

    assertThat($clearingEventIds, arrayContaining(5001, null, null, 5004, null, null));
    assertThat($bulkMatched, arrayContaining(true, false, false, true, false, false));
    assertThat($bulkLics, arrayContaining('FOO', 'BAR', 'BAZ', 'BAZ', 'QUX', 'FOO'));
    assertThat($bulkLicDirs, arrayContaining(false, false, true, false, true, true));
    assertThat($bulkTried, arrayContaining(true, true, true, true, true, false));
  }
  
}
