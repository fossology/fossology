<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Mockery as M;
use Mockery\MockInterface;

include_once(dirname(dirname(__DIR__))."/common-string.php");

class TestClearedGetter extends ClearedGetterCommon
{
  public function __construct($groupBy = "content")
  {
    parent::__construct($groupBy);
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $userId = null, $groupId=null)
  {
    return array(
      array("licenseId" => "371", "risk" => "5", "content" => "1", "text" => "t1", "comments" => "c1", "uploadtree_pk" => 1),
      array("licenseId" => "213", "risk" => "4", "content" => "1", "text" => "t2", "comments" => "c1", "uploadtree_pk" => 2),
      array("licenseId" => "243", "risk" => "4", "content" => "2", "text" => "t3", "comments" => "c3", "uploadtree_pk" => 3),
    );
  }
}

class WeirdCharClearedGetter extends ClearedGetterCommon
{
  public function __construct($groupBy = "content")
  {
    parent::__construct($groupBy);
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $userId = null, $groupId=null)
  {
  }

  public function getCleared($uploadId, $objectAgent, $groupId = null, $extended = true, $agentcall = null, $isUnifiedReport = false)
  {
    return array(
      array("good" => "漢", "esc" => "escape", "uml" => ' ü ')
    );
  }
}

class ClearedGetterCommonTest extends \PHPUnit\Framework\TestCase
{
  /** @var UploadDao|MockInterface */
  private $uploadDao;
  /** @var TreeDao|MockInterface */
  private $treeDao;

