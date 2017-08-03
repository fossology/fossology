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
  protected $createLicensesAsCandidate = true;
  protected $createLicensesInfosAsFindings = true;
  protected $createConcludedLicensesAsFindings = false;
  protected $createConcludedLicensesAsConclusions = true;
  protected $concludeLicenseDecisionType = DecisionTypes::IDENTIFIED;

  function __construct()
  {
  }

  public function addAsTBD()
  {
    $this->concludeLicenseDecisionType = DecisionTypes::TO_BE_DISCUSSED;
    return $this;
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
}
