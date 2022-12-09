<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @history \file common-copyright-file.php
 * \brief This file contains common functions for getting copyright information
 */

namespace Fossology\Lib\Util;

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;

class CopyrightLister
{
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;
  /** @var TreeDao */
  private $treeDao;
  /** @var CopyrightDao */
  private $copyrightDao;

  private $includeContainer = FALSE;
  private $excludingCopyright = -1;
  private $includingCopyright = -1;
  /** @var string $type copyright type(all|statement|url|email) */
  private $type = "";
  private $agentId;

  function __construct()
  {
    global $container;
    $this->dbManager = $container->get('db.manager');
    $this->uploadDao = $container->get('dao.upload');
    $this->treeDao = $container->get('dao.tree');
    $this->copyrightDao = $container->get('dao.copyright');
  }

  public function setContainerInclusion($includeContainer)
  {
    $this->includeContainer = $includeContainer;
  }

  public function setExcludingCopyright($excludingCopyright)
  {
    $this->excludingCopyright = $excludingCopyright;
  }

  public function setIncludingCopyright($includingCopyright)
  {
    $this->includingCopyright = $includingCopyright;
  }

  public function setType($type)
  {
    $this->type = $type;
  }

  /**
   * @param $itemId - uploadtree id
   * @param $uploadId - upload id
   */
  public function getCopyrightList($itemId, $uploadId)
  {
    if (empty($itemId)) {
      $itemId = $this->uploadDao->getUploadParent($uploadId);
    }
    if (!$this->selectAgentId($uploadId)) {
      echo 'no valid copyright agent found';
      return;
    }
    $uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($uploadId);
    $toprow = $this->uploadDao->getItemTreeBounds($itemId,$uploadtree_tablename);

    $extraWhere = 'agent_fk='.$this->agentId.' AND lft>'.$toprow->getLeft().' AND rgt<'.$toprow->getRight();
    $allCopyrightEntries = $this->copyrightDao->getAllEntries('copyright', $uploadId, $uploadtree_tablename,
            empty($this->type)||$this->type=='all' ? null : $this->type, false, null, $extraWhere);

    $modeMask = empty($this->includeContainer) ? (3<<28) : (1<<28);
    $sql = "SELECT uploadtree_pk, ufile_name, lft, rgt FROM $uploadtree_tablename
              WHERE upload_fk=$1 AND lft>$2 AND rgt<$3 AND (ufile_mode & $4) = 0
              ORDER BY uploadtree_pk";
    $this->dbManager->prepare($outerStmt=__METHOD__.'.loopThroughAllRecordsInTree',$sql);
    $outerresult = $this->dbManager->execute($outerStmt,array($toprow->getUploadId(),$toprow->getLeft(),$toprow->getRight(),$modeMask));
    while ($row = $this->dbManager->fetchArray($outerresult)) {
      $this->printRow($row,$uploadtree_tablename, $allCopyrightEntries); //$this->uploadDao->getParentItemBounds($uploadId)->getItemId());
    }
    $this->dbManager->freeResult($outerresult);
  }

  /**
   * @param int $uploadId
   * @return bool success
   */
  private function selectAgentId($uploadId)
  {
    global $container;
    /* @var $agentDao AgentDao */
    $agentDao = $container->get('dao.agent');
    $agentRec = $agentDao->agentARSList($tableName="copyright_ars", $uploadId, 1);

    if ($agentRec === false) {
      echo _("No data available \n");
      return false;
    }
    $this->agentId = $agentRec[0]["agent_fk"];
    return true;
  }

  /**
   *  @brief write out text in format 'filepath: copyright list'
   */
  private function printRow($row,$uploadtree_tablename, &$allCopyrightEntries, $parentId=0)
  {
    $filepath = $this->treeDao->getFullPath($row['uploadtree_pk'], $uploadtree_tablename, $parentId);

    $copyrightArray = array();
    foreach ($allCopyrightEntries as $entry) {
      if ($entry['uploadtree_pk'] == $row['uploadtree_pk']) {
        $copyrightArray[] = $entry['content'];
      }
    }
    $copyright = implode(', ', $copyrightArray);

    /** include and exclude together */
    if (-1 != $this->includingCopyright && -1 != $this->excludingCopyright && !empty($this->includingCopyright) &&
        !empty($this->excludingCopyright)) {
      if (empty($copyright) || stristr($copyright, $this->includingCopyright) ||
          stristr($copyright, $this->excludingCopyright)) {
        return;
      }
    } else if (
            /** no value set for -x and -X, show all files */
          ! (-1 == $this->includingCopyright && -1 == $this->excludingCopyright) &&
            /** both value from -x and -X are empty, unmeaningful, show all files */
          ! (empty($this->includingCopyright) && empty($this->excludingCopyright)) &&
            /** just show files without copyright no matter if excluding_copyright */
          ! (empty($this->includingCopyright) && empty($copyright)) &&
            /** just show files with copyright */
          ! (empty($this->excludingCopyright) && !empty($copyright)) &&
            /** include  */
          ! (-1 != $this->includingCopyright && !empty($this->includingCopyright) && !empty($copyright) && stristr($copyright, $this->includingCopyright)) &&
            /** exclude */
          ! (-1 != $this->excludingCopyright && !empty($this->excludingCopyright) && !empty($copyright) && !stristr($copyright, $this->excludingCopyright))) {
      return;
    }
    print ("$filepath: $copyright\n");
  }
}
