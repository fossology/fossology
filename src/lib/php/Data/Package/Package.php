<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Package;

use Fossology\Lib\Data\Upload\Upload;

class Package
{

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
  public function __construct($id, $name, $uploads)
  {
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
