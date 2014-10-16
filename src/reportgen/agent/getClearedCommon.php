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

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\TreeDao;

abstract class ClearedGetterCommon
{
  /** @var UploadDao */
  private $uploadDao;

  /** @var TreeDao */
  private $treeDao;

 // private $tableName = "copyright";
  //private $type = null;

  public function __construct() {
    global $container;

    $this->uploadDao = $container->get('dao.upload');
    $this->treeDao = $container->get('dao.tree');
  }

  public function getUploadIdArg()
  {
    $args = getopt("u:", array());

    if (!array_key_exists('u',$args))
    {
      print "missing required parameter -u {uploadId}";
      exit(2);
    }

    $uploadId = intval($args['u']);

    if ($uploadId<=0)
    {
      print "invalid uploadId ".$uploadId;
      exit(2);
    }
    return $uploadId;
  }

  protected function changeTreeIdsToPaths(&$ungrupedStatements, $uploadTreeTableName)
  {
    foreach($ungrupedStatements as $key => &$statement) {
      $uploadTreeId = $statement['uploadtree_pk'];
      unset($statement['uploadtree_pk']);
      $filePathRow = $this->treeDao->getFullPath($uploadTreeId, $uploadTreeTableName);
      $fileName = $filePathRow['file_path'];

      $statement['fileName'] = $fileName;
    }
    unset($statement);
  }

  protected function groupStatements($ungrupedStatements)
  {
    $statements = array();
    foreach($ungrupedStatements as $key => $statement) {
      $id = $statement['id'];
      $description = $statement['description'];
      $content = $statement['content'];
      $fileName = $statement['fileName'];

      if (array_key_exists($content, $statements))
      {
        $statements[$content]['files'][] = $fileName;
      }
      else
      {
        $statements[$content] = array(
          "content" => $content, //$textfinding,
          "text" => $description,
          "files" => array($fileName)
        );
      }
    }

    return $statements;
  }

  abstract protected function getDecisions($uploadId, $uploadTreeTableName);

  public function getCleared($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadTreeTableName($uploadId);

    $ungrupedStatements = $this->getDecisions($uploadId, $uploadTreeTableName);

    $this->changeTreeIdsToPaths($ungrupedStatements, $uploadTreeTableName);

    $statements = $this->groupStatements($ungrupedStatements);

    return array_values($statements);
  }
}

