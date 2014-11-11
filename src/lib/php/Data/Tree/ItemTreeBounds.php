<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

use Fossology\Lib\Util\Object;

class ItemTreeBounds extends Object
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
  private $uploadTreeId;

  /**
   * @param int $uploadTreeId
   * @param string $uploadTreeTableName
   * @param int $uploadId
   * @param int $left
   * @param int $right
   */
  public function __construct($uploadTreeId, $uploadTreeTableName, $uploadId, $left, $right)
  {
    $this->uploadTreeTableName = $uploadTreeTableName;
    $this->uploadId = $uploadId;
    $this->left = $left;
    $this->right = $right;
    $this->uploadTreeId = $uploadTreeId;
  }

  /**
   * @return int
   */
  public function getItemId()
  {
    return $this->uploadTreeId;
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

} 