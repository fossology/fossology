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

namespace Fossology\Lib\Data\Upload;


use DateTime;

class UploadTest extends \PHPUnit_Framework_TestCase
{
  private $id = 132;

  private $fileName = "<fileName>";

  private $description = "<description>";

  private $treeTableName = "<treeTableName>";

  /** @var DateTime */
  private $timestamp;

  /** @var Upload */
  private $upload;

  public function setUp()
  {
    $this->timestamp = new DateTime();

    $this->upload = new Upload($this->id, $this->fileName, $this->description, $this->treeTableName, $this->timestamp);
  }

  public function testGetId()
  {
    assertThat($this->upload->getId(), is($this->id));
  }

  public function testGetFilename()
  {
    assertThat($this->upload->getFilename(), is($this->fileName));
  }

  public function testGetDescription()
  {
    assertThat($this->upload->getDescription(), is($this->description));
  }

  public function testGetTreeTableName()
  {
    assertThat($this->upload->getTreeTableName(), is($this->treeTableName));
  }

  public function testGetTimeStamp()
  {
    assertThat($this->upload->getTimestamp(), is($this->timestamp));
  }

  public function testCreateFromTableRow()
  {
    $row = array(
        'upload_pk' => $this->id,
        'upload_filename' => $this->fileName,
        'upload_desc' => $this->description,
        'uploadtree_tablename' => $this->treeTableName,
        'upload_ts' => $this->timestamp->format('Y-m-d H:i:s')
    );

    $upload = Upload::createFromTable($row);
    assertThat($upload->getId(), is($this->id));
    assertThat($upload->getFilename(), is($this->fileName));
    assertThat($upload->getDescription(), is($this->description));
    assertThat($upload->getTreeTableName(), is($this->treeTableName));
    assertThat($upload->getTimestamp(), is($this->timestamp));
  }
}
 