<?php
/*
 * Copyright (C) 2017, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace Fossology\ReportImport;

use Fossology\Lib\Data\DecisionTypes;

class ReportImportConfiguration
{
  private static $keys = array(
    'addConcludedAsDecisions', // => $createConcludedLicensesAsConclusions
    'addLicenseInfoFromInfoInFile', // => $createLicensesInfosAsFindings
    'addLicenseInfoFromConcluded', // => $createConcludedLicensesAsFindings
    'addConcludedAsDecisionsOverwrite', // => $overwriteDecisions
    'addCopyrights', // => $addCopyrightInformation
    'addConcludedAsDecisionsTBD', // => $concludeLicenseDecisionType
    'addNewLicensesAs' // => $createLicensesAsCandidate
  );

  protected $createLicensesAsCandidate = true;
  protected $createLicensesInfosAsFindings = true;
  protected $createConcludedLicensesAsFindings = false;
  protected $createConcludedLicensesAsConclusions = true;
  protected $overwriteDecisions = false;
  protected $addCopyrightInformation = false;
  protected $concludeLicenseDecisionType = DecisionTypes::IDENTIFIED;

  private function getFromArgs($args, $num, $expected="true")
  {
    return array_key_exists(self::$keys[$num],$args) ? $args[self::$keys[$num]] === $expected : false;
  }

  function __construct($args)
  {
    $this->createConcludedLicensesAsConclusions = $this->getFromArgs($args,0);
    $this->createLicensesInfosAsFindings = $this->getFromArgs($args,1);
    $this->createConcludedLicensesAsFindings = $this->getFromArgs($args,2);
    $this->overwriteDecisions = $this->getFromArgs($args,3);
    $this->addCopyrightInformation = $this->getFromArgs($args,4);

    $addConcludedAsDecisionsTBD = $this->getFromArgs($args,5);
    if($addConcludedAsDecisionsTBD)
    {
      $this->concludeLicenseDecisionType = DecisionTypes::TO_BE_DISCUSSED;
    }

    $this->createLicensesAsCandidate = $this->getFromArgs($args, 6, "candidate");

    $this->echoConfiguration();
  }

  private function var_dump($mixed = null) {
    ob_start();
    var_dump($mixed);
    $dump = ob_get_contents();
    ob_end_clean();
    return $dump;
  }

  public function echoConfiguration()
  {
    echo   "INFO: \$createLicensesAsCandidate is: "           .$this->var_dump($this->createLicensesAsCandidate);
    echo "\nINFO: \$createLicensesInfosAsFindings is: "       .$this->var_dump($this->createLicensesInfosAsFindings);
    echo "\nINFO: \$createConcludedLicensesAsFindings is: "   .$this->var_dump($this->createConcludedLicensesAsFindings);
    echo "\nINFO: \$createConcludedLicensesAsConclusions is: ".$this->var_dump($this->createConcludedLicensesAsConclusions);
    echo "\nINFO: \$overwriteDecisions is: "                  .$this->var_dump($this->overwriteDecisions);
    echo "\nINFO: \$addCopyrightInformation is: "             .$this->var_dump($this->addCopyrightInformation);
    echo "\nINFO: \$concludeLicenseDecisionType is: "         .$this->var_dump($this->concludeLicenseDecisionType);
    echo "\n";
  }

  /**
   * @return bool
   */
  public function isCreateLicensesAsCandidate()
  {
    return $this->createLicensesAsCandidate;
  }

  /**
   * @return bool
   */
  public function isCreateLicensesInfosAsFindings()
  {
    return $this->createLicensesInfosAsFindings;
  }

  /**
   * @return bool
   */
  public function isCreateConcludedLicensesAsFindings()
  {
    return $this->createConcludedLicensesAsFindings;
  }

  /**
   * @return bool
   */
  public function isCreateConcludedLicensesAsConclusions()
  {
    return $this->createConcludedLicensesAsConclusions;
  }

  /**
   * @return int
   */
  public function getConcludeLicenseDecisionType()
  {
    return $this->concludeLicenseDecisionType;
  }

  /**
   * @param bool $createConcludedLicensesAsConclusions
   * @return ReportImportConfiguration
   */
  public function setCreateConcludedLicensesAsConclusions($createConcludedLicensesAsConclusions)
  {
    $this->createConcludedLicensesAsConclusions = $createConcludedLicensesAsConclusions;
    return $this;
  }

  /**
   * @return bool
   */
  public function isOverwriteDecisions()
  {
    return $this->overwriteDecisions;
  }

  /**
   * @return bool
   */
  public function isAddCopyrightInformation()
  {
    return $this->addCopyrightInformation;
  }
}
