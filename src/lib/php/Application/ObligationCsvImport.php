<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\ArrayOperation;

class ObligationCsvImport {
  /** @var DbManager */
  protected $dbManager;
  /** @var string */
  protected $delimiter = ',';
  /** @var string */
  protected $enclosure = '"';
  /** @var null|array */
  protected $headrow = null;
  /** @var array */
  protected $alias = array(
      'topic'=>array('topic','Obligation or Risk topic'),
      'text'=>array('text','Full Text'),
      'licnames'=>array('licnames','Associated Licenses')
    );

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->obligationMap = $GLOBALS['container']->get('businessrules.obligationmap');
  }

  public function setDelimiter($delimiter=',')
  {
    $this->delimiter = substr($delimiter,0,1);
  }

  public function setEnclosure($enclosure='"')
  {
    $this->enclosure = substr($enclosure,0,1);
  }

  /**
   * @param string $filename
   * @return string message
   */
  public function handleFile($filename)
  {
    if (!is_file($filename) || ($handle = fopen($filename, 'r')) === FALSE) {
      return _('Internal error');
    }
    $cnt = -1;
    $msg = '';
    try
    {
      while(($row = fgetcsv($handle,0,$this->delimiter,$this->enclosure)) !== FALSE) {
        $log = $this->handleCsv($row);
        if (!empty($log))
        {
          $msg .= "$log\n";
        }
        $cnt++;
      }
      $msg .= _('Read csv').(": $cnt ")._('obligations');
    }
    catch(\Exception $e)
    {
      fclose($handle);
      return $msg .= _('Error while parsing file').': '.$e->getMessage();
    }
    fclose($handle);
    return $msg;
  }

  /**
   * @param array $row
   * @return string $log
   */
  private function handleCsv($row)
  {
    if($this->headrow===null)
    {
      $this->headrow = $this->handleHeadCsv($row);
      return 'head okay';
    }

    $mRow = array();
    foreach( array('topic','text','licnames') as $needle){
      $mRow[$needle] = $row[$this->headrow[$needle]];
    }

    return $this->handleCsvObligation($mRow);
  }

  private function handleHeadCsv($row)
  {
    $headrow = array();
    foreach( array('topic','text','licnames') as $needle){
      $col = ArrayOperation::multiSearch($this->alias[$needle], $row);
      if (false === $col)
      {
        throw new \Exception("Undetermined position of $needle");
      }
      $headrow[$needle] = $col;
    }
    return $headrow;
  }

  private function getKeyFromTopicAndText($row)
  {
    $req = array($row['topic'], $row['text']);
    $row = $this->dbManager->getSingleRow('SELECT ob_pk FROM obligation_ref WHERE ob_topic=$1 AND ob_md5=md5($2)',$req);
    return ($row === false) ? false : $row['ob_pk'];
  }


  /**
   * @param array $row
   * @return string
   */
  private function handleCsvObligation($row)
  {
    /* @var $dbManager DbManager */
    $dbManager = $this->dbManager;
    $exists = $this->getKeyFromTopicAndText($row);
    if ($exists !== false)
    {
      return "Obligation topic '$row[topic]' with text '$row[text]' already exists in DB (id=".$exists.")";
    }

    $stmtInsert = __METHOD__.'.insert';
    $dbManager->prepare($stmtInsert,'INSERT INTO obligation_ref (ob_topic,ob_text,ob_md5)'
            . ' VALUES ($1,$2,md5($2)) RETURNING ob_pk');
    $resi = $dbManager->execute($stmtInsert,
            array($row['topic'],$row['text']));
    $new = $dbManager->fetchArray($resi);
    $dbManager->freeResult($resi);

    $associatedLicenses = "";
    $licenses = explode(";",$row['licnames']);
    foreach ($licenses as $license)
    {
      $licId = $this->obligationMap->getIdFromShortname($license);
      if ($licId == '0')
      {
        $message = _("ERROR: License with shortname '$license' not found in the DB. Obligation not updated.");
        return "<b>$message</b><p>";
      }

      if ($this->obligationMap->isLicenseAssociated($new['ob_pk'],$licId))
      {
        continue;
      }

      $this->obligationMap->associateLicenseWithObligation($new['ob_pk'],$licId);
      if ($associatedLicenses == "")
      {
        $associatedLicenses = "$license";
      }
      else
      {
        $associatedLicenses .= ";$license";
      }
    }

    $return = "Obligation topic '$row[topic]' was added and associated with licenses '$associatedLicenses' in DB";

    return $return;
  }

}
