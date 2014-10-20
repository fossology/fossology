<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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

namespace Fossology\Lib\Data;


use DateTime;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseDecision\LicenseDecision;

class ClearingDecisionBuilderTest extends \PHPUnit_Framework_TestCase
{

  /** @var bool */
  private $sameUpload = true;

  /** @var bool */
  private $sameFolder = true;

  /** @var LicenseRef[] */
  private $licenses;

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
  private $scope;

  /** @var DateTime */
  private $date_added;

  /** @var ClearingDecisionBuilder */
  private $clearingDecisionBuilder;

  public function setUp()
  {
    $this->sameUpload = true;
    $this->sameFolder = true;
    $this->licenses = array(new LicenseRef(8, "testSN", "testFN"), new LicenseRef(100, "test2SN", "test2FN"), new LicenseRef(1007, "test3SN", "test3FN"));
    $this->clearingId = 8;
    $this->uploadTreeId = 9;
    $this->pfileId = 10;
    $this->userName = "tester";
    $this->userId = 11;
    $this->type = DecisionTypes::TO_BE_DISCUSSED;
    $this->comment = "Test comment";
    $this->reportinfo = "Test reportinfo";
    $this->scope = LicenseDecision::SCOPE_UPLOAD;
    $this->date_added = DateTime::createFromFormat('Y-m-d h:i:s', "2012-07-08 11:14:15");
    
    $this->clearingDecisionBuilder = ClearingDecisionBuilder::create()->setType(DecisionTypes::IDENTIFIED);
  }

  public function testSameUpload()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setSameUpload($this->sameUpload)
        ->build();
    assertThat($clearingDec->getSameUpload(), is($this->sameUpload));
  }

  public function testSameFolder()
  {
    $clearingDec =$this->clearingDecisionBuilder 
        ->setSameFolder($this->sameFolder)
        ->build();
    assertThat($clearingDec->getSameFolder(), is($this->sameFolder));
  }

  public function testLicenses()
  {
    $clearingDec = $this->clearingDecisionBuilder
        ->setLicenses($this->licenses)
        ->build();
    assertThat($clearingDec->getLicenses(), is($this->licenses));
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
        ->setDateAdded($this->date_added->getTimestamp())
        ->build();
    assertThat($clearingDec->getDateAdded(), is($this->date_added));
  }

}
 