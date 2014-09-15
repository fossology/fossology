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
use Fossology\Lib\Data\DatabaseEnum;
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
   * @param NewestEditedLicenseSelector $newestEditedLicenseSelector
   * @param $uploadDao
   * @param $licenseDao
   * @param $clearingDao
   */

  function __construct(NewestEditedLicenseSelector $newestEditedLicenseSelector, UploadDao $uploadDao, LicenseDao $licenseDao, ClearingDao $clearingDao )
  {
    $this->newestEditedLicenseSelector = $newestEditedLicenseSelector;
    $this->uploadDao = $uploadDao;
    $this->licenseDao = $licenseDao;
    $this->clearingDao = $clearingDao;
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
      $output .= "<option value=\"" . $licenseRef->getId() . "\">" . $licenseRef->getFullName() . "</option>\n";
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
    $fileTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId, "uploadtree");
    $licenses = $this->licenseDao->getFileLicenseMatches($fileTreeBounds);
    $licenseList = array();

    foreach ($licenses as $licenseMatch)
    {
      $licenseList[] = $licenseMatch->getLicenseRef();

    }
    return $licenseList;
  }


  /**
   * @param $uploadTreeId
   * @return string
   * creates two licenseListSelects and buttons to transfer licenses and two text boxes
   */
  public function createChangeLicenseForm($uploadTreeId) {
    $licenseRefs = $this->licenseDao->getLicenseRefs();

    $clearingDecWithLicenses = $this->clearingDao->getFileClearings($uploadTreeId);

    $preSelectedLicenses = null;
    if (!empty($clearingDecWithLicenses))
    {
      $filteredFileClearings = $this->clearingDao->newestEditedLicenseSelector->extractGoodClearingDecisionsPerFileID($clearingDecWithLicenses, true);
      if (!empty ($filteredFileClearings))
      {
        $preSelectedLicenses = reset($filteredFileClearings)->getLicenses();
      }
    }

    if ($preSelectedLicenses === null)
    {
      $preSelectedLicenses = $this->getAgentSuggestedLicenses($uploadTreeId);
    }

    $this->filterLists($licenseRefs, $preSelectedLicenses);

    $output = "";

    $output .= "<div class=\"modal\" id=\"userModal\" hidden>";
    $output .= "<form name=\"licenseListSelect\">";
    $output .= " <table border=\"0\"> <tr>";
    $text = _("Available licenses:");
    $output .= "<td><p>$text<br>";
    $output .= $this->createListSelect("licenseLeft", $licenseRefs);
    $output .= "</p></td>";

    $output .= "<td align=\"center\" valign=\"middle\">";
    $output .= $this->createLicenseSwitchButtons();
    $output .= "</td>";

    $text = _("Selected licenses:");
    $output .= "<td><p>$text<br>";
    $output .= $this->createListSelect("licenseRight", $preSelectedLicenses);
    $output .= "</p></td>";

    $output .= "<td>";
    $text = _("Comment (private)");
    $output .= "$text:<br><textarea name=\"comment\" id=\"comment\" type=\"text\" cols=\"50\" rows=\"8\" maxlength=\"150\"></textarea>";
    $text = _("Remark (public)");
    $output .= "<p>$text:<br><textarea name=\"remark\" id=\"remark\"   type=\"text\"  cols=\"50\" rows=\"10\" maxlength=\"150\"></textarea></p>";
    $output .= "</td>";


    $output .= "</tr>";
    $output .= "<tr><td colspan='2'>";
    $output .= "" . _("License decision scope") . "<br/>";
    $clearingScopes = $this->clearingDao->getClearingScopes();
    $output .= DatabaseEnum::createDatabaseEnumSelect("scope", $clearingScopes, 3);
    $output .= "</td>";

    $output .= "<td colspan='2'>" . _("License decision type") . "<br/>";
    $clearingTypes = $this->clearingDao->getClearingTypes();
    $output .= DatabaseEnum::createDatabaseEnumSelect("type", $clearingTypes, 1);

    $output .= "</td></tr>";
    $output .= "<tr><td>&nbsp;</td></tr>";
    $output .= "<tr><td colspan='2'>";
    $output .= "<button  type=\"button\" autofocus  onclick='performPostRequest()'>Submit</button>";
    $output .= "</td>";
    $output .= "<td colspan='2'>";
    $output .= "<button  type=\"button\" autofocus  onclick='performNoLicensePostRequest()'>No License contained</button>";
    $output .= "</td>";
    $output .= "</tr>";
    $output .= "<tr><td>&nbsp;</td></tr></table>";
    $output .= "<input name=\"licenseNumbersToBeSubmitted\" id=\"licenseNumbersToBeSubmitted\" type=\"hidden\" value=\"\" />\n";
    $output .= "<input name=\"uploadTreeId\" id=\"uploadTreeId\" type=\"hidden\" value=\"" . $uploadTreeId . "\" />\n </form>\n";
    $output .= "</div>";

    return $output;
  }


  public function createBulkForm($uploadTreeId) {
    $output = "";
    $allLicenseRefs = $this->licenseDao->getLicenseRefs();
    $output .= "<div class=\"modal\" id=\"bulkModal\" hidden>";
    $output .= "<form name=\"bulkForm\">";
    $text = _("Bulk recognition");
    $output .= "<h2>$text</h2>";
    $output .= "<select name=\"bulkRemoving\" id=\"bulkRemoving\">";
    $output .= "<option value=\"f\">Add license</option>";
    $output .= "<option value=\"t\">Remove license</option>";
    $output .= "</select>";
    $output .= $this->createListSelect("bulkLicense", $allLicenseRefs, false, 1);
    $text = _("reference text");
    $output .= "<br>$text:<br><textarea name=\"bulkRefText\" id=\"bulkRefText\" type=\"text\" cols=\"80\" rows=\"12\"></textarea><br>";
    $output .= "<br><button type=\"button\" onclick='scheduleBulkScan()'>Schedule Bulk scan</button>";
    $output .= "<br><span id=\"bulkIdResult\" name=\"bulkIdResult\" hidden></span>";
    $output .= "<br><span id=\"bulkJobResult\" name=\"bulkJobResult\" hidden>a bulk job has completed</span>";
    $output .= "</div>";
    $output .= "<input name=\"uploadTreeId\" id=\"uploadTreeId\" type=\"hidden\" value=\"" . $uploadTreeId . "\" />\n </form>\n";

    return $output;
  }

}