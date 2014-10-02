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

namespace Fossology\Lib\Data\Tree;


class Item {

  /** @var int */
  private $id;

  /** @var int */
  private $parentId;

  /** @var int */
  private $fileId;

  /** @var int */
  private $fileMode;

  /** @var string */
  private $fileName;

  /** @var ItemTreeBounds */
  private $itemTreeBounds;

  public function __construct($id, $parentId, $fileId, $fileMode, $fileName, ItemTreeBounds $itemTreeBounds) {
    $this->id = $id;
    $this->parentId = $parentId;
    $this->fileId = $fileId;
    $this->fileMode = $fileMode;
    $this->fileName = $fileName;
    $this->itemTreeBounds = $itemTreeBounds;
  }

  /**
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return int
   */
  public function getParentId()
  {
    return $this->parentId;
  }

  /**
   * @return int
   */
  public function getFileId()
  {
    return $this->fileId;
  }

  /**
   * @return int
   */
  public function getFileMode()
  {
    return $this->fileMode;
  }

  /**
   * @return string
   */
  public function getFileName()
  {
    return $this->fileName;
  }

  /**
   * @return ItemTreeBounds
   */
  public function getFileTreeBounds()
  {
    return $this->itemTreeBounds;
  }

}