<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Upload;

class UploadTest extends \PHPUnit\Framework\TestCase
{
  /** @var int */
  private $id = 132;
  /** @var string */
  private $fileName = "<fileName>";
  /** @var string */
  private $description = "<description>";
  /** @var string */
  private $treeTableName = "<treeTableName>";
  /** @var int */
  private $timestamp;
  /** @var Upload */
  private $upload;

  protected function setUp() : void
  {
    $this->timestamp = time();
    $this->upload = new Upload($this->id, $this->fileName, $this->description, $this->treeTableName, $this->timestamp);

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
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
        'upload_ts' => date('Y-m-d H:i:s',$this->timestamp)
    );

    $upload = Upload::createFromTable($row);
    assertThat($upload->getId(), is($this->id));
    assertThat($upload->getFilename(), is($this->fileName));
    assertThat($upload->getDescription(), is($this->description));
    assertThat($upload->getTreeTableName(), is($this->treeTableName));
    assertThat($upload->getTimestamp(), is($this->timestamp));
  }
}
