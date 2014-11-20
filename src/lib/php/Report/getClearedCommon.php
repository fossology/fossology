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

use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;

abstract class ClearedGetterCommon
{
  /** @var UploadDao */
  protected $uploadDao;

  /** @var TreeDao */
  protected $treeDao;

  /** @var array */
  private $fileNameCache = array();

  private $userId;
  private $uploadId;
  private $groupBy;

  public function __construct($groupBy = "content") {
    global $container;

    $this->uploadDao = $container->get('dao.upload');
    $this->treeDao = $container->get('dao.tree');

    $this->groupBy = $groupBy;
  }

  public function getCliArgs()
  {
    $args = getopt("u:", array("uId:"));

    if (!array_key_exists('u',$args))
    {
      print "missing required parameter -u {uploadId}\n";
      exit(2);
    }
    if (false && !array_key_exists('uId',$args))
    {
      print "missing optional parameter --uId {userId}\n";
    }

    $this->uploadId = intval($args['u']);
    $this->userId = intval(@$args['uId']);
  }

  public function getUploadId()
  {
    $uploadId = $this->uploadId;

    if ($uploadId<=0)
    {
      print "invalid uploadId ".$uploadId;
      exit(2);
    }
    return $uploadId;
  }

  public function getUserId()
  {
    $userId = $this->userId;

    if ($userId<=0)
    {
      print "invalid user ".$userId;
      exit(2);
    }
    return $userId;
  }

  protected function changeTreeIdsToPaths(&$ungrupedStatements, $uploadTreeTableName, $uploadId)
  {
    $parentId = $this->treeDao->getRealParent($uploadId, $uploadTreeTableName);

    foreach($ungrupedStatements as &$statement) {
      $uploadTreeId = $statement['uploadtree_pk'];
      unset($statement['uploadtree_pk']);

      if (!array_key_exists($uploadTreeId, $this->fileNameCache)) {
        $this->fileNameCache[$uploadTreeId] = $this->treeDao->getFullPath($uploadTreeId, $uploadTreeTableName, $parentId);
      }

      $fileName = $this->fileNameCache[$uploadTreeId];

      $statement['fileName'] = $fileName;
    }
    unset($statement);
  }

  protected function groupStatements($ungrupedStatements)
  {
    $statements = array();
    foreach($ungrupedStatements as $statement) {
      $content = convertToUTF8($statement['content'], false);
      $fileName = $statement['fileName'];

      if (!array_key_exists('text', $statement))
      {
        /* TODO make the subclasses do this logic and never fall in this branch */
        $description = $statement['description'];
        $textfinding = $statement['textfinding'];

        if ($description === null) {
          $text = "";
        } else {
          $content = $textfinding;
          $text = $description;
        }
        // TODO
      }
      else
      {
        $text = $statement['text'];
      }

      $groupBy = $statement[$this->groupBy];

      if (array_key_exists($groupBy, $statements))
      {
        $currentFiles = &$statements[$groupBy]['files'];
        if (!in_array($fileName, $currentFiles))
          $currentFiles[] = $fileName;
      }
      else
      {
        $statements[$groupBy] = array(
          "content" => convertToUTF8($content, false),
          "text" => convertToUTF8($text, false),
          "files" => array($fileName)
        );
      }
    }

    return $statements;
  }

  /**
   * @param int $uploadId
   * @param string $uploadTreeTableName
   * @param null|int $userId
   * @return array
   */
  abstract protected function getStatements($uploadId, $uploadTreeTableName, $userId=null);

  public function getCleared($uploadId, $userId=null)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadTreeTableName($uploadId);

    $ungrupedStatements = $this->getStatements($uploadId, $uploadTreeTableName, $userId);

    $this->changeTreeIdsToPaths($ungrupedStatements, $uploadTreeTableName, $uploadId);

    $statements = $this->groupStatements($ungrupedStatements);

    return array("statements" => array_values($statements));
  }
}

