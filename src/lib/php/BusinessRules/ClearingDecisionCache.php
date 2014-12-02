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

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Util\Object;
use Fossology\Lib\Data\ClearingDecision;

class ClearingDecisionCache extends Object
{
  const KEYREPO = "all";

  /** @var ClearingDecision[][] */
  private $decisionMap;

  /**
   * @parm ClearingDecision[][] $decisionMap
   */
  function __construct($decisionMap)
  {
    $this->decisionMap = $decisionMap;
  }

  /**
   * @return ClearingDecision|false
   */
  public function getDecisionOf($itemId, $pfileId)
  {
    $decisionMap = $this->decisionMap;
    if (array_key_exists($pfileId, $decisionMap))
    {
      $pfileMap = $decisionMap[$pfileId];
      if (array_key_exists($itemId, $pfileMap))
      {
        return $pfileMap[$itemId];
      }
      else
      {
        return $pfileMap[self::KEYREPO];
      }
    }

    return false;
  }

  public function getAllLicenseNames()
  {
    $decisionMap = $this->decisionMap;
    $result = array();
    foreach($decisionMap as $pfileId => $pFileMap)
    {
      /** @var ClearingDecision $decision */
      foreach($pFileMap as $itemId => $decision)
      {
        foreach($decision->getPositiveLicenses() as $toAdd)
        {
          array_push($result, $toAdd->getShortName());
        }

      }
    }

    return $result;
  }
}