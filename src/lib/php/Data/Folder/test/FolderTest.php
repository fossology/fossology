<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Folder;

class FolderTest extends \PHPUnit\Framework\TestCase
{

  /** @var int */
  private $folderId = 32;

  /** @var string */
  private $folderName = "<folder>";

  /** @var string */
  private $folderDescription = "<description>";

  /** @var int */
  private $folderPermissions = 3453;

  /** @var Folder */
  private $folder;

  protected function setUp() : void
  {
    $this->folder = new Folder($this->folderId, $this->folderName,
      $this->folderDescription, $this->folderPermissions);
  }

  public function testGetId()
  {
    assertThat($this->folder->getId(), is($this->folderId));
  }

  public function testGetName()
  {
    assertThat($this->folder->getName(), is($this->folderName));
  }

  public function testGetDescription()
  {
    assertThat($this->folder->getDescription(), is($this->folderDescription));
  }

  public function testGetPermissions()
  {
    assertThat($this->folder->getPermissions(), is($this->folderPermissions));
  }
}
