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
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\License;

require_once("getClearedCommon.php");
class BulkMatchesGetter extends ClearedGetterCommon
{
  /** @var HighlightDao */
  private $highlightDao;
  /** @var ClearingDao */
  private $clearingDao;

  public function __construct() {
    global $container;

    $this->highlightDao = $container->get('dao.highlight');
    $this->clearingDao = $container->get('dao.clearing');

    parent::__construct($groupBy = "bulkId");
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $userId=null)
  {
    $result = array();

    $parentTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $bulkHistory = $this->clearingDao->getBulkHistory($parentTreeBounds, false);

    foreach($bulkHistory as $bulk) {
      $bulkId = $bulk['bulkId'];
      $licenseShortName = $bulk['lic'];
      $removing = $bulk['removing'];

      $content = $bulk['text'];

      foreach ($this->clearingDao->getBulkMatches($bulkId, $userId) as $bulkMatch)
      {
        $uploadTreeId = $bulkMatch['itemid'];

        $result[] = array(
          'bulkId' => $bulkId,
          'content' => $content,
          'textfinding' => ($removing ? "[remove] " : "") . $licenseShortName,
          'description' => $content,
          'uploadtree_pk' => $uploadTreeId
        );
      }
    }

    return $result;
  }
}