  protected function setUp() : void
  {
    $this->uploadDao = M::mock(UploadDao::class);
    $this->treeDao = M::mock(TreeDao::class);

    $container = M::mock('ContainerBuilder');
    $GLOBALS['container'] = $container;

    $container->shouldReceive('get')->with('dao.upload')->andReturn($this->uploadDao);
    $container->shouldReceive('get')->with('dao.tree')->andReturn($this->treeDao);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testGetFileNames()
  {
    $this->clearedGetterTest = new TestClearedGetter();

    $uploadId = 1;
    $parentId = 112;
    $uploadTreeTableName = "ut";

    $this->uploadDao
         ->shouldReceive('getUploadtreeTableName')
         ->with($uploadId)
         ->andReturn($uploadTreeTableName);

    $this->treeDao
         ->shouldReceive('getMinimalCoveringItem')
         ->with($uploadId,$uploadTreeTableName)
         ->andReturn($parentId);

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(1, $uploadTreeTableName, $parentId)
         ->andReturn("a/1");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(1, $uploadTreeTableName)
         ->andReturn(array('sha1'=> "9B12538E" ,'md5'=> "MD52538E"));

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(2, $uploadTreeTableName, $parentId)
         ->andReturn("a/2");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(2, $uploadTreeTableName)
         ->andReturn(array('sha1'=> "8C2275AE" ,'md5'=> "MD5275AE"));

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(3, $uploadTreeTableName, $parentId)
         ->andReturn("a/b/1");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(3, $uploadTreeTableName)
         ->andReturn(array('sha1'=> "CA10238C" ,'md5'=> "MD50238C"));

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(4, $uploadTreeTableName, $parentId)
         ->andReturn("a/4");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(4, $uploadTreeTableName)
         ->andReturn(array('sha1'=> "AB12838A" ,'md5'=> "MD52838A"));

    $statements = $this->clearedGetterTest->getCleared($uploadId, null);
    $expected = array(
      "statements" => array(
        array(
          "licenseId" => "371",
          "risk" => "5",
          "content" => "1",
          "text" => "d1",
          "comments" => "c1",
          "files" => array("a/1", "a/2"),
          "hash" => array("9B12538E","8C2275AE")
        ),
        array(
          "licenseId" => "213",
          "risk" => "5",
          "content" => "tf1",
          "text" => "d1",
          "comments" => "c1",
          "files" => array("a/1"),
          "hash" => array("9B12538E")
        ),
        array(
          "licenseId" => "243",
          "risk" => "3",
          "content" => "tf1",
          "text" => "d2",
          "comments" => "c4",
          "files" => array("a/4"),
          "hash" => array("AB12838A")
        ),
        array(
          "licenseId" => "8",
          "risk" => "4",
          "content" => "2",
          "text" => "t3",
          "comments" => "c3",
          "files" => array("a/b/1"),
          "hash" => array("CA10238C")
        )
      )
    );
    assertThat(arsort($expected), equalTo($statements));
  }

  public function testGetFileNamesGroupByText()
  {
    $this->clearedGetterTest = new TestClearedGetter();
    $uploadId = 1;
    $parentId = 112;
    $uploadTreeTableName = "ut";

    $this->uploadDao
         ->shouldReceive('getUploadtreeTableName')
         ->with($uploadId)
         ->andReturn($uploadTreeTableName);

    $this->treeDao
         ->shouldReceive('getMinimalCoveringItem')
         ->with($uploadId,$uploadTreeTableName)
         ->andReturn($parentId);

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(1, $uploadTreeTableName, $parentId)
         ->andReturn("a/1");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(1, $uploadTreeTableName)
         ->andReturn(array('sha1'=> "9B12538E" ,'md5'=> "MD52538E"));

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(2, $uploadTreeTableName, $parentId)
         ->andReturn("a/2");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(2, $uploadTreeTableName)
         ->andReturn(array('sha1'=> "8C2275AE" ,'md5'=> "MD5275AE"));

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(3, $uploadTreeTableName, $parentId)
         ->andReturn("a/b/1");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(3, $uploadTreeTableName)
         ->andReturn(array('sha1'=> "CA10238C" ,'md5'=> "MD50238C"));

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(4, $uploadTreeTableName, $parentId)
         ->andReturn("a/4");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(4, $uploadTreeTableName)
         ->andReturn(array('sha1'=> "AB12838A" ,'md5'=> "MD52838A"));

    $tester = new TestClearedGetter("text");
    $statements = $tester->getCleared($uploadId, null);
    $expected = array(
      "statements" => array(
        array(
          "licenseId" => "371",
          "risk" => "5",
          "content" => "tf1",
          "text" => "d1",
          "comments" => "c1",
          "files" => array("a/1"),
          "hash" => array("9B12538E")
        ),
        array(
          "licenseId" => "243",
          "risk" => "4",
          "content" => "1",
          "text" => "t2",
          "comments" => "c1",
          "files" => array("a/2"),
          "hash" => array("8C2275AE")
        ),
        array(
          "licenseId" => "243",
          "risk" => "4",
          "content" => "2",
          "text" => "t3",
          "comments" => "c3",
          "files" => array("a/b/1"),
          "hash" => array("CA10238C")
        ),
        array(
          "licenseId" => "8",
          "risk" => "3",
          "content" => "tf1",
          "text" => "d1",
          "comments" => "c1",
          "files" => array("a/4"),
          "hash" => array("AB12838A")
        )
      )
    );
    assertThat(arsort($expected), equalTo($statements));
  }

  /**
   * Verify that a single uploadtree item whose path cannot be resolved (e.g. the
   * item is not a descendant of the minimal covering item) does NOT abort the whole
   * report.  The item should be included with an empty fileName/fileHash and all
   * other items should be processed normally.
   */
  public function testChangeTreeIdsToPathsHandlesGetFullPathException()
  {
    $uploadId = 1;
    $parentId = 112;
    $uploadTreeTableName = "ut";

    $this->uploadDao
         ->shouldReceive('getUploadtreeTableName')
         ->with($uploadId)
         ->andReturn($uploadTreeTableName);

    $this->treeDao
         ->shouldReceive('getMinimalCoveringItem')
         ->with($uploadId, $uploadTreeTableName)
         ->andReturn($parentId);

    // Item 1: getFullPath throws (simulates the "could not find path" scenario)
    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(1, $uploadTreeTableName, $parentId)
         ->andThrow(new \Exception("could not find path of 1"));

    // Item 2: resolves normally
    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(2, $uploadTreeTableName, $parentId)
         ->andReturn("a/2");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(2, $uploadTreeTableName)
         ->andReturn(array('sha1' => "8C2275AE", 'md5' => "MD5275AE"));

    // Item 3: resolves normally
    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(3, $uploadTreeTableName, $parentId)
         ->andReturn("a/b/1");

    $this->treeDao
         ->shouldReceive('getItemHashes')
         ->with(3, $uploadTreeTableName)
         ->andReturn(array('sha1' => "CA10238C", 'md5' => "MD50238C"));

    $clearedGetter = new TestClearedGetter();
    // Must not throw — exception from getFullPath must be caught internally.
    $result = $clearedGetter->getCleared($uploadId, null);

    assertThat($result, hasKey('statements'));
    $statements = $result['statements'];

    // Item 1 (unresolvable) should still appear, with empty fileName and fileHash.
    $fileNames = array_merge(...array_column($statements, 'files'));
    assertThat($fileNames, hasItem(''));

    // Items 2 and 3 should be present with their correct paths.
    assertThat($fileNames, hasItem('a/2'));
    assertThat($fileNames, hasItem('a/b/1'));
  }

  function testWeirdChars()
  {
    $weirdCharclearedGetter = new WeirdCharclearedGetter();
    $json = $weirdCharclearedGetter->cJson(0);
    assertThat($json, containsString('"good":"\\u6f22"'));
    assertThat($json, containsString('"esc":"escape"'));
    assertThat($json, containsString('"uml":" \\u00fc "'));
  }
}
