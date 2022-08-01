<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\ClearingDao;

class LicenseNonFunctionalGetter extends ClearedGetterCommon
{
  /** @var ClearingDao */
  private $clearingDao;

  /** @var nonFunctionalFilesOnly */
  private $nonFunctionalFilesOnly;

  public function __construct($nonFunctionalFilesOnly=true)
  {
    $this->clearingDao = $GLOBALS['container']->get('dao.clearing');
    $this->nonFunctionalFilesOnly = $nonFunctionalFilesOnly;
    parent::__construct($groupBy = 'text');
  }

  /**
   * @param int $uploadId
   * @param string $uploadTreeTableName
   * @param int|null $groupId
   * @return array of array('uploadtree_pk'=>int)
   */
  protected function getStatements($uploadId, $uploadTreeTableName, $groupId=null)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    return $this->clearingDao->getFilesForDecisionTypeFolderLevel($itemTreeBounds, $groupId, true, 'nonFunctional');
  }

  /**
   * @overwrite
   * @param type $ungrupedStatements
   * @return type
   */
  protected function groupStatements($ungrupedStatements, $extended, $agentcall, $isUnifiedReport, $objectAgent)
  {
    $statements = array();
    foreach ($ungrupedStatements as $statement) {
      $fileName = $statement['fileName'];
      $dirName = dirname($statement['fileName']);
      $baseName = basename($statement['fileName']);
      $comment = $statement['comment'];
      $licenseName = $statement['shortname'];
      if ($this->nonFunctionalFilesOnly) {
        if (array_key_exists($fileName, $statements)) {
          $currentLics = &$statements[$fileName]["licenses"];
          if (! in_array($licenseName, $currentLics)) {
            $currentLics[] = $licenseName;
          }
        } else {
          $statements[$fileName] = array(
            "content" => convertToUTF8($dirName, false),
            "fileName" => $baseName,
            "licenses" => array($licenseName)
            );
        }
      } else {
        if ($comment) {
          $statements[] = array(
            "content" => $licenseName,
            "text" => $comment,
            "files" => array($fileName)
          );
        }
      }
      $objectAgent->heartbeat(1);
    }
    return array("statements" => array_values($statements));
  }
}
