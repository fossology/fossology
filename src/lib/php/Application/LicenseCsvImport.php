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

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Data\LicenseUsageTypes;

class LicenseCsvImport {
  /** @var DbManager */
  protected $dbManager;
  /** @var null|array */
  protected $headrow = null;
  /** @var array */
  protected $nkMap = array();

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
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
    try
    {
      while(($row = fgetcsv($handle)) !== FALSE) {
        $this->handleCsv($row);
        $cnt++;
      }
    }
    catch(\Exception $e)
    {
      fclose($handle);
      return _('Error while parsing file: '.$e->getMessage());
    }
    fclose($handle);
    return _('Read csv').(": $cnt ")._('licenses');
  }

  private function handleCsv($row)
  {
    if($this->headrow===null)
    {
      $this->headrow = $this->handleHeadCsv($row);
      return;
    }

    $mRow = array();
    foreach( array('shortname','fullname','text') as $needle){
      $mRow[$needle] = $row[$this->headrow[$needle]];
    }
    foreach(array('parent_shortname'=>null,'url'=>'','notes'=>'') as $optNeedle=>$defaultValue)
    {
      $mRow[$optNeedle] = $defaultValue;
      if (array_key_exists($optNeedle,$this->headrow) && array_key_exists($this->headrow[$optNeedle], $row))
      {
        $mRow[$optNeedle] = $row[$this->headrow[$optNeedle]];
      }
    }
    
    $this->handleCsvLicense($mRow);
  }
  
  private function handleHeadCsv($row)
  {
    $headrow = array();
    foreach( array('shortname','fullname','text') as $needle){
      $col = array_search($needle, $row);
      if (false === $col)
      {
        throw new \Exception("Undetermined position of $needle");
      }
      $headrow[$needle] = $col;
    }
    foreach( array('parent_shortname','url','notes') as $optNeedle){
      $headrow[$optNeedle] = array_search($optNeedle, $row);
    }
    return $headrow;
  }

  private function handleCsvLicense($row)
  {
    /** @var DbManager $dbManager */
    $dbManager = $this->dbManager;
    if ($this->getKeyFromShortname($row['shortname'])!==false)
    {
      throw new \Exception("Shortname '$row[shortname]' already in DB");
    }
    $stmtInsert = __METHOD__.'.insert';
    $dbManager->prepare($stmtInsert,'INSERT INTO license_ref (rf_shortname,rf_fullname,rf_text,rf_detector_type,rf_url,rf_notes) VALUES ($1,$2,$3,$4,$5,$6) RETURNING rf_pk');
    $resi = $dbManager->execute($stmtInsert,array($row['shortname'],$row['fullname'],$row['text'],$userDetected=1,$row['url'],$row['notes']));
    $new = $dbManager->fetchArray($resi);
    $dbManager->freeResult($resi);
    if ($row['parent_shortname']===null || $this->getKeyFromShortname($row['parent_shortname'])===false)
    {
      return;
    }

    $dbManager->insertTableRow('license_map',
        array('rf_fk'=>$new['rf_pk'],
            'rf_parent'=>$this->getKeyFromShortname($row['parent_shortname']),
            'usage'=>LicenseUsageTypes::CONCLUSION));
  }
  
  private function getKeyFromShortname($shortname)
  {
    if(array_key_exists($shortname, $this->nkMap))
    {
      return $this->nkMap[$shortname];
    }
    $row = $this->dbManager->getSingleRow('SELECT rf_pk,rf_shortname FROM license_ref WHERE rf_shortname=$1',array($shortname));
    $this->nkMap[$shortname] = ($row===false) ? false : $row['rf_pk'];
    return $this->nkMap[$shortname];
  }

} 