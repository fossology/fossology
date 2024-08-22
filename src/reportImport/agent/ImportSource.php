<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\ReportImport;


interface ImportSource
{
  /**
   * @return array
   */
  public function getAllFiles();

  /**
   * @return bool
   */
  public function parse();

  /**
   * @return $specVersion
   */
  public function getVersion();
  
  /**
   * @param $fileId
   * @return array
   */
  public function getHashesMap($fileId);

  /**
   * @param $fileid
   * @return array|ReportImportData
   */
  public function getDataForFile($fileid);
}
