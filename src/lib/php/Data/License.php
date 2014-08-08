<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Johannes Najjar, Andreas Würl

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

class License extends LicenseRef
{
  /**
   * @var string
   */
  private $text;
  /**
   * @var string
   */
  private $url;

  function __construct($id, $shortName, $fullName, $text, $url)
  {
    parent::__construct($id, $shortName, $fullName);
    $this->text = $text;
    $this->url = $url;
  }

  /**
   * @return string
   */
  public function getText()
  {
    return $this->text;
  }

  /**
   * @return string
   */
  public function getUrl()
  {
    return $this->url;
  }
}