<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\UI;

use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Db\DbManager;

class FolderNav
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
            ." SELECT folder_pk, folder_name, folder_desc, depth, name_path FROM folder_tree ORDER BY name_path";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($parentFolder));
    $out = '';
    $lastDepth = -1;
    while ($row = $this->dbManager->fetchArray($res)) {
      for (; $row['depth']<$lastDepth; $lastDepth--) {
        $out .= '</li></ul>';
      }
      if ($row['depth']==$lastDepth) {
        $out .= "</li>\n<li>";
      }
      if ($row['depth']==0) {
        $out .= '<ul id="tree"><li>';
        $lastDepth++;
      }
      for (;$row['depth']>$lastDepth;$lastDepth++) {
        $out .= '<ul><li>';
      }
      $out .= $this->getFormattedItem($row, $uri);
    }
    for (; - 1<$lastDepth;$lastDepth--) {
      $out .= '</li></ul>';
    }
    return $out;
  }

  protected function getFormattedItem($row,$uri)
  {
    $title = empty($row['folder_desc']) ? '' : ' title="' . htmlspecialchars($row['folder_desc']) . '"';
    return '<a'.$title.
           ' href="'.$uri.'?mod=browse&folder='.$row['folder_pk'].'"'.
           ' class="clickable-folder text-info stretched-link" style="padding:2px;" data-folder="'.$row['folder_pk'].'"'.
           '>'.htmlentities($row['folder_name']).'</a>';
  }
}
