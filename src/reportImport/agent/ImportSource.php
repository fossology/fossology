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
   * @param $fileid
   * @return array
   */
  public function getHashesMap($fileid);

  /**
   * @param $fileid
   * @return array
   */
  public function getDataForFile($fileid);
}
