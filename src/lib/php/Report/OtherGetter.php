<?php
/*
 Copyright (C) 2017, Siemens AG
 Author: Anuapm Ghosh

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

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\UploadDao; 

class OtherGetter
{
  /** @var UploadDao */
  private $uploadDao;
  
  public function __construct(){
    global $container;                                                             
    $this->uploadDao = $container->get('dao.upload');
  }

  public function getReportData($uploadId)
  {
    return $this->uploadDao->getReportInfo($uploadId);
  }
} 
