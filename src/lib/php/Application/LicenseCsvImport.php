<?php
/*
Copyright (C) 2014, Siemens AG

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

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\ArrayOperation;

class LicenseCsvImport {
  /** @var DbManager */
  protected $dbManager;
  /** @var string */
  protected $delimiter = ',';
  /** @var string */
  protected $enclosure = '"';
  /** @var null|array */
  protected $headrow = null;
  /** @var array */
  protected $nkMap = array();
  protected $alias = array(
      'shortname'=>array('shortname','Short Name'),
      'fullname'=>array('fullname','Long Name'),
      'text'=>array('text','Full Text'),
      'parent_shortname'=>array('parent_shortname','Decider Short Name'),
      'report_shortname'=>array('report_shortname','Regular License Text Short Name'),
      'url'=>array('url','URL'),
      'notes'=>array('notes'),
      'source'=>array('source','Foreign ID')
      );

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
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
    if (($handle = fopen($filename, 'r')) === FALSE) {
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
      $msg .= _('Read csv').(": $cnt ")._('licenses');
    }
    catch(\Exception $e)
    {
      return $msg .= _('Error while parsing file: '.$e->getMessage());
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
    foreach( array('shortname','fullname','text') as $needle){
      $mRow[$needle] = $row[$this->headrow[$needle]];
    }
    foreach(array('parent_shortname'=>null,'report_shortname'=>null,'url'=>'','notes'=>'','source'=>'') as $optNeedle=>$defaultValue)
    {
      $mRow[$optNeedle] = $defaultValue;
      if ($this->headrow[$optNeedle]!==false && array_key_exists($this->headrow[$optNeedle], $row))
      {
        $mRow[$optNeedle] = $row[$this->headrow[$optNeedle]];
      }
    }
    
    return $this->handleCsvLicense($mRow);
  }
  
  private function handleHeadCsv($row)
  {
    $headrow = array();
    foreach( array('shortname','fullname','text') as $needle){
      $col = ArrayOperation::multiSearch($this->alias[$needle], $row);
      if (false === $col)
      {
        throw new \Exception("Undetermined position of $needle");
      }
      $headrow[$needle] = $col;
    }
    foreach( array('parent_shortname','report_shortname','url','notes','source') as $optNeedle){
      $headrow[$optNeedle] = ArrayOperation::multiSearch($this->alias[$optNeedle], $row);
    }
    return $headrow;
  }

  /**
   * @param array $row
   * @return string
   */
  private function handleCsvLicense($row)
  {
    /** @var DbManager $dbManager */
    $dbManager = $this->dbManager;
    if ($this->getKeyFromShortname($row['shortname'])!==false)
    {
      return "Shortname '$row[shortname]' already in DB (id=".$this->getKeyFromShortname($row['shortname']).")";
    }
    $sameText = $dbManager->getSingleRow('SELECT rf_shortname FROM license_ref WHERE rf_md5=md5($1)',array($row['text']));
    if ($sameText!==false)
    {
      return "Text of '$row[shortname]' already used for '$sameText[rf_shortname]'";
    }
    $stmtInsert = __METHOD__.'.insert';
    $dbManager->prepare($stmtInsert,'INSERT INTO license_ref (rf_shortname,rf_fullname,rf_text,rf_md5,rf_detector_type,rf_url,rf_notes,rf_source)'
            . ' VALUES ($1,$2,$3,md5($3),$4,$5,$6,$7) RETURNING rf_pk');
    $resi = $dbManager->execute($stmtInsert,
            array($row['shortname'],$row['fullname'],$row['text'],$userDetected=1,$row['url'],$row['notes'],$row['source']));
    $new = $dbManager->fetchArray($resi);
    $dbManager->freeResult($resi);
    $this->nkMap[$row['shortname']] = $new['rf_pk'];
    $return = "Inserted '$row[shortname]' in DB";
    
    if ($row['parent_shortname']!==null && $this->getKeyFromShortname($row['parent_shortname'])!==false)
    {
      $dbManager->insertTableRow('license_map',
        array('rf_fk'=>$new['rf_pk'],
            'rf_parent'=>$this->getKeyFromShortname($row['parent_shortname']),
            'usage'=>  LicenseMap::CONCLUSION));
      $return .= " with conclusion '$row[parent_shortname]'";
    }
    if ($row['report_shortname']!==null && $this->getKeyFromShortname($row['report_shortname'])!==false)
    {
      $dbManager->insertTableRow('license_map',
        array('rf_fk'=>$new['rf_pk'],
            'rf_parent'=>$this->getKeyFromShortname($row['report_shortname']),
            'usage'=>  LicenseMap::REPORT));
      $return .= " reporting '$row[report_shortname]'";
    }
    return $return;
  }
  
  private function getKeyFromShortname($shortname)
  {
    if(array_key_exists($shortname, $this->nkMap))
    {
      return $this->nkMap[$shortname];
    }
    $row = $this->dbManager->getSingleRow('SELECT rf_pk FROM license_ref WHERE rf_shortname=$1',array($shortname));
    $this->nkMap[$shortname] = ($row===false) ? false : $row['rf_pk'];
    return $this->nkMap[$shortname];
  }

} 