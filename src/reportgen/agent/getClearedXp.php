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

use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\CopyrightDao;

class XpClearedGetter
{
  /** @var CopyrightDao */
  private $copyrightDao;

  /** @var UploadDao */
  private $uploadDao;

  /** @var TreeDao */
  private $treeDao;

 // private $tableName = "copyright";
  //private $type = null;

  public function __construct() {
    global $container;

    $this->copyrightDao = $container->get('dao.copyright');
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

  protected function groupStatements($ungrupedStatements, $uploadTreeTableName)
  {
    $fileNames = array();
    foreach($ungrupedStatements as $key => $statement) {
      $id = $statement['id'];
      $content = $statement['content'];
      $uploadTreeId = $statement['uploadtree_pk'];
      $filePathRow = $this->treeDao->getFullPath($uploadTreeId, $uploadTreeTableName);
      $fileNames[$id.$content][] = $filePathRow['file_path'];
    }

    $statements = array();
    foreach($ungrupedStatements as $key => $statement) {
      $id = $statement['id'];
      $description = $statement['description'];
      //$textfinding = $statement['textfinding'];
      $content = $statement['content'];

      $statements[$id.$content] =
      array("content" => $content, //$textfinding,
      "text" => $description,
      "files" => array_values($fileNames[$id.$content]));
    }

    return $statements;
  }

  public function getCleared($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadTreeTableName($uploadId);

    $ungrupedStatements = $this->copyrightDao->getAllDecisions($this->tableName, $uploadId, $uploadTreeTableName, DecisionTypes::IDENTIFIED, $this->type);

    $statements = $this->groupStatements($ungrupedStatements,$uploadTreeTableName);
    return array("statements" => array_values($statements));
  }
}

