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

      $extractedMatches[$match->getFileId()][$agentRef->getAgentName()][$licenseRef->getShortName()][$agentRef->getAgentId()][$match->getLicenseFileId()] =
          array(
              'licenseId' => $licenseRef->getId(),
              'agentRev' => $agentRef->getAgentRevision(),
              'percent' => $match->getPercent());
    }

    return $extractedMatches;
  }
} 