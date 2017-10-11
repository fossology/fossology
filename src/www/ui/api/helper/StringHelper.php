<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

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
 ***************************************************************/

namespace www\ui\api\helper;


class StringHelper
{
  /**
   * Removes lines from string
   * @param $lineNumbersToRemove
   * @param $wholeString
   * @return string
   */
  function removeLines($lineNumbersToRemove, $wholeString)
  {
    $splitString = explode("\n", $wholeString);
    for($i=0; $i < sizeof($splitString); $i++)
    {
      if(in_array($i, $lineNumbersToRemove))
      {
        unset($splitString[$i]);
      }
    }
    return implode("\n",$splitString);
  }

  /**
   * @param $wholeString - the string that needs to be cut
   * @param $numbersToRemove - numbers to remove from string
   * @param $outerLowerString - the string on the bottom of the file
   * @return string - content
   */
  function getContentBetweenString($wholeString, $numbersToRemove, $outerLowerString)
  {
    //remove numbers in array from string
    $cutString = $this->removeLines($numbersToRemove, $wholeString);
    $contentString = substr($cutString, 0, strpos($cutString, $outerLowerString));
    return $contentString;
  }
}
