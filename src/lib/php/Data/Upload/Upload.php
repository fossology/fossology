<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Upload;

class Upload
{
  /** @var int */
  protected $id;
  /** @var string */
  protected $filename;
  /** @var string */
  protected $description;
  /** @var string */
  protected $treeTableName;
  /** @var int */
  protected $timestamp;

  /**
   * @param $row
   * @return Upload
   */
  public static function createFromTable($row)
  {
    return new Upload(intval($row['upload_pk']), $row['upload_filename'],
      $row['upload_desc'], $row['uploadtree_tablename'],
      strtotime($row['upload_ts']));
  }

  /**
   * @param int $id
   * @param string $filename
   * @param string $description
   * @param string $treeTableName
   * @param int $timestamp
   */
  public function __construct($id, $filename, $description, $treeTableName, $timestamp)
  {
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
   * @return int
   */
  public function getTimestamp()
  {
    return $this->timestamp;
  }
}
