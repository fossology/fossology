<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
   * @return null
   */
  public function getVersion(){
    return null;
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
   * @param $fileId
   * @return array
   */
  public function getHashesMap($fileId)
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
