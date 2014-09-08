<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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

namespace Fossology\Lib\Data;

use Fossology\Lib\Util\Object;

class DatabaseEnum extends Object
{
  /**
   * @var int
   */
  private $ordinal;
  /**
   * @var string
   */
  private $name;

  public function __construct($ordinal, $name)
  {
    $this->ordinal = $ordinal;
    $this->name = $name;
  }

  /**
   * @return integer
   */
  public function getOrdinal()
  {
    return $this->ordinal;
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }


  /**
   * @param string $selectElementName
   * @param DatabaseEnum[] $databaseEnum
   * @param int $selectedValue
   * @return array
   */
 static function createDatabaseEnumSelect($selectElementName, $databaseEnum, $selectedValue)
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

}