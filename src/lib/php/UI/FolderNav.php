<?php
/***********************************************************
 * Copyright (C) 2015 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\UI;

use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;

class FolderNav extends Object
{
  /** @var DbManager */
  private $dbManager;
  /** @var FolderDao */
  private $folderDao;

  public function __construct(DbManager $dbManager, FolderDao $folderDao)
  {
    $this->dbManager = $dbManager;
    $this->folderDao = $folderDao;
  }
  
  /**
   * @param int $parentFolder  parent folder_pk
   * @return string HTML of the folder tree
   */
  public function showFolderTree($parentFolder)
  {
    $uri = Traceback_uri();
    $sql = $this->folderDao->getFolderTreeCte($parentFolder)
            ." SELECT folder_pk, folder_name, folder_desc, depth FROM folder_tree";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($parentFolder));
    $out = '';
    $lastDepth = -1;
    while($row=$this->dbManager->fetchArray($res)){
      if($row['depth']==$lastDepth)
      {
        $out .= "</li>\n<li>";
      }
      if($row['depth']==0)
      {
        $out .= '<ul id="tree"><li>';
        $lastDepth++;
      }
      for(;$row['depth']>$lastDepth;$lastDepth++)
      {
        $out .= '<ul><li>';
      }
      for(;$row['depth']<$lastDepth;$lastDepth--)
      {
        $out .= '</li></ul>';
      }
      $title = empty($row['folder_desc']) ? '' : 'title="' . htmlspecialchars($row['folder_desc']) . '"';
      $out .= '<a '.$title.' href="'.$uri.'?mod=browse&folder='.$row['folder_pk'].'">'.htmlentities($row['folder_name']).'</a>';
    }
    for(;0<$lastDepth;$lastDepth--)
    {
      $out .= '</li></ul>';
    }
    return $out;
  } 

}
