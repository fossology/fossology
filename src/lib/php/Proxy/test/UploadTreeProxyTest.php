<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Test\TestPgDb;

class UploadTreeProxyTest extends \PHPUnit\Framework\TestCase
{
  private $testDb;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->testDb->createPlainTables( array('uploadtree', 'report_info', 'upload') );
    $this->dbManager = $this->testDb->getDbManager();
    $this->dbManager->queryOnce('ALTER TABLE uploadtree RENAME TO uploadtree_a');
    $this->testDb->insertData(array('uploadtree_a'));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
  }

  public function testGetNonArtifactDescendantsWithMaterialize()
  {
    $uploadTreeProxy = new UploadTreeProxy($uploadId=1, $options=array(), $uploadTreeTableName='uploadtree_a');
    $uploadTreeProxy->materialize();

    $artifact = new ItemTreeBounds(2,'uploadtree_a', $uploadId, 2, 3);
    $artifactDescendants = $uploadTreeProxy->getNonArtifactDescendants($artifact);
    assertThat($artifactDescendants, emptyArray());

    $zip = new ItemTreeBounds(1,'uploadtree_a', $uploadId, 1, 24);
    $zipDescendants = $uploadTreeProxy->getNonArtifactDescendants($zip);
    assertThat(array_keys($zipDescendants), arrayContainingInAnyOrder(array(6,7,8,10,11,12)) );

    $uploadTreeProxy->unmaterialize();
  }

  public function testGetNonArtifactDescendantsWithoutMaterialize()
  {
    $uploadTreeProxy = new UploadTreeProxy($uploadId=1, $options=array(), $uploadTreeTableName='uploadtree_a');

    $artifact = new ItemTreeBounds(2,'uploadtree_a', $uploadId, 2, 3);
    $artifactDescendants = $uploadTreeProxy->getNonArtifactDescendants($artifact);
    assertThat($artifactDescendants, emptyArray());

    $zip = new ItemTreeBounds(1,'uploadtree_a', $uploadId, 1, 24);
    $zipDescendants = $uploadTreeProxy->getNonArtifactDescendants($zip);
    assertThat(array_keys($zipDescendants), arrayContainingInAnyOrder(array(6,7,8,10,11,12)) );
  }

  protected function prepareUploadTree($upload=4)
  {
    $this->dbManager->prepare($stmt = 'insert.uploadtree',
        "INSERT INTO uploadtree_a (uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name, realparent)"
            . " VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)");
    foreach (array(
      array(301, null, $upload, null, 2<<28, 1, 16, 'topDir',null)
     ,array(302,  301, $upload, null, 2<<28, 2,  5,   'dirA', 301)
     ,array(303,  302, $upload,  101,     0, 3,  4, 'fileAB.txt', 302)
     ,array(304,  301, $upload, null, 3<<28, 6, 13,  'metaC', 301)
     ,array(305,  304, $upload, null, 2<<28, 7, 10,  'dirCD', 301)
     ,array(306,  305, $upload,  102,     0, 8,  9,'fileCDE.c', 305)
     ,array(307,  304, $upload,  103,     0,11, 12, 'fileCF.cpp', 301)
     ,array(308,  301, $upload,  104,     0,14, 15,  'fileG.h', 301)
        ) as $itemEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $itemEntry));
    }
  }


  public function testOptionRealParent()
  {
    $this->prepareUploadTree($upload=4);

    $uploadTreeProxy = new UploadTreeProxy($upload, $options=array(UploadTreeProxy::OPT_REALPARENT=>301), $uploadTreeTableName='uploadtree_a');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT uploadtree_pk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendants = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendants = array_reduce($descendants, function($foo,$bar)
    {
      $foo[] = $bar['uploadtree_pk'];
      return $foo;
    }, array());
    assertThat($zipDescendants, equalTo(array(302,305,307,308)) );
  }

  public function testOptionRange()
  {
    $this->prepareUploadTree($upload=4);

    $itemBounds = new ItemTreeBounds(302, $uploadTreeTableName='uploadtree_a', $upload, 2, 5);
    $uploadTreeProxyA = new UploadTreeProxy($upload, array(UploadTreeProxy::OPT_RANGE=>$itemBounds), $uploadTreeTableName, 'viewDirA');
    $stmt = __METHOD__.'A';
    $this->dbManager->prepare($stmt, $uploadTreeProxyA->asCTE()." SELECT uploadtree_pk FROM ".$uploadTreeProxyA->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxyA->getParams());
    $descendants = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsA = array_reduce($descendants, function($foo,$bar){
      $foo[] = $bar['uploadtree_pk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsA, equalTo(array(303)) );

    $itemBoundsT = new ItemTreeBounds(301, $uploadTreeTableName='uploadtree_a', $upload, 1, 16);
    $uploadTreeProxyT = new UploadTreeProxy($upload, array(UploadTreeProxy::OPT_RANGE=>$itemBoundsT), $uploadTreeTableName, 'viewTop');
    $stmtT = __METHOD__;
    $this->dbManager->prepare($stmtT, $uploadTreeProxyT->asCTE()." SELECT uploadtree_pk FROM ".$uploadTreeProxyT->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxyT->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['uploadtree_pk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(303,306,307,308)) );
  }

  public function testOptionExt()
  {
    $this->prepareUploadTree($upload=4);

    $uploadTreeProxy = new UploadTreeProxy($upload, $options=array(UploadTreeProxy::OPT_EXT=>'c'), 'uploadtree_a');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT ufile_name FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendants = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendants = array_reduce($descendants, function($foo,$bar){
      $foo[] = $bar['ufile_name'];
      return $foo;
    }, array());
    assertThat($zipDescendants, equalTo(array('fileCDE.c')) );
  }

  public function testOptionHead()
  {
    $this->prepareUploadTree($upload=4);

    $uploadTreeProxy = new UploadTreeProxy($upload, $options=array(UploadTreeProxy::OPT_HEAD=>'filEc'), 'uploadtree_a');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT ufile_name FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendants = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendants = array_reduce($descendants, function($foo,$bar){
      $foo[] = $bar['ufile_name'];
      return $foo;
    }, array());
    assertThat($zipDescendants, equalTo(array('fileCDE.c','fileCF.cpp')) );
  }

  public function testOptionScanRefParented()
  {
    $this->testDb->createPlainTables( array('license_map','license_file') );
    $rfId = 201;
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>103,'agent_fk'=>401));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>102,'agent_fk'=>402));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId+1,'pfile_fk'=>101,'agent_fk'=>401));

    $this->prepareUploadTree($upload=4);

    $itemBoundsT = new ItemTreeBounds(301, $uploadTreeTableName='uploadtree_a', $upload, 1, 16);
    $options = array(UploadTreeProxy::OPT_RANGE=>$itemBoundsT, UploadTreeProxy::OPT_AGENT_SET=>array(401), UploadTreeProxy::OPT_SCAN_REF=>$rfId);
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, $uploadTreeTableName, 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT pfile_fk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['pfile_fk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(103)) );
  }

  public function testOptionScanRefRanged()
  {
    $this->testDb->createPlainTables( array('license_map','license_file') );
    $rfId = 201;
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>103,'agent_fk'=>401));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>102,'agent_fk'=>402));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId+1,'pfile_fk'=>104,'agent_fk'=>401));

    $this->prepareUploadTree($upload=4);

    $options = array(UploadTreeProxy::OPT_REALPARENT=>301, UploadTreeProxy::OPT_AGENT_SET=>array(401), UploadTreeProxy::OPT_SCAN_REF=>$rfId);
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, 'uploadtree_a', 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT pfile_fk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['pfile_fk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(103)) );
  }

  protected function insertDecisionEvent($decisionId,$eventId,$rfId,$groupId,$item,$pfileId,$type,$removed,$date,$scope=DecisionScopes::ITEM)
  {
    $this->dbManager->insertTableRow('clearing_decision',array('clearing_decision_pk'=>$decisionId,'pfile_fk'=>$pfileId,'uploadtree_fk'=>$item,
        'group_fk'=>$groupId,'date_added'=>$date,'decision_type'=> $type,'scope'=>$scope));
    $this->dbManager->insertTableRow('clearing_event',array('clearing_event_pk'=>$eventId,'rf_fk'=>$rfId,'group_fk'=>$groupId,'uploadtree_fk'=>$item,
        'date_added'=>$date,'removed'=>$removed));
    if ($type != DecisionTypes::WIP) {
      $this->dbManager->insertTableRow('clearing_decision_event', array('clearing_event_fk' => $eventId, 'clearing_decision_fk' => $decisionId));
    }
  }

  public function testOptionConRefParented()
  {
    $this->testDb->createPlainTables( array('clearing_decision','clearing_decision_event','clearing_event') );

    $rfId = 201;
    $groupId = 301;
    $decisionId = 501;
    $eventId = 601;

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::IDENTIFIED, 'true', '2015-05-11 12:15');

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::IDENTIFIED, 'true', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:15');

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 308, 104, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 308, 104, DecisionTypes::WIP, 'true', '2015-05-11 12:15');

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 303, 101, DecisionTypes::WIP, 'false', '2015-05-11 12:15');

    $this->prepareUploadTree($upload=4);

    $options = array(UploadTreeProxy::OPT_GROUP_ID=>$groupId, UploadTreeProxy::OPT_REALPARENT=>301, UploadTreeProxy::OPT_CONCLUDE_REF=>$rfId);
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, $uploadTreeTableName='uploadtree_a', 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT uploadtree_pk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['uploadtree_pk'];
      return $foo;
    }, array());
    $parentOf306 = 305;
    assertThat($zipDescendantsT, equalTo(array($parentOf306,308)) );
  }

  public function testOptionConRefRanged()
  {
    $this->testDb->createPlainTables( array('clearing_decision','clearing_decision_event','clearing_event') );

    $rfId = 201;
    $groupId = 301;
    $decisionId = 501;
    $eventId = 601;

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::IDENTIFIED, 'true', '2015-05-11 12:15');

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::IDENTIFIED, 'true', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:15');

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 308, 104, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 308, 104, DecisionTypes::WIP, 'true', '2015-05-11 12:15');

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 303, 101, DecisionTypes::WIP, 'false', '2015-05-11 12:15');

    $this->prepareUploadTree($upload=4);

    $itemBoundsT = new ItemTreeBounds(301, $uploadTreeTableName='uploadtree_a', $upload, 1, 16);
    $options = array(UploadTreeProxy::OPT_GROUP_ID=>$groupId, UploadTreeProxy::OPT_RANGE=>$itemBoundsT, UploadTreeProxy::OPT_CONCLUDE_REF=>$rfId);
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, $uploadTreeTableName, 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT uploadtree_pk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['uploadtree_pk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(306,308)) );
  }

  public function testOptionSkipAlreadyClearedRanged()
  {
    $this->testDb->createPlainTables( array('license_file','clearing_decision','clearing_decision_event','clearing_event','license_ref') );
    $this->testDb->createInheritedTables( array('license_candidate') );

    $rfId = 201;
    $groupId = 301;
    $decisionId = 501;
    $eventId = 601;

    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>$rfId,'rf_shortname'=>'any_license_found'));

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::IDENTIFIED, 'true', '2015-05-11 12:13');

    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>103,'agent_fk'=>401));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>104,'agent_fk'=>401));

    $this->prepareUploadTree($upload=4);

    $itemBoundsT = new ItemTreeBounds(301, $uploadTreeTableName='uploadtree_a', $upload, 1, 16);
    $options = array(UploadTreeProxy::OPT_GROUP_ID=>$groupId, UploadTreeProxy::OPT_RANGE=>$itemBoundsT, UploadTreeProxy::OPT_SKIP_ALREADY_CLEARED=>true, UploadTreeProxy::OPT_AGENT_SET=>array(401));
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, $uploadTreeTableName, 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT pfile_fk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['pfile_fk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(104)) );
  }

  public function testOptionSkipAlreadyClearedParented()
  {
    $this->testDb->createPlainTables( array('license_file','clearing_decision','clearing_decision_event','clearing_event','license_ref') );
    $this->testDb->createInheritedTables( array('license_candidate') );

    $rfId = 201;
    $groupId = 301;
    $decisionId = 501;
    $eventId = 601;

    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>$rfId,'rf_shortname'=>'any_license_found'));

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::IDENTIFIED, 'true', '2015-05-11 12:13');

    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>103,'agent_fk'=>401));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>104,'agent_fk'=>401));

    $this->prepareUploadTree($upload=4);

    $options = array(UploadTreeProxy::OPT_GROUP_ID=>$groupId, UploadTreeProxy::OPT_REALPARENT=>301, UploadTreeProxy::OPT_SKIP_ALREADY_CLEARED=>true, UploadTreeProxy::OPT_AGENT_SET=>array(401));
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, $uploadTreeTableName='uploadtree_a', 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT pfile_fk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['pfile_fk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(104)) );
  }

  public function testOptionSkipTheseThatAreAlreadyCleared()
  {
    $this->testDb->createPlainTables( array('license_file','clearing_decision','clearing_decision_event','clearing_event','license_ref') );
    $this->testDb->createInheritedTables( array('license_candidate') );

    $rfId = 201;
    $groupId = 301;
    $decisionId = 501;
    $eventId = 601;

    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>$rfId,'rf_shortname'=>'any_license_found'));

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::IDENTIFIED, 'true', '2015-05-11 12:13');

    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>103,'agent_fk'=>401));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>104,'agent_fk'=>401));

    $this->prepareUploadTree($upload=4);

    $options = array(UploadTreeProxy::OPT_GROUP_ID=>$groupId, UploadTreeProxy::OPT_REALPARENT=>301, UploadTreeProxy::OPT_SKIP_THESE=>UploadTreeProxy::OPT_SKIP_ALREADY_CLEARED, UploadTreeProxy::OPT_AGENT_SET=>array(401));
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, $uploadTreeTableName='uploadtree_a', 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT pfile_fk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['pfile_fk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(104)) );
  }

  public function testOptionSkipAlreadyClearedButScanRanged()
  {
    $this->testDb->createPlainTables( array('license_file','clearing_decision','clearing_decision_event','clearing_event','license_ref','license_map') );
    $this->testDb->createInheritedTables( array('license_candidate') );

    $rfId = 201;
    $groupId = 301;
    $decisionId = 501;
    $eventId = 601;

    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>$rfId,'rf_shortname'=>'any_license_found'));
    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>$rfId+1,'rf_shortname'=>'license_found'));

    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::IDENTIFIED, 'true', '2015-05-11 12:13');

    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>103,'agent_fk'=>401));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>104,'agent_fk'=>401));
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId+1,'pfile_fk'=>101,'agent_fk'=>401));

    $this->prepareUploadTree($upload=4);

    $itemBoundsT = new ItemTreeBounds(301, $uploadTreeTableName='uploadtree_a', $upload, 1, 16);
    $options = array(UploadTreeProxy::OPT_GROUP_ID=>$groupId, UploadTreeProxy::OPT_RANGE=>$itemBoundsT,
        UploadTreeProxy::OPT_SKIP_ALREADY_CLEARED=>true, UploadTreeProxy::OPT_AGENT_SET=>array(401), UploadTreeProxy::OPT_SCAN_REF=>$rfId);
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, $uploadTreeTableName, 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT pfile_fk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['pfile_fk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(104)) );
  }

  public function testCount()
  {
    $uploadTreeProxy = new UploadTreeProxy(1, array(), 'uploadtree_a');
    assertThat($uploadTreeProxy->count(), is(12));

    $uploadTreeProxy->materialize();
    assertThat($uploadTreeProxy->count(), is(12));

    $uploadTreeProxyAd = new UploadTreeProxy(1, array(UploadTreeProxy::OPT_ITEM_FILTER=>" AND ufile_name LIKE 'Ad%'"), 'uploadtree_a', 'viewWithHead');
    assertThat($uploadTreeProxyAd->count(), is(2));
  }

  public function testGetUploadTreeTableName()
  {
    $uploadTreeProxy = new UploadTreeProxy(1, array(), $tableName='uploadtree_a');
    assertThat($uploadTreeProxy->getUploadTreeTableName(), is(equalTo($tableName)));
  }

  public function testGetDefaultUploadTreeView()
  {
    $this->prepareUploadTree($upload=4);
    $options = array(UploadTreeProxy::OPT_ITEM_FILTER=>"AND ufile_name='dirA'");
    $uploadTreeProxy = new UploadTreeProxy(4, $options, $uploadTreeTableName='uploadtree_a');

    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT uploadtree_pk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['uploadtree_pk'];
      return $foo;
    }, array());
    assertThat($zipDescendantsT, equalTo(array(302)) );
  }

  public function testOptionOnlyTBD()
  {
    $this->testDb->createPlainTables( array('license_file','clearing_decision','clearing_decision_event','clearing_event','license_ref') );
    $this->testDb->createInheritedTables( array('license_candidate') );

    $rfId = 201;
    $groupId = 301;
    $decisionId = 501;
    $eventId = 601;

    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>$rfId,'rf_shortname'=>'any_license_found'));

    // File 101 (303): IDENTIFIED (Cleared)
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 303, 101, DecisionTypes::IDENTIFIED, 'false', '2015-05-11 12:13');

    // File 102 (306): TO_BE_DISCUSSED (Should be picked up)
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 306, 102, DecisionTypes::TO_BE_DISCUSSED, 'false', '2015-05-11 12:13');

    // File 103 (307): WIP (Should NOT be picked up)
    $this->insertDecisionEvent($decisionId++, $eventId++, $rfId, $groupId, 307, 103, DecisionTypes::WIP, 'false', '2015-05-11 12:13');

    // File 104 (308): No decision (Should NOT be picked up) - has license file but no decision
    $this->dbManager->insertTableRow('license_file',array('rf_fk'=>$rfId,'pfile_fk'=>104,'agent_fk'=>401));

    $this->prepareUploadTree($upload=4);

    $options = array(UploadTreeProxy::OPT_GROUP_ID=>$groupId, UploadTreeProxy::OPT_REALPARENT=>301, UploadTreeProxy::OPT_SKIP_THESE=>UploadTreeProxy::OPT_ONLY_TBD, UploadTreeProxy::OPT_AGENT_SET=>array(401));
    $uploadTreeProxy = new UploadTreeProxy($upload, $options, $uploadTreeTableName='uploadtree_a', 'viewTop');
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $uploadTreeProxy->asCTE()." SELECT pfile_fk FROM ".$uploadTreeProxy->getDbViewName());
    $res = $this->dbManager->execute($stmt, $uploadTreeProxy->getParams());
    $descendantsT = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $zipDescendantsT = array_reduce($descendantsT, function($foo,$bar){
      $foo[] = $bar['pfile_fk'];
      return $foo;
    }, array());
    // Only 102 should be returned
    assertThat($zipDescendantsT, equalTo(array(102)) );
  }
}
