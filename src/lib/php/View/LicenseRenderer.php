<?php
/*
Copyright (C) 2014, Siemens AG
Johannes Najjar

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
namespace Fossology\Lib\View;

use Fossology\Lib\Data\License;

class LicenseRenderer
{
  /**
   * @param License|null $license
   * @return string
   */
  public function renderFullText($license)
  {
    $renderedText = "";
    if ($license)
    {
      $renderedText .= "<div class='text'>";
      $renderedText .= "<h1>License: " . $license->getShortName() . "</h1>\n";
      $licenseFullName = $license->getFullName();
      if ($licenseFullName)
      {
        $renderedText .= "<h2>License Fullname: " . $licenseFullName . "</h2>\n";
      }
      $licenseUrl = $license->getUrl();
      if ($licenseUrl && (strtolower($licenseUrl) != 'none'))
      {
        $renderedText .= "<b>Reference URL:</b> <a href=\"" . $licenseUrl . "\" target=_blank> " . $licenseUrl . "</a><br>\n";
      }
      $licenseText = $license->getText();
      if ($licenseText)
      {
        $renderedText .= "<b>License Text:</b>\n" . $licenseText;
      }
      $renderedText .= "<hr>\n";
      $renderedText .= "</div>";
    } else
    {
      $renderedText .= "<div class='text'>";
      $renderedText .= "<h1>Original license text is not in the FOSSology database.</h1>\n";
      $renderedText .= "<hr>\n";
      $renderedText .= "</div>";
    }
    return $renderedText;
  }

  public function renderLicenseHistogram($licenseHistogram, $editedLicensesHist, $uploadTreeId, $tagId, $fileCount)
  {
    /* Write license histogram to $VLic  */
    $rendered = "<table border=0 class='semibordered' id='lichistogram'></table>\n";
    list($jsBlockLicenseHist, $uniqueLicenseCount, $totalScannerLicenseCount, $scannerUniqueLicenseCount,
        $noScannerLicenseFoundCount, $editedTotalLicenseCount, $editedUniqueLicenseCount, $editedNoLicenseFoundCount)
        = $this->createLicenseHistogramJSarray($licenseHistogram, $editedLicensesHist, $uploadTreeId, $tagId);

    $rendered .= "<br/><br/>";
    $rendered .= _("Hint: Click on the license name to search for where the license is found in the file listing.") . "<br/><br/>\n";
    
    if ($uniqueLicenseCount==0)
    {
      $rendered = '<b>'._('Neither license found by scanner nor concluded by user.').'</b>';
    }

    $rendered .= $this->totalCountHist($fileCount, $uniqueLicenseCount, $scannerUniqueLicenseCount,
        $editedUniqueLicenseCount, $totalScannerLicenseCount, $noScannerLicenseFoundCount, $editedTotalLicenseCount, $editedNoLicenseFoundCount);

    return array($jsBlockLicenseHist, $rendered);
  }

  /**
   * @param array $scannerLics
   * @param array $editedLics
   * @param $uploadTreeId
   * @param $tagId
   * @return array
   */
  public function createLicenseHistogramJSarray($scannerLics, $editedLics, $uploadTreeId, $tagId)
  {
    $agentId = GetParm('agentId', PARM_INTEGER);
            
    $allScannerLicenseNames = array_keys($scannerLics);
    $allEditedLicenseNames = array_keys($editedLics);

    $allLicNames = array_unique(array_merge($allScannerLicenseNames, $allEditedLicenseNames));

    $uniqueLicenseCount = 0;

    $totalScannerLicenseCount = 0;
    $scannerUniqueLicenseCount = count (array_unique( $allScannerLicenseNames ) );
    $noScannerLicenseFoundCount = 0;

    $editedTotalLicenseCount = 0;
    $editedUniqueLicenseCount = 0;
    $editedNoLicenseFoundCount = 0;

    $tableData = array();
    foreach ($allLicNames as $licenseShortName)
    {
      $uniqueLicenseCount++;

      if (array_key_exists($licenseShortName, $scannerLics))
      {
        $count = $scannerLics[$licenseShortName];
      } else
      {
        $count = 0;
      }


      if (array_key_exists($licenseShortName, $editedLics))
      {
        $editedCount = $editedLics[$licenseShortName];
        $editedUniqueLicenseCount++;
      } else
      {
        $editedCount = 0;
      }


      $totalScannerLicenseCount += $count;
      $editedTotalLicenseCount += $editedCount;

      if ($licenseShortName == "No_license_found")
      {
        $noScannerLicenseFoundCount = $count;
        $editedNoLicenseFoundCount = $editedCount;
      }
      //else
      {

        /*  Count  */
        if ($count > 0)
        {
          $scannerCountLink = "<a href='";
          $scannerCountLink .= Traceback_uri();
          $tagClause = ($tagId) ? "&tag=$tagId" : "";
          if ($agentId)
          {
            $tagClause .= "&agentId=$agentId";
          }
          $scannerCountLink .= "?mod=license_list_files&item=$uploadTreeId&lic=" . urlencode($licenseShortName) . $tagClause . "'>$count</a>";
        } else
        {
          $scannerCountLink = "0";
        }


        if ($editedCount > 0)
        {
          $editedLink = $editedCount;
        } else
        {
          $editedLink = "0";
        }

        $tableData[] = array($scannerCountLink, $editedLink, $licenseShortName);
      }
    }

    $tableColumns = array(
        array("sTitle" => _("Scanner Count"), "sClass" => "right", "sWidth" => "5%", "bSearchable" => false, "sType" => "num-html"),
        array("sTitle" => _("Concluded License Count"), "sClass" => "right", "sWidth" => "5%", "bSearchable" => false, "sType" => "num-html"),
        array("sTitle" => _("License Name"), "sClass" => "left", "mRender" => '###dressContents###'),
    );

    $tableSorting = array(
        array(0, "desc"),
        array(1, "desc"),
        array(2, "desc")
    );

    $tableLanguage = array(
        "sInfo" => "Showing _START_ to _END_ of _TOTAL_ licenses",
        "sSearch" => "Search _INPUT_ <button onclick='clearSearchLicense()' >" . _("Clear") . "</button>",
        "sLengthMenu" => "Display <select><option value=\"10\">10</option><option value=\"25\">25</option><option value=\"50\">50</option><option value=\"100\">100</option></select> licenses"
    );

    $dataTableConfig = array(
        "aaData" => $tableData,
        "aoColumns" => $tableColumns,
        "aaSorting" => $tableSorting,
        "iDisplayLength" => 25,
        "oLanguage" => $tableLanguage
    );

    $dataTableJS = str_replace('"###dressContents###"', "dressContents", json_encode($dataTableConfig));

    $rendered = "<script>
      function createLicHistTable() {
        dTable=$('#lichistogram').dataTable(" . $dataTableJS . ");
    }
</script>";

    return array($rendered, $uniqueLicenseCount, $totalScannerLicenseCount, $scannerUniqueLicenseCount, $noScannerLicenseFoundCount, $editedTotalLicenseCount, $editedUniqueLicenseCount, $editedNoLicenseFoundCount);
  }

  /**
   * @param $fileCount
   * @param $uniqueLicenseCount
   * @param $scannerUniqueLicenseCount
   * @param $editedUniqueLicenseCount
   * @param $totalScannerLicenseCount
   * @param $noScannerLicenseFoundCount
   * @param $editedTotalLicenseCount
   * @param $editedNoLicenseFoundCount
   * @internal param $rendered
   * @return string
   */
  public function totalCountHist($fileCount, $uniqueLicenseCount, $scannerUniqueLicenseCount, $editedUniqueLicenseCount, $totalScannerLicenseCount, $noScannerLicenseFoundCount, $editedTotalLicenseCount, $editedNoLicenseFoundCount)
  {
    $rendered = "<table border=\"0\" cellpadding=\"2\" id='licsummary' class='simpleTable' >";
    $rendered .= "<tr><td class='odd'>" . _("Unique licenses") . "</td> <td align='right' class='odd'>$uniqueLicenseCount</td>";
    $rendered .= "<td align='right' class='even'>$fileCount</td><td class='even'>" . _("Files") . "</td></tr>";
    $rendered .= "<tr><td class='even'>" . _("Unique scanner detected licenses") . "</td><td align='right' class='even'>$scannerUniqueLicenseCount</td>";
    $rendered .= "<td align='right' class='odd'>$editedUniqueLicenseCount</td><td class='odd'>" . _("Unique concluded licenses") . "</td></tr>";

    $scannerLicenseCount = $totalScannerLicenseCount - $noScannerLicenseFoundCount;
    $rendered .= "<tr><td class='odd'>" . _("Licenses found") . "</td><td align='right' class='odd'>$scannerLicenseCount</td>";
    $editedLicenseCount = $editedTotalLicenseCount - $editedNoLicenseFoundCount;
    $rendered .= "<td align='right' class='even'>$editedLicenseCount</td><td class='even'>" . _("Licenses concluded") . "</td></tr>";

    $rendered .= "<tr><td class='even'>" . _("Files with no detected licenses") . "</td><td align='right' class='even'>$noScannerLicenseFoundCount</td>";
    $rendered .= "<td align='right' class='odd'>$editedNoLicenseFoundCount</td><td class='odd'>" . _("Concluded files with no license information") . "</td></tr>"; //TODO find a better wording, I mean files where a human confirmed that there is no relevant license information contained

    $rendered .= "</table>";
    return $rendered;
  }


} 