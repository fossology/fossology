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
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\License;

define('CONTEXT',25);

require_once("getClearedCommon.php");
class KeywordsGetter extends ClearedGetterCommon
{
  /** @var HighlightDao */
  private $highlightDao;
  /** @var DbManager */
  private $dbManager;

  public function __construct() {
    global $container;

    $this->highlightDao = $container->get('dao.highlight');

    $this->dbManager = $container->get('db.manager');

    parent::__construct($groupBy = "text");
  }

  private function readFile($inputFile, $start, $end)
  {
    $length = $end - $start;
    $startContext = max(0,$start - CONTEXT);

    fseek($inputFile, $startContext, SEEK_SET);
    $context = fread($inputFile, $length + 2*CONTEXT);

    fseek($inputFile, $start, SEEK_SET);
    $keyword = fread($inputFile, $length);

    return array($keyword, $context);
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $userId=null)
  {
    $result = array();

    $parentTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);


    $stmt = "listItems.$uploadTreeTableName";

    $this->dbManager->prepare(
      $stmt,
      "SELECT uploadtree_pk as itemid FROM $uploadTreeTableName ut WHERE ut.upload_fk = $1 AND pfile_fk != 0"
    );

    $res = $this->dbManager->execute($stmt, array($uploadId));

    while ($row = $this->dbManager->fetchArray($res)) {
      $itemId = $row['itemid'];
      $itemTreeBounds = $this->uploadDao->getItemTreeBounds($itemId, $uploadTreeTableName);

      $inputFile = fopen(RepPathItem($itemId), "rb");

      if (!$inputFile)
        throw new \Exception("could not open item $itemId for reading");

      $highlightsKeyword = $this->highlightDao->getHighlightKeywords($itemTreeBounds);
      foreach($highlightsKeyword as $highlight)
      {
        $start = $highlight->getStart();
        $end = $highlight->getEnd();

        list($keyword, $context) = $this->readFile($inputFile, $start, $end);

        $result[]= array(
          'content' => convertToUTF8($keyword),
          'text' => convertToUTF8($context),
          'uploadtree_pk' => $itemId
        );
      }

      fclose($inputFile);
    }

    $this->dbManager->freeResult($res);

    return $result;
  }
}

