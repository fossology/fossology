<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Html\LinkElement;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class CopyrightDao extends Object
{
  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var UploadDao
   */
  private $uploadDao;

  /**
   * @var Logger
   */
  private $logger;

  function __construct(DbManager $dbManager, UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->uploadDao = $uploadDao;
    $this->logger = new Logger(self::className());
  }

  /**
   * @param int $uploadTreeId
   * @return Highlight[]
   */
  public function getCopyrightHighlights($uploadTreeId)
  {

    $pFileId = 0;
    $row = $this->uploadDao->getUploadEntry($uploadTreeId);

    if (!empty($row['pfile_fk']))
    {
      $pFileId = $row['pfile_fk'];
    } else
    {
      $text = _("Could not locate the corresponding pfile.");
      print $text;
    }

    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
        "SELECT * FROM copyright WHERE copy_startbyte IS NOT NULL and pfile_fk=$1");
    $result = $this->dbManager->execute($statementName, array($pFileId));

    $typeToHighlightTypeMap = array(
        'statement' => Highlight::COPYRIGHT,
        'email' => Highlight::EMAIL,
        'url' => Highlight::URL);

    $highlights = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      if (!empty($row['copy_startbyte']))
      {
        $type = $row['type'];
        $content = $row['content'];
        $linkUrl = Traceback_uri() . "?mod=copyrightlist&agent=" . $row['agent_fk'] . "&item=$uploadTreeId&hash=" . $row['hash'] . "&type=" . $type;
        $highlightType = array_key_exists($type, $typeToHighlightTypeMap) ? $typeToHighlightTypeMap[$type] : Highlight::UNDEFINED;
        $highlights[] = new Highlight($row['copy_startbyte'], $row['copy_endbyte'], $highlightType, -1, -1, $content, new LinkElement($linkUrl));
      }
    }
    $this->dbManager->freeResult($result);

    return $highlights;
  }


  public function getCopyrights( $upload_pk, $Uploadtree_pk, $uploadTreeTableName , $Agent_pk, $hash = 0, $type, $filter)
  {
    list($left, $right) = $this->uploadDao->getLeftAndRight($Uploadtree_pk, $uploadTreeTableName);

    //! Set the default to none
    if($filter=="")  $filter = "none";

    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName) {
      $sql_upload = " AND UT.upload_fk=$upload_pk ";
    }

    $join = "";
    $filterQuery ="";
    if( $filter == "legal" ) {
      $Copyright = "Copyright";
      $filterQuery  = " AND CP.content ILIKE ('$Copyright%') ";
    }
    else if ($filter == "nolics"){

      $NoLicStr = "No_license_found";
      $VoidLicStr = "Void";
      $join  = " INNER JOIN license_file AS LF on  CP.pfile_fk =LF.pfile_fk ";
      $filterQuery =" AND LF.rf_fk IN (select rf_pk from license_ref where rf_shortname IN ('$NoLicStr', '$VoidLicStr')) ";
    }
    else if ($filter == "all") {  /* Not needed, but here to show that there is a filter all */
        $filterQuery ="";
    }
      $sql = "SELECT substring(CP.content FROM 1 for 150) AS content,  CP.ct_pk  as ct_pk  " .
             "FROM copyright AS CP " .
             "INNER JOIN $uploadTreeTableName AS UT ON CP.pfile_fk = UT.pfile_fk " .
              $join.
             "WHERE " .
                " ( UT.lft  BETWEEN  $1 AND  $2 ) " .
              "AND CP.type = $3 ".
                  $sql_upload.
                  $filterQuery.
                " AND CP.agent_fk= $4 ".
                " GROUP BY content, ct_pk ";


    $statement = __METHOD__ . $filter.$uploadTreeTableName;
    $params = array($left,$right,$type,$Agent_pk);

    $this->dbManager->prepare($statement,$sql);

    $result = $this->dbManager->execute($statement,$params);
    $rows = pg_fetch_all($result);
    pg_free_result($result);
    $rows = $this->GroupHolders($rows, $hash, $type);

    return $rows;
  }


  /**
   * \brief Combine copyright holders by name  \n
   * Input records contain: content and type \n
   * Output records: copyright_count, content, type, hash \n
   * where content has been simplified from
   * the raw records and hash is the md5 of this
   * new content.
   * \return If $hash non zero, only rows with that hash will
   * be returned.
   */
  function GroupHolders(&$rows, $hash, $type)
  {
    /* Step 1: Clean up content, and add hash
     */
    $NumRows = count($rows);
    for($RowIdx = 0; $RowIdx < $NumRows; $RowIdx++)
    {
      if ($this->massageContent($rows[$RowIdx], $hash, $type))
        unset($rows[$RowIdx]);
    }

    /* Step 2: sort the array by the new content */
    usort($rows, 'hist_rowcmp');

    /* Step 3: group content (remove dups, add counts) */
    $NumRows = count($rows);
    for($RowIdx = 1; $RowIdx < $NumRows; $RowIdx++)
    {
      if ($rows[$RowIdx]['content'] == $rows[$RowIdx-1]['content'])
      {
        $rows[$RowIdx]['copyright_count'] = $rows[$RowIdx-1]['copyright_count'] + 1;
        unset($rows[$RowIdx-1]);
      }
    }

    /** sorting */
    $ordercount = '-1';
    $ordercopyright = '-1';

    if (isset($_GET['orderc'])) $ordercount = $_GET['orderc'];
    if (isset($_GET['ordercp'])) $ordercopyright = $_GET['ordercp'];
    // sort by count
    if (1 == $ordercount) usort($rows, 'hist_rowcmp_count_desc');
    else if (0 == $ordercount) usort($rows, 'hist_rowcmp_count_asc');
    // sort by copyrigyht statement
    else if (1 == $ordercopyright) usort($rows, 'hist_rowcmp_desc');
    else if (0 == $ordercopyright) usort($rows, 'hist_rowcmp');
    else usort($rows, 'hist_rowcmp_count_desc'); // default as sorting by count desc

    /* note $rows indexes may not be contiguous due to unset in step 3 */
    return $rows;
  }



  /**
   * \brief Input row array contains: pfile, content and type  \n
   * Output records: massaged content, type, hash \n
   * where content has been simplified from
   * the raw records and hash is the md5 of this
   * new content. \n
   * If $hash non zero, only rows with that hash will
   * be returned.
   * \return On empty row, return true, else false
   */
  function massageContent(&$row, $hash, $type)
  {
    /* Step 1: Clean up content
     */
    $OriginalContent = $row['content'];

    /* remove control characters */
    $content = preg_replace('/[\x0-\x1f]/', ' ', $OriginalContent);

    if ($type == 'statement')
    {
      /* !"#$%&' */
      $content = preg_replace('/([\x21-\x27])|([*@])/', ' ', $content);

      /*  numbers-numbers, two or more digits, ', ' */
      $content = preg_replace('/(([0-9]+)-([0-9]+))|([0-9]{2,})|(,)/', ' ', $content);
      $content = preg_replace('/ : /', ' ', $content);  // free :, probably followed a date
    }
    else
      if ($type == 'email')
      {
        $content = str_replace(":;<=>()", " ", $content);
      }

    /* remove double spaces */
    $content = preg_replace('/\s\s+/', ' ', $content);

    /* remove leading/trailing whitespace and some punctuation */
    $content = trim($content, "\t \n\r<>./\"\'");

    /* remove leading "dnl " */
    if ((strlen($content) > 4) &&
        (substr_compare($content, "dnl ", 0, 4, true) == 0))
      $content = substr($content, 4);

    /* skip empty content */
    if (empty($content)) return true;

    /* Step 1B: rearrange copyright statments to try and put the holder first,
     * followed by the rest of the statement, less copyright years.
    */
    /* Not yet implemented
     if ($row['type'] == 'statement') $content = $this->StmtReorder($content);
    */

    //  $row['original'] = $OriginalContent;   // to compare original to new content
    $row['content'] = $content;
    $row['copyright_count'] = 1;
    $row['hash'] = md5($row['content']);
    if ($hash && ($row['hash'] != $hash)) return true;

    return false;
  }  /* End of massageContent() */

}