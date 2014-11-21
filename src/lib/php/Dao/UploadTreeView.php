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

namespace Fossology\Lib\Dao;


use Fossology\Lib\Data\Tree\ItemTreeBounds;

class UploadTreeView extends DbViewDao
{
  const CONDITION_UPLOAD = 1;
  const CONDITION_RANGE = 2;
  const CONDITION_PLAIN_FILES = 3;

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param array $constraints
   * @param string $viewSuffix
   */
  public function __construct(ItemTreeBounds $itemTreeBounds, $constraints = array(), $viewSuffix=null)
  {
    $dbViewQuery = self::getUploadTreeView($itemTreeBounds, $constraints);
    parent::__construct($dbViewQuery, 'UploadTreeView' . ($viewSuffix ? '.' . $viewSuffix : ''));
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int[] $constraints
   * @return string
   */
  private static function getUploadTreeView(ItemTreeBounds $itemTreeBounds, $constraints)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $isDefaultTable = $uploadTreeTableName == 'uploadtree_a' || $uploadTreeTableName == 'uploadtree';
    if ($isDefaultTable)
    {
      $constraints[] = self::CONDITION_UPLOAD;
    }
    $baseQuery = "SELECT * FROM $uploadTreeTableName";
    $condition = self::getConstraintsCondition($itemTreeBounds, $constraints);
    return $baseQuery . $condition;
  }

  private static function getConstraintsCondition(ItemTreeBounds $itemTreeBounds, $constraints)
  {
    $conditions = array();
    foreach(array_unique($constraints) as $constraint) {
      $conditions[] = self::getConstraintCondition($itemTreeBounds, $constraint);
    }
    $condition = implode(' AND ', $conditions);
    return $condition ? ' WHERE ' . $condition : '';
  }

  private static function getConstraintCondition(ItemTreeBounds $itemTreeBounds, $constraint)
  {
    switch ($constraint)
    {
      case self::CONDITION_UPLOAD:
        $uploadId = $itemTreeBounds->getUploadId();
        return "upload_fk = $uploadId";
      case self::CONDITION_RANGE:
        $left = $itemTreeBounds->getLeft();
        $right = $itemTreeBounds->getRight();
        return "lft BETWEEN $left AND $right";
      case self::CONDITION_PLAIN_FILES:
        return '((ufile_mode & (3<<28))=0) AND pfile_fk != 0';
      default:
        throw new \InvalidArgumentException("constraint $constraint is not defined");
    }
  }
} 