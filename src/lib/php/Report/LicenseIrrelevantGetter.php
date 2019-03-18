<?php
/*
 Copyright (C) 2017, Siemens AG

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

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\ClearingDao;

class LicenseIrrelevantGetter extends ClearedGetterCommon
{
  /** @var ClearingDao */
  private $clearingDao;

  /** @var irreleavntFilesOnly */
  private $irreleavntFilesOnly;

  public function __construct($irreleavntFilesOnly=true) {
    $this->clearingDao = $GLOBALS['container']->get('dao.clearing');
    $this->irreleavntFilesOnly = $irreleavntFilesOnly;
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
    return $this->clearingDao->getIrrelevantFilesFolder($itemTreeBounds, $groupId);
  }
  
  /**
   * @overwrite
   * @param type $ungrupedStatements
   * @return type
   */
  protected function groupStatements($ungrupedStatements, $extended, $agentcall)
  {
    $statements = array();
    foreach($ungrupedStatements as $statement){
      $fileName = $statement['fileName'];
      $dirName = dirname($statement['fileName']);
      $baseName = basename($statement['fileName']);
      $comment = $statement['comment'];
      $licenseName = $statement['shortname'];
      if($this->irreleavntFilesOnly){
        if (array_key_exists($fileName, $statements))
        {
          $currentLics = &$statements[$fileName]["licenses"];
          if (!in_array($licenseName, $currentLics)){
            $currentLics[] = $licenseName;
          }
        }
        else{
          $statements[$fileName] = array(
            "content" => convertToUTF8($dirName, false),
            "fileName" => $baseName,
            "licenses" => array($licenseName)
            );
        }
      }
      else{
        if($comment){
          $statements[] = array(
            "content" => $licenseName,
            "text" => $comment,
            "files" => array($fileName)
          );
        }
      }
    }
    return $statements;
  }
}
