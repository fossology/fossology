<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Tree;

class ItemTreeBounds
{
  /**
   * @var string
   */
  private $uploadTreeTableName;
  /**
   * @var int
   */
  private $uploadId;
  /**
   * @var int
   */
  private $left;
  /**
   * @var int
   */
  private $right;

  /**
   * @var int
   */
  private $itemId;

  /**
   * @param int $itemId
   * @param string $uploadTreeTableName
   * @param int $uploadId
   * @param int $left
   * @param int $right
   */
  public function __construct($itemId, $uploadTreeTableName, $uploadId, $left, $right)
  {
    $this->uploadTreeTableName = $uploadTreeTableName;
    $this->uploadId = (int) $uploadId;
    $this->left = (int) $left;
    $this->right = (int) $right;
    $this->itemId = (int) $itemId;
  }

  /**
   * @return int
   */
  public function getItemId()
  {
    return $this->itemId;
  }

  /**
   * @return string
   */
  public function getUploadTreeTableName()
  {
    return $this->uploadTreeTableName;
  }

  /**
   * @return int
   */
  public function getUploadId()
  {
    return $this->uploadId;
  }

  /**
   * @return int
   */
  public function getLeft()
  {
    return $this->left;
  }

  /**
   * @return int
   */
  public function getRight()
  {
    return $this->right;
  }

  public function containsFiles()
  {
    return $this->right - $this->left > 1;
  }

  function __toString()
  {
    return "ItemTreeBounds([" . $this->left . ", " . $this->right . "] " .
    "upload " . $this->uploadId . "@" . $this->uploadTreeTableName . ")";
  }
}
