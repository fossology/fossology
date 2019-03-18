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

namespace Fossology\Lib\Data\Package;

use Fossology\Lib\Data\Upload\Upload;

class Package {

  /** @var int */
  private $id;

  /** @var string*/
  private $name;

  /** @var Upload[] */
  private $uploads;

  /**
   * @param int $id
   * @param string $name
   * @param Upload[] $uploads
   */
  public function __construct($id, $name, $uploads) {
    $this->id = $id;
    $this->uploads = $uploads;
    $this->name = $name;
  }

  /**
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @return Upload[]
   */
  public function getUploads()
  {
    return $this->uploads;
  }

} 
