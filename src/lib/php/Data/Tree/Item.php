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
  private $parentId;

  /** @var int */
  private $fileId;

  /** @var int */
  private $fileMode;

  /** @var string */
  private $fileName;

  /** @var ItemTreeBounds */
  private $itemTreeBounds;

  public function __construct(ItemTreeBounds $itemTreeBounds, $parentId, $fileId, $fileMode, $fileName) {
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
    return $this->itemTreeBounds->getUploadTreeId();
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

  /**
   * @return bool
   */
  public function isFile()
  {
    return !Isartifact($this->fileMode) && !Isdir($this->fileMode) && !Iscontainer($this->fileMode);
  }

  /**
   * @return bool
   */
  public function isContainer()
  {
    return Iscontainer($this->fileMode);
  }

  /**
   * @return bool
   */
  public function containsFileTreeItems()
  {
    return $this->itemTreeBounds->containsFiles();
  }

}