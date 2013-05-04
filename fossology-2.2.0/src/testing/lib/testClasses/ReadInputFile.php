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
 * @version "$Id: ReadInputFile.php 2187 2009-05-29 04:59:35Z rrando $"
 *
 * Created on Jun 6, 2008
 */

class ReadInputFile {

  public $inputFile;
  protected $error;
  protected $fileResource;

  /**
   * function __construct
   * Constructor for ReadInputFile
   * @param string $file, the path to the input file to read
   *        The file should exist and be readable.
   *
   * @return the opened file resource or NULL on failure
   *
   */
  public function __construct($file) {

    $this->error = NULL;
    $this->inputFile = $file;
    if(!file_exists($file)) {
      return(FALSE);
    }
    //print "DB: RIF: inputFile is:$this->inputFile\n";
    try {
      if(FALSE === ($FD = @fopen($this->inputFile, 'r'))) {
        throw new Exception("Cannot open File $this->inputFile\n");
      }
    }
    catch(Exception $e) {
      $this->error = $e->getMessage();
      return(FALSE);
    }
    $this->fileResource = $FD;
    return(TRUE);
  }

  public function getError(){
    return($this->error);
  }

  public function getFileResource(){
    return($this->fileResource);
  }
  /**
   * function getLine
   *
   * Get a line of data from the open file resource.  Will skip any
   * comment lines or blank lines.  A comment line is a line starting with #
   *
   * getline always reads 1024 bytes of data from the file.  It expects
   * each line to have a new-line which will terminate the read.  If the
   * file is not formatted this way, it will just return 1k of data per
   * read.
   *
   * @param $FD opened file resource e.g.
   * $f = new ReadInputFile('foo');
   * $FD = f->getFileResource();
   * $f->getLine(FD);
   *
   * @return the data line or NULL on EOF/Failure
   */
  public function getLine($FD) {
    while($rline = fgets($FD, 1024)) {
      $line = trim($rline);
      // check for blank lines, (null after trim), or comment, skip them
      if ($line === "") continue;
      if (preg_match('/^#/', $line)) continue;
      return($line);
    }
    return(FALSE);
  }
};
?>
