<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Html;

interface HtmlElement
{
  /**
   * @param string $name
   * @param string $value
   */
  function setAttribute($name, $value);

  function getOpeningText();

  function getClosingText();
}