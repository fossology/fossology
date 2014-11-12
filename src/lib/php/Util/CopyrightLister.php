<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014 Siemens AG

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
***********************************************************/

/**
 * @history \file common-copyright-file.php
 * \brief This file contains common functions for getting copyright information
 */

namespace Fossology\Lib\Util;

// use Fossology\Lib\Util\Object;
use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Db\DbManager;

class CopyrightLister extends Object
{
  /** @var DbManager */
  private $dbManager;
  private $includeContainer = FALSE;
  private $excludingCopyright = -1;
  private $includingCopyright = -1;
  /** @var string $type copyright type(all/statement/url/email) */
  private $type = "";
  private $agentId;
  
  function __construct(){
    global $container;
    /** @var DbManager $dbManager */
    $this->dbManager = $container->get('db.manager');
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
   * @param int $uploadId
   * @return bool success
   */
  private function selectAgentId($uploadId)
  {
    global $container;
    /** @var AgentsDao $agentDao */
    $agentDao = $container->get('dao.agents');
    $agentRec = $agentDao->agentARSList($tableName="copyright_ars", $uploadId, $limit=1);

    if ($agentRec === false)
    {
      echo _("No data available \n");
      return false;
    }
    $this->agentId = $agentRec[0]["agent_fk"];
    return TRUE;
  }
  
  private function getUploadtreeIdFromUploadId($upload_pk){
    $uploadtreeRec = $this->dbManager->getSingleRow('SELECT uploadtree_pk FROM uploadtree WHERE parent IS NULL AND upload_fk=$1',
            array($upload_pk),
            $sqlLog=__METHOD__);
    $uploadtree_pk = $uploadtreeRec['uploadtree_pk'];
    return $uploadtree_pk;
  }

  
  /**
   * @param $uploadtree_pk - uploadtree id
   * @param $upload_pk - upload id
   */
  public function getCopyrightList($uploadtree_pk, $upload_pk)
  {
    if (empty($uploadtree_pk)) {
      $uploadtree_pk = $this->getUploadtreeIdFromUploadId($upload_pk);
    }    
    if ($this->selectAgentId($upload_pk) === FALSE)
    {
      return;
    }

    /* get the top of tree */
    $sql = "SELECT upload_fk, lft, rgt from uploadtree where uploadtree_pk=$1";
    $toprow = $this->dbManager->getSingleRow($sql, array($uploadtree_pk), __METHOD__.'.getBounds');

    $uploadtree_tablename = GetUploadtreeTableName($toprow['upload_fk']);

    /* loop through all the records in this tree */
    global $PG_CONN;
    $modeMask = empty($this->includeContainer) ? (3<<28) : (1<<28);
    $sql = "select uploadtree_pk, ufile_name, lft, rgt from $uploadtree_tablename 
                where upload_fk='$toprow[upload_fk]' 
                      and lft>'$toprow[lft]' and rgt<'$toprow[rgt]'
                      and (ufile_mode & $modeMask) = 0";
    $sql .= " order by uploadtree_pk";
    $outerresult = pg_query($PG_CONN, $sql);
    DBCheckResult($outerresult, $sql, __FILE__, __LINE__);

    while ($row = pg_fetch_assoc($outerresult))
    { 
      $this->printRow($row,$uploadtree_tablename);
    } 
    pg_free_result($outerresult);
  }
  
  /**
   *  @brief write out text in format 'filepath: copyright list'
   */  
  private function printRow($row,$uploadtree_tablename)
  {
    $filepatharray = Dir2Path($row['uploadtree_pk'], $uploadtree_tablename);
    $filepath = "";
    foreach($filepatharray as $uploadtreeRow)
    {
      if (!empty($filepath)) $filepath .= "/";
      $filepath .= $uploadtreeRow['ufile_name'];
    }

    $copyright = $this->getFileCopyright_string($row['uploadtree_pk']) ;
    /** include and exclude together */
    if (-1 != $this->includingCopyright && -1 != $this->excludingCopyright && !empty($this->includingCopyright) && 
        !empty($this->excludingCopyright))
    {
      if (empty($copyright) || stristr($copyright, $this->includingCopyright) ||
          stristr($copyright, $this->excludingCopyright)){
        return;
      }
    }
    else if (
            /** no value set for -x and -X, show all files */
            (-1 == $this->includingCopyright && -1 == $this->excludingCopyright) ||
            /** both value from -x and -X are empty, unmeaningful, show all files */
            (empty($this->includingCopyright) && empty($this->excludingCopyright)) ||
            /** just show files without copyright no matter if excluding_copyright */
            (empty($this->includingCopyright) && empty($copyright)) ||
            /** just show files with copyright */
            (empty($this->excludingCopyright) && !empty($copyright)) ||
            /** include  */
            (-1 != $this->includingCopyright && !empty($this->includingCopyright) && !empty($copyright) && stristr($copyright, $this->includingCopyright)) ||
            /** exclude */
            (-1 != $this->excludingCopyright && !empty($this->excludingCopyright) && !empty($copyright) && !stristr($copyright, $this->excludingCopyright)))
      ;
    else
    {
      return;
    }
    print ("$filepath: $copyright\n");
}
  

  /**
   * \brief get all the copyright for a single file or uploadtree
   * 
   * \param $agent_pk - agent id
   * \param $pfile_pk - pfile id, (if empty, $uploadtree_pk must be given)
   * \param $uploadtree_pk - (used only if $pfile_pk is empty)
   * \return Array of file copyright CopyrightArray[ct_pk] = copyright.content
   */
  private function getFileCopyrights($agent_pk, $pfile_pk, $uploadtree_pk)
  {
    $type = $this->type;
    global $PG_CONN;

    // if $pfile_pk, then return the copyright for that one file
    if ($pfile_pk)
    {
      $sql = "SELECT ct_pk, content 
                from copyright
                where pfile_fk='$pfile_pk' and agent_fk=$agent_pk";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
    }
    else if ($uploadtree_pk)
    {
      $sql = "SELECT lft, rgt, upload_fk FROM uploadtree WHERE uploadtree_pk = $1";
      $row = $this->dbManager->getSingleRow($sql, array($uploadtree_pk), __METHOD__.'.findLftAndRgtBounds');
      $lft = $row["lft"];
      $rgt = $row["rgt"];
      $upload_pk = $row["upload_fk"];

      $typesql = ($type && "all" != $type) ? "and type = '$type'" : '';
      //  Get the copyright under this $uploadtree_pk
      $sql = "SELECT ct_pk, content from copyright,
                  (SELECT distinct(pfile_fk) as PF from uploadtree
                     where upload_fk=$upload_pk and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$agent_pk $typesql";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
    }
    else
    {
      throw new Exception("Missing function inputs in " . __FILE__ . ':' . __LINE__);
    }

    $CopyrightArray = array();
    while ($row = pg_fetch_assoc($result))
    {
      $CopyrightArray[$row['ct_pk']] = $row["content"];
    }
    pg_free_result($result);
    return $CopyrightArray;
  }

  /**
   * @param int $uploadtree_pk - (used only if $pfile_pk is empty)
   * @param int $pfile_pk - pfile id, (if empty, $uploadtree_pk must be given)
   * @return string copyright list as a single string
   */
  private function getFileCopyright_string($uploadtree_pk, $pfile_pk=0)
  {
    $copyrightStr = '';
    $copyrightArray = $this->getFileCopyrights($this->agentId, $pfile_pk, $uploadtree_pk);
    $glue = '';
    foreach($copyrightArray as $ct)
    {
      $copyrightStr .= $glue.$ct;
      $glue = ', ';
    }
    return $copyrightStr;
  }
}
