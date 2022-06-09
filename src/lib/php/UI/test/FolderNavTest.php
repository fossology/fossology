<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Steffen Weber
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\UI;

use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

function Traceback_uri()
{
  return 'uri';
}

class FolderNavTest extends \PHPUnit\Framework\TestCase
{
  /** @var M */
  private $dbManager;
  /** @var M */
  private $folderDao;
  /** @var FolderNav */
  private $folderNav;
  /** @var string */
  private $uri;

  protected function setUp() : void
  {
    $this->folderDao = M::mock(FolderDao::class);
    $this->dbManager = M::mock(DbManager::class);
    $this->folderNav = new FolderNav($this->dbManager,$this->folderDao);
    $this->uri = Traceback_uri();
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }

  protected function getFormattedItem($row)
  {
    return Reflectory::invokeObjectsMethodnameWith($this->folderNav, 'getFormattedItem', array($row,$this->uri));
  }

  protected function prepareShowFolderTree($parentFolderId='parentFolderId')
  {
    $this->folderDao->shouldReceive('getFolderTreeCte')->with($parentFolderId)
            ->andReturn($parentFolderId.'Cte');
    $this->dbManager->shouldReceive('prepare')->withArgs(array(anything(),startsWith($parentFolderId.'Cte')));
    $this->dbManager->shouldReceive('execute')->withArgs(array(anything(),array($parentFolderId)))
            ->andReturn($res=$parentFolderId.'Res');
    $this->dbManager->shouldReceive('freeResult')->with($res);
    return $res;
  }

  public function testShowFolderTreeWithoutContent()
  {
    $res = $this->prepareShowFolderTree($parentFolderId='foo');
    $this->dbManager->shouldReceive('fetchArray')->with($res)
            ->andReturn($rowA=array('folder_pk'=>1, 'folder_name'=>'A', 'folder_desc'=>'', 'depth'=>0),false);
    $out = $this->folderNav->showFolderTree($parentFolderId);
    assertThat($out, equalTo('<ul id="tree"><li>'.$this->getFormattedItem($rowA).'</li></ul>'));
  }

  public function testShowFolderTreeWithContent()
  {
    $res = $this->prepareShowFolderTree($parentFolderId='foo');
    $this->dbManager->shouldReceive('fetchArray')->with($res)
            ->andReturn($rowTop=array('folder_pk'=>1, 'folder_name'=>'Top', 'folder_desc'=>'', 'depth'=>0),
                    $rowA=array('folder_pk'=>2, 'folder_name'=>'B', 'folder_desc'=>'/A', 'depth'=>1),
                    $rowB=array('folder_pk'=>3, 'folder_name'=>'B', 'folder_desc'=>'/A/B', 'depth'=>2),
                    $rowC=array('folder_pk'=>4, 'folder_name'=>'C', 'folder_desc'=>'/C', 'depth'=>1),
                    false);
    $out = $this->folderNav->showFolderTree($parentFolderId);
    assertThat(str_replace("\n",'',$out), equalTo('<ul id="tree"><li>'.$this->getFormattedItem($rowTop).'<ul><li>'
            .$this->getFormattedItem($rowA).'<ul><li>'
            .$this->getFormattedItem($rowB).'</li></ul></li><li>'
            .$this->getFormattedItem($rowC).'</li></ul></li></ul>'));
  }
}
