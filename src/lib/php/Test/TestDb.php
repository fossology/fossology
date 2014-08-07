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

namespace Fossology\Lib\Test;

class TestDb
{
  private $dbName;
  private $projectRoot;

  public function __construct() {
    date_default_timezone_set("UTC");

    $this->dbName = "fossology_" . date("Ymdd") . "_" . rand();
    $this->projectRoot = dirname(dirname(dirname(__FILE__)));
    $this->ensurePgPassFileEntry();
  }

  public function create() {

  }

  public function ensurePgPassFileEntry()
  {
    // create .pgpass file and place in the users home dir who is running this
    // program.  This file will be needed later in the code.
    $userHome = getenv('HOME');
    $ipv4 = gethostbyname(gethostname());
    $fullHostName = gethostbyaddr(gethostbyname($ipv4));
    $pgPassFilePath = "$userHome/.pgpass";

    if (!file_exists($pgPassFilePath)) {
      $windowsHome = getenv('APPDATA');
      $pgPassFilePath = "$windowsHome/postgresql/pgpass.conf";
    }
    
    // check for an existing ~/.pgpass.  If one already exists, and if the
    // file already contains a :fossy:fossy entry, then do not modify the
    // file at all
    $pgPassFileContent = ""; // start with an empty string
    if (file_exists($pgPassFilePath))
    {
      // read the file contents into the string
      $pgPassFileContent = file_get_contents($pgPassFilePath);
    }

    // If a fossy:fossy entry does not already exist then add it.
    // If the .pgpass file already exists do not overwrite, but append
    if (!preg_match('/\:fossy\:fossy/', $pgPassFileContent))
    {
      $pgPassFile = fopen($pgPassFilePath, 'a');
      $contents = "$fullHostName:*:*:fossy:fossy\n";
      $result = fwrite($pgPassFile, $contents);
      if ($result === FALSE)
      {
        echo "FATAL! Could not write to file $pgPassFilePath\n";
        exit(1);
      }
      fclose($pgPassFile);
    }

    // chmod so only owner can read/write it. If this is not set
    // postgres will ignore the .pgpass file.
    if (!chmod($pgPassFilePath, 0600))
    {
      echo "Warning! could not set $pgPassFilePath to 0600\n";
    }
  }
} 