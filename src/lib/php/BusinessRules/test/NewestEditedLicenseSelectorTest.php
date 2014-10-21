<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Daniele Fognini, Johannes Najjar

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
namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\ClearingDecisionBuilder;
use Fossology\Lib\Data\DecisionTypes;


class NewestEditedLicenseSelectorTest extends \PHPUnit_Framework_TestCase
{

  /**
   * @var NewestEditedLicenseSelector
   */
  private $newestEditedLicenseSelector;

  /**
   * @param $id
   * @param $isLocal
   * @param $scope
   * @param $name
   * @param $ud
   * @return ClearingDecision
   */
  private function clearingDec($id, $isLocal, $scope, $name, $ud, $pfileId=1,$uploadTreeId=1)
  {
    $clearingDecision = ClearingDecisionBuilder::create()
        ->setClearingId($id)
        ->setUserName($name)
        ->setSameFolder($isLocal)
        ->setSameUpload($isLocal)
        ->setType($ud)
        ->setScope($scope)
        ->setPfileId($pfileId)
        ->setUploadTreeId($uploadTreeId);

    $licref = $this->licenseRef($id, $name);
    $clearingDecision->setPositiveLicenses(array($licref));

    return $clearingDecision->build();
  }
  
  private function licenseRef($rf,$name)
  {
    return new LicenseRef($rf, $name . 'shortName', $name . 'fullName');
  }
  
  private function localClearingDec($id, $type, $positive, $negative)
  {
    $clearingDecision = ClearingDecisionBuilder::create()
        ->setClearingId($id)
        ->setUserName('anyUser')
        ->setSameFolder(true)
        ->setSameUpload(true)
        ->setType($type)
        ->setScope('upload')
        ->setPfileId(123)
        ->setUploadTreeId(456)
        ->setPositiveLicenses($positive)
        ->setNegativeLicenses($negative)
        ->build();
    return $clearingDecision;
  }
  

  public function setUp()
  {
    $this->newestEditedLicenseSelector = new NewestEditedLicenseSelector();
  }

  public function testCreateClearingDec()
  {
    $licenses = $this->clearingDec(0, true, 'global', "Test", 'Identified')->getPositiveLicenses();
    $firstLicense = reset($licenses);
    assertThat($firstLicense->getShortName(), is("TestshortName"));
  }

  public function testEmptyIsEmpty()
  {
    $editedLicensesArray = array();
    assertThat($this->newestEditedLicenseSelector->extractGoodLicensesPerFileID($editedLicensesArray), is(array()));
  }

  public function testNotFoundIsEmpty()
  {
    $editedLicensesArray = array(134 => $this->clearingDec(0, false, 'upload', "Test", DecisionTypes::IDENTIFIED));
    assertThat($this->newestEditedLicenseSelector->extractGoodLicensesPerFileID($editedLicensesArray), is(array()));
  }

