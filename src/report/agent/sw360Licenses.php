<?php
/*
 Author: Daniele Fognini, Shaheem Azmal, anupam.ghosh@siemens.com
 Copyright (C) 2015, Siemens AG

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
use Thrift\ClassLoader\ThriftClassLoader;


use Thrift\Protocol\TBinaryProtocol;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TSocket;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TBufferedTransport;
use Thrift\Exception\TException;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Agent;
use sw360\thrift\licenses\LicenseServiceClient;

use PhpOffice\PhpWord\Element\Section;


include_once(__DIR__ . "/sw360IpPort.php");

class Sw360License
{
  
  function sw360GetLicense($uploadId,$groupName,$identifiers)
  {
    $sw360IpPort = new Sw360IpAndPort;
    $sw360Ip = $sw360IpPort->getSw360Ip();
    $sw360Port = $sw360IpPort->getSw360Port(); 
    $identifier = $this->contentOnly($identifiers);

    try{
      $socket = new THttpClient($sw360Ip, $sw360Port,'/licenses/thrift');

      $transport = new TBufferedTransport($socket);
      $protocol = new TCompactProtocol($transport);
      $client = new LicenseServiceClient($protocol);

      $transport->open();
      $getLicenseTodos = $client->getDetailedLicenseSummary($groupName,$identifier);
      $transport->close();

      $results = $this->processGetLicense($getLicenseTodos);
   }

    catch (TException $tx) {
      print 'TException: '.$tx->getMessage()."\n";
      print $tx->getTraceAsString();
    }
    return $results;
  }

  function contentOnly($identifiers)
  {
    foreach($identifiers as $identifier){
       $identifierReq[] = $identifier["content"];
    }
    return $identifierReq;
  }

  function processGetLicense($getLicenseTodos)
  {
    if(!empty($getLicenseTodos)){
      foreach($getLicenseTodos as $getLicenseTodo){
        if(!empty($getLicenseTodo->todos)){
          $todos = $getLicenseTodo->todos;
          foreach($todos as $todo){
            $obligationTypes = $todo->obligations;
            foreach($obligationTypes as $obligationType){
              $todoLicenseArray[] = array(
                "License Name" => $getLicenseTodo->shortname,
                "License Main Type" => $getLicenseTodo->licenseType->type,
                "Type" => $todo->type,
                "Text" => $todo->text, 
                "ObligationsType" => $obligationType->type,
                "ObligationName" => $obligationType->name,
                "DevelopmentString" => $todo->developmentString,
                "DistributionString" => $todo->distributionString
              );
            }
          }
        }
      } 
    }
    
    if(!empty($todoLicenseArray)){
      foreach($todoLicenseArray as &$licenseArray){
        if($licenseArray["DevelopmentString"] == "True"){
          $licenseArray["DevelopmentString"] = "X";
        }
        else{
          $licenseArray["DevelopmentString"] = " ";
        }
        
        if($licenseArray["DistributionString"] == "True"){
          $licenseArray["DistributionString"] = "X";
        }
        else{
          $licenseArray["DistributionString"] = " ";
        }
      }
    
      $results = array();
      foreach($todoLicenseArray as $data){
        $id = $data["ObligationName"];
        if(isset($results[$id])) {
          $results[$id][] = $data;
        }
        else{
          $results[$id] = array($data);
        }
      }   
    }
    return $results;
  }
}
