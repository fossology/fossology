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

class ClearingDecisionBuilderTest extends \PHPUnit_Framework_TestCase {

  /**
   * @var bool
   */
  private $sameUpload=true;

  /**
   * @var bool
   */
  private $sameFolder=true;

  /**
   * @var LicenseRef[]
   */
  private $licenses ;

  /**
   * @var int
   */
  private $clearingId;

  /**
   * @var int
   */
  private $uploadTreeId;

  /**
   * @var int
   */
  private $pfileId;
  /**
   * @var string
   */
  private $userName;
  /**
   * @var int
   */
  private $userId;

  /**
   * @var string
   */
  private $type;

  /**
   * @var string
   */
  private $comment;

  /**
   * @var string
   */
  private $reportinfo;

  /**
   * @var string
   */
  private $scope;

  /**
   * @var DateTime
   */
  private $date_added;

  public function setUp()
  {
    $this->sameUpload=true;
    $this->sameFolder=true;
    $this->licenses = array (new LicenseRef(8,"testSN", "testFN") ,new LicenseRef(100,"test2SN", "test2FN"), new LicenseRef(1007,"test3SN", "test3FN")  );
    $this->clearingId=8;
    $this->uploadTreeId=9;
    $this->pfileId=10;
    $this->userName="tester";
    $this->userId=11;
    $this->type = ClearingDecision::TO_BE_DISCUSSED;
    $this->comment="Test comment";
    $this->reportinfo ="Test reportinfo";
    $this->scope = LicenseDecision::SCOPE_UPLOAD;
    $this->date_added = DateTime::createFromFormat('Y-m-d h:i:s',"2012-07-08 11:14:15");
  }

  public function testSameUpload()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setSameUpload ($this->sameUpload)
      -> build();
    assertThat($clearingDec->getSameUpload(), is($this->sameUpload));
  }

  public function testSameFolder()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setSameFolder ($this->sameFolder)
      -> build();
    assertThat($clearingDec->getSameFolder(), is($this->sameFolder));
  }

  public function testLicenses()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setLicenses ($this->licenses)
      -> build();
    assertThat($clearingDec->getLicenses(), is($this->licenses));
  }

  public function testClearingId()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setClearingId ($this->clearingId)
      -> build();
    assertThat($clearingDec->getClearingId(), is($this->clearingId));
  }

  public function testUploadTreeId()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setUploadTreeId ($this->uploadTreeId)
      -> build();
    assertThat($clearingDec->getUploadTreeId(), is($this->uploadTreeId));
  }

  public function testPfileId()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setPfileId ($this->pfileId)
      -> build();
    assertThat($clearingDec->getPfileId(), is($this->pfileId));
  }

  public function testUserName()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setUserName ($this->userName)
      -> build();
    assertThat($clearingDec->getUserName(), is($this->userName));
  }

  public function testUserId()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setUserId ($this->userId)
      -> build();
    assertThat($clearingDec->getUserId(), is($this->userId));
  }

  public function testType()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setType ($this->type)
      -> build();
    assertThat($clearingDec->getType(), is($this->type));
  }

  public function testDateAdded()
  {
    $clearingDec = ClearingDecisionBuilder::create()
      -> setDateAdded ($this->date_added->getTimestamp())
      -> build();
    assertThat($clearingDec->getDateAdded(), is($this->date_added));
  }

}
 