<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Tree;

class Item
{

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

  public function __construct(ItemTreeBounds $itemTreeBounds, $parentId, $fileId,
    $fileMode, $fileName)
  {
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
    return $this->itemTreeBounds->getItemId();
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
  public function getItemTreeBounds()
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

  /**
   * @return bool
   */
  public function hasParent()
  {
    return $this->parentId !== null;
  }

  function __toString()
  {
    return "Item(#" . $this->getId() . ", '" . $this->fileName . "')";
  }
}
