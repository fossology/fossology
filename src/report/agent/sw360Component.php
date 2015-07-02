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

use sw360\thrift\components\ComponentServiceClient;

use PhpOffice\PhpWord\Element\Section;


include_once(__DIR__ . "/sw360IpPort.php");

class Sw360Component
{

  function sw360GetComponent($uploadId)
  {
    $sw360IpPort = new Sw360IpAndPort;
    $sw360Ip = $sw360IpPort->getSw360Ip();
    $sw360Port = $sw360IpPort->getSw360Port(); 

    try{
      $socket = new THttpClient($sw360Ip, $sw360Port,'/components/thrift');
      $transport = new TBufferedTransport($socket);
      $protocol = new TCompactProtocol($transport);
      $client = new ComponentServiceClient($protocol);

      $transport->open();
      $getComponent = $client->getComponentDetailedSummaryForExport();
      $transport->close();
      }
    catch (TException $tx) {
      print 'TException: '.$tx->getMessage()."\n";
      print $tx->getTraceAsString();  
    }
      
    return $getComponent;
  } 

  function processGetComponent($uploadId)
  { 
    $flag = false; 
    $getComponents = $this->sw360GetComponent($uploadId);
    if(!empty($getComponents)){
      foreach($getComponents as $getComponent){
        $releasesT = $getComponent->releases;
        foreach($releasesT as $releaseT){
          echo $releaseT->fossologyId;
          if(intval($releaseT->fossologyId) == $uploadId){
            $flag = True;
            break;
          }
        }
      }
      if($flag === false){
        return null;
      }
      $collectionArray = array(
        "Community" => $getComponent->homepage,
        "Component" => $releaseT->name,
        "Version" => $releaseT->version,
        "Source URL" => $releaseT->downloadurl,
        "Release date" => $releaseT->releaseDate
      );
      return $collectionArray;
    } 
    return null;
  }

}
