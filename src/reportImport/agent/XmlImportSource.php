<?php
/*
 * Copyright (C) 2017, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace Fossology\ReportImport;


use Symfony\Component\DependencyInjection\SimpleXMLElement;
require_once 'ImportSource.php';

class XmlImportSource implements ImportSource
{
  /** @var  string */
  private $filename;
  /** @var SimpleXMLElement */
  private $xml;

  /** @var array ReportImportData[] with string as keys  */
  private $datas = array();

  /**
   * XmlImportSource constructor.
   * @param $filename
   */
  function __construct($filename)
  {
    $this->filename = $filename;
  }

  /**
   * @return bool
   */
  public function parse()
  {
    $this->xml = simplexml_load_file($this->filename, null, LIBXML_NOCDATA);

    /** @var \SimpleXMLElement[] */
    $licenses = $this->xml->xpath("License");
    /** @var \SimpleXMLElement[] */
    $copyrights = $this->xml->xpath("Copyright");

    $this->parseLicenseInformation($licenses);
    $this->parseCopyrightInformation($copyrights);

    return true;
  }

  /**
   * @param SimpleXMLElement $filesNode
   * @return array
   */
  private function splitFilesList($filesNode)
  {
    $files = array();

    $separator = "\r\n";
    $line = strtok((string) $filesNode, $separator);

    while ($line !== false) {
      if(! array_key_exists($line, $this->datas))
      {
        $this->datas[$line] = new ReportImportData();
      }
      $files[] = $line;
      $line = strtok($separator);
    }

    return $files;
  }

  /**
   * @param $licenses
   */
  private function parseLicenseInformation($licenses)
  {
    foreach ($licenses as $licenseNode)
    {
      $attributes = $licenseNode->attributes();
      $licenseName = (string) $attributes["name"];
      $licenseId = $attributes["spdxidentifier"] !== NULL ? (string) $attributes["spdxidentifier"] : $licenseName;
      $licenseText = (string) $licenseNode->xpath("Content")[0];
      $item = new ReportImportDataItem($licenseId);
      $item->setLicenseCandidate($licenseName, $licenseText, false);
      $item->setCustomText((string) $licenseNode);

      foreach ($licenseNode->xpath("Files") as $filesNode)
      {
        $files = $this->splitFilesList($filesNode);
        foreach ($files as $file)
        {
          $this->datas[$file]->addLicenseInfoInFile($item);
        }
      }
    }
  }

  /**
   * @param $copyrights
   */
  private function parseCopyrightInformation($copyrights)
  {
    foreach ($copyrights as $copyrightNode)
    {
      foreach ($copyrightNode->xpath("Files") as $filesNode)
      {
        $files = $this->splitFilesList($filesNode);
        foreach ($files as $file) {
          foreach ($copyrightNode->xpath("Content") as $content) {
            $this->datas[$file]->addCopyrightText((string)$content);
          }
        }
      }
    }
  }

  /**
   * @return array
   */
  public function getAllFiles()
  {
    $allFiles = array();
    foreach ($this->datas as $fileName => $data)
    {
      $allFiles[$fileName] = $fileName;
    }
    return $allFiles;
  }

  /**
   * @param $fileid
   * @return array
   */
  public function getHashesMap($fileid)
  {
    return array();
  }

  /**
   * @param $fileid
   * @return array
   */
  public function getDataForFile($fileid)
  {
    return $this->datas[$fileid];
  }
}
