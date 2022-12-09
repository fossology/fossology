<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Mockery as M;

class ClearingDecisionBuilderTest extends \PHPUnit\Framework\TestCase
{
  /** @var bool */
  private $sameUpload = true;
  /** @var bool */
  private $sameFolder = true;
  /** @var ClearingEvent */
  private $clearingEvent;
  /** @var int */
  private $clearingId;
  /** @var int */
  private $uploadTreeId;
  /** @var int */
  private $pfileId;
  /** @var string */
  private $userName;
  /** @var int */
  private $userId;
  /** @var string */
  private $type;
  /** @var string */
  private $comment;
  /** @var string */
  private $reportinfo;
  /** @var string */
  private $acknowledgement;
  /** @var string */
  private $scope;
  /** @var int */
  private $timeStamp;

  /** @var ClearingDecisionBuilder */
  private $clearingDecisionBuilder;

  protected function setUp() : void
  {
    $this->sameUpload = true;
    $this->sameFolder = true;
    $this->clearingEvent = M::mock(ClearingEvent::class);
    $this->clearingId = 8;
    $this->uploadTreeId = 9;
    $this->pfileId = 10;
    $this->userName = "tester";
    $this->userId = 11;
    $this->type = DecisionTypes::TO_BE_DISCUSSED;
    $this->comment = "Test comment";
    $this->reportinfo = "Test reportinfo";
    $this->acknowledgement = "Test acknowledgement";
    $this->scope = DecisionScopes::ITEM;
    $this->timeStamp = mktime(11, 14, 15, 7, 28, 2012);

    $this->clearingDecisionBuilder = ClearingDecisionBuilder::create()->setType(DecisionTypes::IDENTIFIED);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }

  public function testSameFolder()
  {
    $clearingDec =$this->clearingDecisionBuilder
        ->setSameFolder($this->sameFolder)
        ->build();
    assertThat($clearingDec->getSameFolder(), is($this->sameFolder));
  }

  public function testClearingLicenses()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setClearingEvents(array($this->clearingEvent))
        ->build();
    assertThat($clearingDec->getClearingEvents(), is(arrayContaining($this->clearingEvent)));
  }

  public function testPositiveLicenses()
  {
    $addedLic = M::mock(LicenseRef::class);

    $addedClearingLic = M::mock(ClearingLicense::class);
    $addedClearingLic->shouldReceive('isRemoved')->withNoArgs()->andReturn(false);
    $addedClearingLic->shouldReceive('getLicenseRef')->withNoArgs()->andReturn($addedLic);

    $removedClearingLic = M::mock(ClearingLicense::class);
    $removedClearingLic->shouldReceive('isRemoved')->andReturn(true);

    $removedClearingEvent = M::mock(ClearingEvent::class);

    $this->clearingEvent->shouldReceive('getClearingLicense')->andReturn($addedClearingLic);
    $removedClearingEvent->shouldReceive('getClearingLicense')->andReturn($removedClearingLic);

    $clearingDec = $this->clearingDecisionBuilder
        ->setClearingEvents(array($this->clearingEvent, $removedClearingEvent))
        ->build();
    assertThat($clearingDec->getPositiveLicenses(), is(arrayContaining($addedLic)));
  }

  public function testClearingId()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setClearingId($this->clearingId)
        ->build();
    assertThat($clearingDec->getClearingId(), is($this->clearingId));
  }

  public function testUploadTreeId()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setUploadTreeId($this->uploadTreeId)
        ->build();
    assertThat($clearingDec->getUploadTreeId(), is($this->uploadTreeId));
  }

  public function testPfileId()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setPfileId($this->pfileId)
        ->build();
    assertThat($clearingDec->getPfileId(), is($this->pfileId));
  }

  public function testUserName()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setUserName($this->userName)
        ->build();
    assertThat($clearingDec->getUserName(), is($this->userName));
  }

  public function testUserId()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setUserId($this->userId)
        ->build();
    assertThat($clearingDec->getUserId(), is($this->userId));
  }

  public function testType()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setType($this->type)
        ->build();
    assertThat($clearingDec->getType(), is($this->type));
  }

  public function testDateAdded()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setTimeStamp($this->timeStamp)
        ->build();
    assertThat($clearingDec->getTimeStamp(), is($this->timeStamp));
  }
}
