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

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\View\HighlightRenderer;

class LicenseOverviewPrinter extends Object
{

  /**
   * @var UploadDao
   */
  private $uploadDao;

  /**
   * @var LicenseDao
   */
  private $licenseDao;

  /**
   * @var ClearingDao
   */
  private $clearingDao;

  /**
   * @var HighlightRenderer
   */
  private $highlightRenderer;

  function __construct(LicenseDao $licenseDao, UploadDao $uploadDao, ClearingDao $clearingDao, HighlightRenderer $highlightRenderer)
  {
    $this->uploadDao = $uploadDao;
    $this->licenseDao = $licenseDao;
    $this->clearingDao = $clearingDao;
    $this->highlightRenderer = $highlightRenderer;
  }

  /**
   * @param $hasDiff
   * @return string rendered legend box
   */
  function legendBox($hasDiff)
  {
    $colorMapping = $this->highlightRenderer->colorMapping;

    $output = '<b>' . _("Legend") . ':</b><br/>';
    if ($hasDiff)
    {
      $output .= _("license text");
      foreach (array(Highlight::MATCH => 'identical', Highlight::CHANGED => 'modified', Highlight::ADDED => 'added', Highlight::DELETED => 'removed',
                     Highlight::SIGNATURE => 'license relevant text', Highlight::KEYWORD => 'keyword')
               as $colorKey => $txt)
      {
        $output .= '<br/>'.$this->highlightRenderer->createStyle( $colorKey, $txt, $colorMapping) . $txt . '</span>';
      }
    } else
    {
      $output .= '<span style="background:' . $colorMapping['any'] . '">' . _("license relevant text") . "</span>";
    }
    return '<div style="background-color:white; padding:2px; border:1px outset #222222; width:150px; position:fixed; right:5px; bottom:5px;">' . $output . '</div>';
  }

  /**
   * @param $licenseMatches
   * @param $uploadId
   * @param $uploadTreeId
   * @param int $selectedAgentId
   * @param int $selectedLicenseId
   * @param int $selectedLicenseFileId
   * @param bool $hasHighlights
   * @param bool $showReadOnly
   * @return string
   */
  function createLicenseOverview($licenseMatches, $uploadId, $uploadTreeId, $selectedAgentId = 0, $selectedLicenseId = 0, $selectedLicenseFileId = 0, $hasHighlights = false, $showReadOnly = true)
  {
    $output = "<h3>" . _("Scanner results") . "</h3>\n";

    foreach ($licenseMatches as $fileId => $agents)
    {
      ksort($agents);
      foreach ($agents as $agentName => $foundLicenses)
      {
        $text = _("The $agentName license scanner found:");
        $output .= "$text <br><b>\n";
        foreach ($foundLicenses as $licenseShortname => $agentDetails)
        {
          $output .= $this->printLicenseNameAsLink($licenseShortname); //$Upload, $uploadTreeId,
          ksort($agentDetails);
          $keys = array_keys($agentDetails);
          $mostRecentAgentId = $keys[count($keys) - 1];
          $output .= $this->createPercentInfoAndAnchors($uploadId, $uploadTreeId,  $selectedAgentId, $selectedLicenseId, $selectedLicenseFileId, $hasHighlights, $agentDetails, $mostRecentAgentId, $showReadOnly);
          $output .= "<br/>\n";
        }
        $output = substr($output, 0, count($output) - 7);
        $output .= '</b><br/>';
        $output .= '<br/>';
      }

    }

    if ($hasHighlights)
    {
      $output .= $this->legendBox($selectedAgentId > 0 && $selectedLicenseId > 0);
    }
    if ($selectedAgentId > 0 && $selectedLicenseId > 0)
    {
      $format = GetParm("format", PARM_STRING);
      $output .= "<br/><a href='" .
          Traceback_uri() . "?mod=view-license&upload=$uploadId&item=$uploadTreeId&format=$format'>" . _("Exit") . "</a> " . _("specific license mode") . "<br/>";
    }
    return $output;
  }

  /**
   * @param $Upload
   * @param $Item
   * @param $selectedAgentId
   * @param $selectedLicenseId
   * @param $selectedLicenseFileId
   * @param $hasHighlights
   * @param $agentDetails
   * @param $mostRecentAgentId
   * @param $showReadOnly
   * @return string
   */
  private function createPercentInfoAndAnchors($Upload, $Item, $selectedAgentId, $selectedLicenseId, $selectedLicenseFileId, $hasHighlights, $agentDetails, $mostRecentAgentId, $showReadOnly)
  {
    $output = "(";
    $foundIndex = 1;

    foreach ($agentDetails[$mostRecentAgentId] as $licenseFileId => $scanDetails)
    {
      $licenseId = $scanDetails['licenseId'];
      $foundLabel = '#' . $foundIndex++;
      if ($showReadOnly)
      {
        $output .= $this->createAnchor($Upload, $Item, $selectedAgentId, $selectedLicenseId, $selectedLicenseFileId, $hasHighlights, $mostRecentAgentId, $licenseId, $licenseFileId, $foundLabel);
      } else
      {
        $output .= $foundLabel;
      }
      if (!empty($scanDetails['percent']))
      {
        $output .= ': ' . $scanDetails['percent'] . '%';
      }
      $output .= ", ";
    }
    $output = substr($output, 0, count($output) - 3);
    $output .= ')';
    return $output;
  }

