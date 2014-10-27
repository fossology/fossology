<?php
/*
Copyright (C) 2014, Siemens AG

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

use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Util\Object;

class LicenseProcessor extends Object
{
  /**
   * @param LicenseMatch[] $matches
   * @return array
   */
  public function extractLicenseMatches($matches)
  {
    $extractedMatches = array();

    foreach ($matches as $match)
    {
      $agentRef = $match->getAgentRef();
      $licenseRef = $match->getLicenseRef();

      $content = array(
          'licenseId' => $licenseRef->getId(),
          'agentRev' => $agentRef->getAgentRevision(),
          'percent' => $match->getPercentage(),
          'agentId' => $agentRef->getAgentId());


      $fileId = $match->getFileId();
      $agentName = $agentRef->getAgentName();
      $shortName = $licenseRef->getShortName();
      $agentId = $agentRef->getAgentId();
      $licenseFileId = $match->getLicenseFileId()?: 'empty';
      $extractedMatches[$fileId][$agentName][$shortName][$agentId][$licenseFileId] = $content;
    }

    return $extractedMatches;
  }

  /**
   * @param ClearingDecision[] $matches
   * @return array
   */
  public function extractBulkLicenseMatches($matches)
  {
    $extractedMatches = array();

    foreach ($matches as $match)
    {
      $fileId = $match->getPfileId();
      $highlightId = $match->getClearingId();
      $agentRef = "empty";
      if($match->getType()=="bulk") {
        foreach($match->getNegativeLicenses() as $license)
        {
          $agentName = _("Bulk removal");
          $agentId = 1;
          $content = array(
              'licenseId' => $license->getId(),
              'agentRev' => $agentRef,
              'percent' => null,
              'agentId' => $agentId);
          $shortName = $license->getShortName();
          $extractedMatches[$fileId][$agentName][$shortName][$agentId][$highlightId] = $content;
        }
        foreach($match->getPositiveLicenses() as $license)
        {
          $agentName = _("Bulk addition");
          $agentId = 2;
          $content = array(
              'licenseId' => $license->getId(),
              'agentRev' => $agentRef,
              'percent' => null,
              'agentId' => $agentId);
          $shortName = $license->getShortName();
          $extractedMatches[$fileId][$agentName][$shortName][$agentId][$highlightId] = $content;
        }
      }
    }
    return $extractedMatches;
  }

} 