  public function testFoundIsNotEmpty()
  {
    $cd = $this->clearingDec(0, false, 'global', "Test", DecisionTypes::IDENTIFIED);
    $editedLicensesArray = array($cd);
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);
    assertThat(implode(", ", $licenses), is("TestshortName"));
  }

  public function testNewerGlobalWinsAgainstGlobal()
  {
    $editedLicensesArray = array(
        $this->clearingDec(1, false, 'global', "A", DecisionTypes::IDENTIFIED),
        $this->clearingDec(0, false, 'global', "B", DecisionTypes::IDENTIFIED)
    );
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);

    assertThat(implode(", ", $licenses), is("AshortName"));
  }

  public function testNewerLocalWinsAgainstGlobal()
  {
    $editedLicensesArray = array(
        $this->clearingDec(1, true, 'upload', "A", DecisionTypes::IDENTIFIED),
        $this->clearingDec(0, false, 'global', "B", DecisionTypes::IDENTIFIED)
    );
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);

    assertThat(implode(", ", $licenses), is("AshortName"));
  }

  public function testOlderLocalWinsAgainstGlobal()
  {
    $editedLicensesArray = array(
        $this->clearingDec(0, false, 'global', "B", DecisionTypes::IDENTIFIED),
        $this->clearingDec(1, true, 'upload', "A", DecisionTypes::IDENTIFIED)

    );
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);

    assertThat(implode(", ", $licenses), is("AshortName"));
  }

  public function testToBeDiscussedIsNotIgnored()
  {
    $editedLicensesArray = array($this->clearingDec(0, true, 'upload', "Test", DecisionTypes::TO_BE_DISCUSSED));
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);
    assertThat($licenses, is(array('TestshortName')));
  }

  public function testOlderGlobalLosesAgainstLocalTBD()
  {
    $editedLicensesArray = array(
        $this->clearingDec(2, true, 'upload', "Test", DecisionTypes::TO_BE_DISCUSSED),
        $this->clearingDec(1, false, 'global', "A", DecisionTypes::IDENTIFIED),
    );
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);

    assertThat(implode(", ", $licenses), is("TestshortName"));
  }

  public function testOlderLocalLosesAgainstNewerTBD()
  {
    $editedLicensesArray = array(
        $this->clearingDec(2, true, 'upload', "Test", DecisionTypes::TO_BE_DISCUSSED),
        $this->clearingDec(1, true, 'upload', "A", DecisionTypes::IDENTIFIED),
    );
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);

    assertThat(implode(", ", $licenses), is("TestshortName"));
  }

  public function testOlderLocalWinsAgainstNewerGlobal()
  {
    $editedLicensesArray = array(
        $this->clearingDec(2, true, 'upload', "Test", DecisionTypes::TO_BE_DISCUSSED),
        $this->clearingDec(1, true, 'global', "A", DecisionTypes::IDENTIFIED),
        $this->clearingDec(0, true, 'upload', "B", DecisionTypes::IDENTIFIED)
    );
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);
    assertThat(implode(", ", $licenses), is("TestshortName"));
  }

  public function testOlderGlobalWinsAgainstNewerLocalForDifferentFileInOtherUpload()
  {
    $editedLicensesArray = array(
        $this->clearingDec(2, false, 'upload', "Test", DecisionTypes::TO_BE_DISCUSSED,1,1),
        $this->clearingDec(1, false, 'upload', "A", DecisionTypes::IDENTIFIED,1,2),
        $this->clearingDec(0, false, 'global', "B", DecisionTypes::IDENTIFIED,1,3)
    );
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);
    assertThat(implode(", ", $licenses), is("BshortName"));
  }

  public function testClearingDecisionTBDIsInActive()
  {
    assertThat($this->newestEditedLicenseSelector->isInactive($this->clearingDec(2, true, 'upload', "Test", DecisionTypes::TO_BE_DISCUSSED)), is(true));
  }

  public function testClearingDecisionUserDecisionIsNotInActive()
  {
    assertThat($this->newestEditedLicenseSelector->isInactive($this->clearingDec(2, true, 'upload', "Test", DecisionTypes::IDENTIFIED)), is(false));
  }

  public function testSelectNewestEditedLicensePerItem_multipleFiles()
  {
    $editedLicensesArray = array(
        $this->clearingDec(4, true, 'upload', "Cesc", DecisionTypes::TO_BE_DISCUSSED,1,1),
        $this->clearingDec(3, true, 'upload', "Aesa", DecisionTypes::IDENTIFIED,3,3),
        $this->clearingDec(2, true, 'upload', "Test", DecisionTypes::IDENTIFIED,1,2),
        $this->clearingDec(1, true, 'upload', "Besb", DecisionTypes::IDENTIFIED,1,1),
    );

    $reflection = new \ReflectionClass($this->newestEditedLicenseSelector->classname() );
    $method = $reflection->getMethod('selectNewestEditedLicensePerItem');
    $method->setAccessible(true);
    
    $licenses = $method->invoke($this->newestEditedLicenseSelector,$editedLicensesArray);
    $licenseNames = array();
    foreach ($licenses as $lic)
    {
      $licenseNames[] = $lic->getShortName();
    }
    assertThat($licenseNames, is(arrayContainingInAnyOrder(array("AesashortName", "CescshortName", "TestshortName"))));
  }
  
  public function testSelectNewestEditedLicensePerFileID_complexeDecision()
  {
    $added1 = array($this->licenseRef(1,'licA'));
    $removed1 = array($this->licenseRef(2,'licB'));
    $added2 = array($this->licenseRef(3,'licC'),$this->licenseRef(4,'licD'));
    $removed2 = array($this->licenseRef(1,'licA'));
    $editedLicensesArray = array(
        $this->localClearingDec(2,DecisionTypes::IDENTIFIED,$added2,$removed2),
        $this->localClearingDec(1,DecisionTypes::IRRELEVANT,$added1,$removed1)
      );
    
    $reflection = new \ReflectionClass($this->newestEditedLicenseSelector->classname() );
    $method = $reflection->getMethod('selectNewestEditedLicensePerItem');
    $method->setAccessible(true);
    
    $licenses = $method->invoke($this->newestEditedLicenseSelector,$editedLicensesArray);
    assertThat($licenses, is($added2) );
  }
}
