<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: Johannes Najjar
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
 ***********************************************************/

namespace Fossology\Lib\Util;

use Fossology\Lib\BusinessRules\NewestEditedLicenseSelector;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Data\LicenseRef;

class ChangeLicenseUtility extends Object
{

  /**
   * @var NewestEditedLicenseSelector $newestEditedLicenseSelector
   */
  private $newestEditedLicenseSelector;

  function __construct()
  {
    $this->newestEditedLicenseSelector = new NewestEditedLicenseSelector();
  }

  /**
   * @param $tableName
   * @param ClearingDecision[] $clearingDecWithLicenses
   * @param $user_pk
   * @return string
   */
  function printClearingTable($tableName, $clearingDecWithLicenses, $user_pk)
  {
    $output = "<table border=\"1\" id=\"$tableName\" name=\"$tableName\">\n";
    $output .= $this->printClearingTableInnerHtml($clearingDecWithLicenses, $user_pk);
    $output .= "\n</table>";
    $output .= "<table><tr class='inactiveClearing'><td>Inactive concluded licenses decisions</td></tr></table>";
    return $output;
  }

  /**
   * @param ClearingDecision[] $clearingDecWithLicenses
   * @param $user_pk
   * @param $output
   * @return string
   */
  function printClearingTableInnerHtml($clearingDecWithLicenses, $user_pk)
  {
    $output = "<tr><th>" . _("Date") . "</th><th>" . _("Username") . "</th><th>" . _("Scope") . "</th><th>" . _("Type") . "</th><th>" . _("Licenses") . "</th><th>" . _("Comment") . "</th><th>" . _("Remark") . "</th></tr>";
    foreach ($clearingDecWithLicenses as $clearingDecWithLic)
    {

      if ($this->newestEditedLicenseSelector->isInactive($clearingDecWithLic))
      {
        $output .= "<tr class='inactiveClearing'>";
      } else
      {
        $output .= "<tr>";
      }
      $output .= "<td>" . $clearingDecWithLic->getDateAdded()->format('Y-m-d') . "</td>";
      $output .= "<td>" . $clearingDecWithLic->getUserName() . "</td>";
      $output .= "<td>" . $clearingDecWithLic->getScope() . "</td>";
      $output .= "<td>" . $clearingDecWithLic->getType() . "</td>";
      $licenseNames = array();
      foreach ($clearingDecWithLic->getLicenses() as $lic)
      {
        $licenseNames[] = $lic->getShortName();
      }
      $output .= "<td>" . implode(", ", $licenseNames) . "</td>";
      if ($user_pk == $clearingDecWithLic->getUserId())
      {
        $output .= "<td>" . $clearingDecWithLic->getComment() . "</td>";
      } else
      {
        $output .= "<td>--private--</td>";
      }
      $output .= "<td>" . $clearingDecWithLic->getReportinfo() . "</td>";

      $output .= "</tr>";
    }
    return $output;
  }

  /**
   * @param $output
   * @return string
   */
  function createLicenseSwitchButtons()
  {
    $output = "<input type=\"button\" value=\"&gt;\" onclick=\"moveLicense(this.form.licenseLeft, this.form.licenseRight);\" /><br />\n";
    $output .= "<input type=\"button\" value=\"&lt;\" onclick=\"moveLicense(this.form.licenseRight, this.form.licenseLeft);\" />";
    return $output;
  }


  /**
   * @param LicenseRef[] $bigList
   * @param LicenseRef[] $smallList
   */
  function filterLists(&$bigList, &$smallList)
  {
    $bigListcopy = $bigList;
    $smallListcopy = $smallList;

    $bigList = array();
    $smallList = array();

    foreach ($bigListcopy as $license)
    {
      $flag = false;
      foreach ($smallListcopy as $suggestedLic)
      {
        if ($license->getShortName() == $suggestedLic->getShortName()) $flag = true;
      }
      if ($flag)
      {
        $smallList[] = $license;
      } else
      {
        $bigList[] = $license;
      }
    }
  }

  /**
   * @param string $selectElementName
   * @param DatabaseEnum[] $databaseEnum
   * @param int $selectedValue
   * @return array
   */
  function createDatabaseEnumSelect($selectElementName, $databaseEnum, $selectedValue)
  {
    $output = "<select name=\"$selectElementName\" id=\"$selectElementName\" size=\"1\">\n";
    foreach ($databaseEnum as $option)
    {
      $output .= "<option ";
      if ($option->getOrdinal() == $selectedValue) $output .= " selected ";
      $output .= "value=\"" . $option->getOrdinal() . "\">" . $option->getName() . "</option>\n";
    }
    $output .= "</select>";
    return $output;
  }


  /**
   * @param string $listElementName
   * @param LicenseRef[] $licenseRefArray
   * @return string
   */
  function createListSelect($listElementName, $licenseRefArray)
  {
    $output = "<select name=\"$listElementName\" id=\"$listElementName\" size=\"20\" multiple=\"multiple\" style=\"min-width:200px\" >\n"; //style=\"min-width:200px;max-width:400px;\"
    foreach ($licenseRefArray as $licenseRef)
    {
      $output .= "<option value=\"" . $licenseRef->getId() . "\">" . $licenseRef->getFullName() . "</option>\n";
    }
    $output .= "</select>";
    return $output;
  }


}