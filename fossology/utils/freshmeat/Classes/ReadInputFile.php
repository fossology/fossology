<?php

/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/**
 * class to read simple input file.  Takes care of # comment lines and
 * blank lines.
 *
 * @version "$Id: $"
 *
 * Created on Jun 6, 2008
 */

class ReadInputFile
{
  public $input_file;
  public $file_resource;

/**
 * function __construct
 * Constructor for ReadInputFile
 * @param string $file, the path to the input file to read
 *        The file should exist and be readable.
 *
 * @return the opened file resource or NULL on failure
 *
 */
  public function __construct($file)
  {
    /* use a try catch here
     *
     * NOTE, test to see if it exsits, if not create it?
     */
     $this->input_file = $file;
     if(empty($file))
     {
      print "DB: RIF: Failing due to empty file?\n";
      return;
     }
    //print "DB: RIF: input_file is:$this->input_file\n";
    $FD = fopen($this->input_file, 'r') or die("Can't open $this->input_file, $php_errormsg\n");
    $this->file_resource = $FD;
    return;
  }

  /**
   * function getline
   *
   * Get a line of data from the open file resource.  Will skip any
   * comment lines or blank lines.
   *
   * getline always reads 1024 bytes of data from the file.  It expects
   * each line to have a new-line which will terminate the read.  If the
   * file is not formatted this way, it will just return 1k of data per
   * read.
   *
   * @param $FD opened file resource
   *
   * @return the data line or NULL on EOF/Failure
   */
  public function getline($FD)
  {
    while($rline = fgets($FD, 1024))
    {
      $line = trim($rline);
            // check for blank lines, (null after trim), skip them
      if (!$line === "")
      {
        continue;
      }
      if (!(preg_match('/^#/', $line)))
      {
        continue;
      }
    }
    return($line);
  }
}
?>
