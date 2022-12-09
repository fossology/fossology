<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Html;

class SimpleHtmlElement implements HtmlElement
{

  private $name;
  private $attributes;

  function __construct($name, $attributes = array())
  {
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
    foreach ($this->attributes as $name => $value) {
      $openingText .= " $name=\"$value\"";
    }
    return $openingText . ">";
  }

  function getClosingText()
  {
    return "</$this->name>";
  }
}
