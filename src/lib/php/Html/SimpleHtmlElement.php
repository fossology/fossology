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

namespace Fossology\Lib\Html;


use Fossology\Lib\Util\Object;

class SimpleHtmlElement extends Object implements HtmlElement
{

  private $name;
  private $attributes;

  function __construct($name, $attributes=array()) {
    $this->name = $name;
    $this->attributes = $attributes;
  }

  /**
   * @param string $name
   * @param string $value
   */
  function setAttribute($name, $value)
  {
    $this->attributes[$name] = $value;
  }

  function getOpeningText()
  {
    $openingText = "<" . $this->name;
    foreach($this->attributes as $name => $value) {
      $openingText .= " $name=\"$value\"";
    }
    return $openingText . ">";
  }

  function getClosingText()
  {
    return "</$this->name>";
  }
}