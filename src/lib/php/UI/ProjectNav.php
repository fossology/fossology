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
use Fossology\Lib\Dao\ProjectDao;
use Fossology\Lib\Db\DbManager;

class ProjectNav
{
  /** @var DbManager */
  private $dbManager;

  /** @var ProjectDao */
  private $projectDao;

  public function __construct(DbManager $dbManager, ProjectDao $projectDao)
  {
    $this->dbManager = $dbManager;
    $this->projectDao = $projectDao;
  }

  /**
   * @param int $parentProject  parent project_pk
   * @return string HTML of the project tree
   */
  public function showProjectTree($parentProject)
  {

    $uri = Traceback_uri();
    $sql = $this->projectDao->getProjectTreeCte($parentProject)
            ." SELECT project_pk, project_name, project_desc, depth, name_path FROM project_tree ORDER BY name_path";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($parentProject));
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
    $title = empty($row['project_desc']) ? '' : ' title="' . htmlspecialchars($row['project_desc']) . '"';
    return '<a'.$title.
           ' href="'.$uri.'?mod=browse&project='.$row['project_pk'].'"'.
           ' class="clickable-project text-info stretched-link" style="padding:2px;" data-project="'.$row['project_pk'].'"'.
           '>'.htmlentities($row['project_name']).'</a>';
  }
}
