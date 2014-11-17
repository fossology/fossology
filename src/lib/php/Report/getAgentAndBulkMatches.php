<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini

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

namespace Fossology\Reportgen;

use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\License;

require_once("getClearedCommon.php");
class AgentAndBulkMatchesGetter extends ClearedGetterCommon
{
  /** @var HighlightDao */
  private $highlightDao;
  /** @var LicenseDao */
  private $licenseDao;

  //TODO remove
  /** @var DbManager */
  private $dbManager;

  public function __construct() {
    global $container;

    $this->highlightDao = $container->get('dao.highlight');
    $this->licenseDao = $container->get('dao.license');
    $this->dbManager = $container->get('db.manager');

    parent::__construct();
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $userId=null)
  {
    $result = array();

    $stmt = __METHOD__.".".$uploadTreeTableName;
    $this->dbManager->prepare($stmt, "SELECT uploadtree_pk FROM $uploadTreeTableName WHERE upload_fk = $1");
    $res = $this->dbManager->execute($stmt, array($uploadId));

    while ($row = $this->dbManager->fetchArray($res)) {
      $uploadTreeId = $row['uploadtree_pk'];
      $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
      foreach($this->highlightDao->getHighlightBulk($uploadTreeId) as $highlightEntry) {
        $type = $highlightEntry->getType();
        /** @var License $license */
        $license = $this->licenseDao->getLicenseById($highlightEntry->getLicenseId());
//        if ($type === Highlight::MATCH)
        //{
          //$content = $highlightEntry->getStart() ."-". $highlightEntry->getEnd();

          $content = $highlightEntry->getInfoText();
          $result[] = array(
            'content' => $content,
            'textfinding' => $license->getShortName(),
            'description' => $content,
            'uploadtree_pk' => $uploadTreeId
          );
        //}
      }
    }
    $this->dbManager->freeResult($res);

    return $result;
  }
}

