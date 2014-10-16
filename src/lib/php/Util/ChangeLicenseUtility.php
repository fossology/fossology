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
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;

class ChangeLicenseUtility extends Object
{

  /**
   * @var NewestEditedLicenseSelector $newestEditedLicenseSelector
   */
  private $newestEditedLicenseSelector;
  /**
   * @var UploadDao $uploadDao
   */
  private $uploadDao;

  /**
   * @var LicenseDao $licenseDao
   */
  private $licenseDao;

  /**
   * @var ClearingDao $clearingDao
   */
  private $clearingDao;

  /**
   * @var DecisionTypes
   */
  private $clearingDecisionTypes;

  /**
   * @param NewestEditedLicenseSelector $newestEditedLicenseSelector
   * @param UploadDao $uploadDao
   * @param LicenseDao $licenseDao
   * @param ClearingDao $clearingDao
   * @param DecisionTypes $clearingDecisionTypes
   */

  function __construct(NewestEditedLicenseSelector $newestEditedLicenseSelector, UploadDao $uploadDao, LicenseDao $licenseDao, ClearingDao $clearingDao, DecisionTypes $clearingDecisionTypes)
  {
    $this->newestEditedLicenseSelector = $newestEditedLicenseSelector;
    $this->uploadDao = $uploadDao;
    $this->licenseDao = $licenseDao;
    $this->clearingDao = $clearingDao;
    $this->clearingDecisionTypes = $clearingDecisionTypes;
  }

  /**
   * @param $tableName
   * @param ClearingDecision[] $clearingDecWithLicenses
   * @param $user_pk
   * @return string
   */
  function printClearingTable($tableName, $clearingDecWithLicenses, $user_pk)
  {
    $output = "<div class='scrollable2'>";
    $output .= "<table border=\"1\" id=\"$tableName\" name=\"$tableName\">\n";
    $output .= $this->printClearingTableInnerHtml($clearingDecWithLicenses, $user_pk);
    $output .= "\n</table>";
    $output .= "<table><tr class='inactiveClearing'><td>Inactive concluded licenses decisions</td></tr></table>";
    $output .= "</div>";
    return $output;
  }

  /**
   * @param ClearingDecision[] $clearingDecWithLicenses
   * @param $user_pk
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

      $output .= "</tr>";
    }
    return $output;
  }


  /**
   * @param ClearingDecision[] $clearingDecWithLicenses
   * @return array
   */
  function getClearingHistory($clearingDecWithLicenses)
  {
    $table = array();
    foreach ($clearingDecWithLicenses as $clearingDecWithLic)
    {
      $licenseNames = array();
      foreach ($clearingDecWithLic->getLicenses() as $lic)
      {
        $licenseShortName = $lic->getShortName();
        if ($lic->isRemoved()) {
          $licenseShortName = "<span style=\"color:red\">" . $licenseShortName . "</span>";
        }
        $licenseNames[$lic->getShortName()] = $licenseShortName;
      }
      ksort($licenseNames, SORT_STRING);
      $row = array(
          'date'=>$clearingDecWithLic->getDateAdded(), //->format('Y-m-d'),
          'username'=>$clearingDecWithLic->getUserName(),
          'scope'=>$clearingDecWithLic->getScope(),
          'type'=>$this->clearingDecisionTypes->getTypeName($clearingDecWithLic->getType()),
          'licenses'=>implode(", ", $licenseNames));
      $table[] = $row;

    }
    return $table;
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
   * @param string $listElementName
   * @param LicenseRef[] $licenseRefArray
   * @return string
   */
  function createListSelect($listElementName, $licenseRefArray, $multiple=true, $size=20)
  {
    $output = "<select name=\"$listElementName\" id=\"$listElementName\" size=\"$size\" ";
    if ($multiple) {
      $output .= "multiple=\"multiple\" ";
    }
    $output .= "style=\"min-width:200px\" >\n"; //style=\"min-width:200px;max-width:400px;\"
    foreach ($licenseRefArray as $licenseRef)
    {
      $uri = Traceback_uri() . "?mod=popup-license" . "&lic=" . urlencode($licenseRef->getShortName());
      $title = _("License Text");
      $sizeInfo = 'width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes';
      $output .= '<option value="' . $licenseRef->getId() . '" title="'.$licenseRef->getFullName().'" '
                      ."ondblclick=\"javascript:window.open('$uri','$title','$sizeInfo');\" >"
                   . $licenseRef->getShortName() 
                  . "</option>\n";
    }
    $output .= "</select>";
    return $output;
  }


  /**
   * @param $uploadTreeId
   * @return LicenseRef[]
   */
  private function getAgentSuggestedLicenses($uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId, "uploadtree");
    $licenses = $this->licenseDao->getAgentFileLicenseMatches($itemTreeBounds);
    $licenseList = array();

    foreach ($licenses as $licenseMatch)
    {
      $licenseList[] = $licenseMatch->getLicenseRef();

    }
    return $licenseList;
  }



  /**
   * @param $uploadTreeId
   * @return array
   */
  public function createChangeLicenseForm($uploadTreeId=-1) {
    $licenseRefs = $this->licenseDao->getLicenseRefs();

    $rendererVars = array();
    $rendererVars['licenseLeftSelect'] = $this->createListSelect("licenseLeft", $licenseRefs);
    return $rendererVars;
  }


  public function createBulkForm($uploadTreeId=-1) {
    $rendererVars = array();
    $rendererVars['bulkUri'] = Traceback_uri() . "?mod=popup-license";
    $rendererVars['licenseArray'] = $this->licenseDao->getLicenseArray();
    // print_r($rendererVars['licenseArray']);
    return $rendererVars;
  }
}