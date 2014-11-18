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

namespace Fossology\Lib\Data\Upload;


use DateTime;

class Upload {
  /** @var int */
  private $id;

  /** @var string */
  private $filename;

  /** @var string */
  private $description;

  /** @var string */
  private $treeTableName;

  /** @var DateTime */
  private $timestamp;

  /**
   * @param int $id
   * @param string $filename
   * @param string $description
   * @param string $treeTableName
   */
  public function __construct($id, $filename, $description, $treeTableName, $timestamp){

    $this->id = $id;
    $this->filename = $filename;
    $this->description = $description;
    $this->treeTableName = $treeTableName;
    $this->timestamp = $timestamp;
  }

  /**
   * @return string
   */
  public function getDescription()
  {
    return $this->description;
  }

  /**
   * @return string
   */
  public function getFilename()
  {
    return $this->filename;
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
  public function getTreeTableName()
  {
    return $this->treeTableName;
  }

  /**
   * @return DateTime
   */
  public function getTimestamp()
  {
    return $this->timestamp;
  }
}