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

namespace Fossology\Lib\Data\Folder;


class FolderTest extends \PHPUnit_Framework_TestCase {

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

  public function setUp() {
    $this->folder = new Folder($this->folderId, $this->folderName, $this->folderDescription, $this->folderPermissions);
  }

  public function testGetId() {
    assertThat($this->folder->getId(), is($this->folderId));
  }

  public function testGetName() {
    assertThat($this->folder->getName(), is($this->folderName));
  }

  public function testGetDescription() {
    assertThat($this->folder->getDescription(), is($this->folderDescription));
  }

  public function testGetPermissions() {
    assertThat($this->folder->getPermissions(), is($this->folderPermissions));
  }
}
 