  /**
   * @param $Upload
   * @param $Item
   * @param $selectedAgentId
   * @param $selectedLicenseId
   * @param $selectedLicenseFileId
   * @param $hasHighlights
   * @param $mostRecentAgentId
   * @param $licenseId
   * @param $licenseFileId
   * @param $foundLabel
   * @return string
   */
  private function createAnchor($Upload, $Item, $selectedAgentId, $selectedLicenseId, $selectedLicenseFileId, $hasHighlights, $mostRecentAgentId, $licenseId, $licenseFileId, $foundLabel)
  {
    // TODO include pages in here... unfortunately this is not easy but it should be possible
    $format = GetParm("format", PARM_TEXT) ?: "text";
    $linkTarget = Traceback_uri() . "?mod=view-license&upload=$Upload&item=$Item&format=$format&licenseId=$licenseId&agentId=$mostRecentAgentId&highlightId=$licenseFileId#highlight";
    if (intval($licenseId) != $selectedLicenseId || intval($licenseFileId) != $selectedLicenseFileId || intval($mostRecentAgentId) != $selectedAgentId)
    {
      $output = '<a title="' . _("Show This License Diff") . '" href="' . $linkTarget . '">' . $foundLabel . '</a>';
    } else
    {
      $output = $foundLabel;
      if ($hasHighlights)
      {
        $output .= '<a title="' . _("Jump To This License Diff") . '" href="' . $linkTarget . '">&nbsp;&#8595;&nbsp;</a>';
      }
    }
    return $output;
  }

  /**
   * @param $Upload
   * @param $uploadTreeId
   * @param bool $noConcludedLicenseYet
   * @return array
   */
  public function createEditButton($Upload, $uploadTreeId, $noConcludedLicenseYet=true)
  {
    $output ="";
    /** edit this license */
    $col = $noConcludedLicenseYet ? '#ff0000' : '#00aa00';
    $text =$noConcludedLicenseYet ? _("Add concluded license") : _("Edit concluded license");
    /** go to the license change page */
    if (plugin_find_id('change_license') >= 0)
    {
      $editLicenseText = _("Edit the license of this file");
      $output = '<a title="' . $editLicenseText . '" href="' . Traceback_uri() . '?mod=change_license';
      $output .= "&upload=$Upload&item=$uploadTreeId";


      $output .= '" style="color:'. $col .';font-style:mono" class="buttonLink">' . $text . '</a><br/>';


    }
    return $output;
  }


  /**
   * @param $licenseShortName
   * @param string $licenseFullName
   * @return array
   */
  public function printLicenseNameAsLink($licenseShortName, $licenseFullName = "")
  {
    if (empty($licenseFullName))
    {
      $displayName = $licenseShortName;
    } else
    {
      $displayName = $licenseFullName;
    }
    $text = _("License Reference");
    $text2 = _("License Text");
    $output = "<a title='$text' href='javascript:;'";
    $output .= " onClick=\"javascript:window.open('";
    $output .= Traceback_uri();
    $output .= "?mod=view-license";
    $output .= "&lic=";
    $output .= urlencode($licenseShortName);
    $output .= "','$text2','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\"";
    $output .= ">$displayName";
    $output .= "</a> ";
    return $output;
  }

  /**
   * @param ClearingDecision[] $clearingDecWithLicenses
   * @return string
   */
  public function createRecentLicenseClearing($clearingDecWithLicenses)
  {
    $output = "<h3>" . _("Concluded license") . "</h3>\n";
     $cd= $this->clearingDao->newestEditedLicenseSelector->selectNewestEditedLicensePerFileID($clearingDecWithLicenses);

    /**
     *@var  ClearingDecision $cd
     */
    if($cd != null  )
    {
      /**
       * @var ClearingDecision $theLicense
       */
      $auditedLicenses = $cd->getLicenses();
      /**
       * @var LicenseRef[] $auditedLicenses
       */
      foreach ($auditedLicenses as $license)
      {
        $output .= $this->printLicenseNameAsLink($license->getShortName(), $license->getFullName());
        $output .= ", ";
      }
      $output = substr($output, 0, count($output) - 3);
      return $output;
    }
    else
    {
      return "";
    }
  }

  /**
   * @param $clearingDecWithLicenses
   * @return string
   */
  public function createWrappedRecentLicenseClearing($clearingDecWithLicenses)
  {
    $foundNothing=false;
    $output = "<div id=\"recentLicenseClearing\" name=\"recentLicenseClearing\">";
    if (!empty($clearingDecWithLicenses))
    {
      $output_TMP = $this->createRecentLicenseClearing($clearingDecWithLicenses);
      if(empty($output_TMP)) {
        $foundNothing =true;
      }
      else {
        $output .= $output_TMP;
      }
    }
    $output .= "</div>";
    return array($output, $foundNothing );
  }


} 