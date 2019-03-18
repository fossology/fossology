<?php
/*
 Copyright (C) 2014-2017, Siemens AG
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

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\ClearingDao;

class BulkMatchesGetter extends ClearedGetterCommon
{
  /** @var ClearingDao */
  private $clearingDao;

  public function __construct() {
    global $container;
    $this->clearingDao = $container->get('dao.clearing');
    parent::__construct($groupBy = "bulkId");
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId=0)
  {
    $result = array();

    $parentTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $bulkHistory = $this->clearingDao->getBulkHistory($parentTreeBounds, $groupId, false);

    foreach($bulkHistory as $bulk) {
      $allLicenses = "";
      $bulkId = $bulk['bulkId'];
      foreach($bulk['removedLicenses'] as $removedLics){
        $allLicenses .= ($removedLics ? "[remove] " : "") . $removedLics.', ';
      }
      foreach($bulk['addedLicenses'] as $addedLics){
        $allLicenses .= ($addedLics ? "[add] " : "") . $addedLics.', ';
      }
      $allLicenses = trim($allLicenses, ', ');
      $content = $bulk['text'];

      foreach ($this->clearingDao->getBulkMatches($bulkId,$groupId) as $bulkMatch)
      {
        $uploadTreeId = $bulkMatch['itemid'];

        $result[] = array(
          'bulkId' => $bulkId,
          'content' => $content,
          'textfinding' => $allLicenses,
          'description' => $content,
          'uploadtree_pk' => $uploadTreeId
        );
      }
    }

    return $result;
  }
}

