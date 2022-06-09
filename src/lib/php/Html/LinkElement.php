<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Html;


class LinkElement extends SimpleHtmlElement
{
  public function __construct($url)
  {
    parent::__construct("a", array("href" => $url));
  }
}