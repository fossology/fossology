#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief Create an 'empty' test file for use in testing
 *
 * @version "$Id $"
 *
 * @todo need to add:
 * - parameters, usage
 * - log of what is getting created with what license
 *
 * Created on Mar 15, 2011 by Mark Donohoe
 */

global $commentChar;
global $endChar;

 /*
   * General flow:
   * - use random suffix if none passed in.
   * - create the default name, if none passed in
   *   - Determine comment style
   * - pick a license randomly
   * - construct all needed file parts (choosing comment style before)
   * - write the file.
   *
   */

/*
 * usage: createTestFiles($name, $suffix, $lic?)
 * if no name, output is to stdout
 * if no suffix, one is picked
 * if name, used as a prefix so multiple names can be generated
 */

function createPHP($FD, $license)
{
  global $commentChar;
  global $endChar;

  $newLine = "\n";
  
  $startTag = "#!/usr/bin/php\n<?php\n";
  $endTag = "\n?>\n";

  $sayHey = "echo \"Hello World!$newLine\";\n";

  if(empty($license))
  {
    $license = _("Copyright Randy Rando, licensed under the BSD license\n");
  }
  $cLic = $commentChar . "\n" . $license . "\n" . $endChar . "\n";
  $body = $startTag . $cLic . $sayHey . $endTag;
  //echo "Body is:\n$body\n";
  $howMany = fwrite($FD,$body);
  fclose($FD);
}

function createPerl($FD, $license)
{
  global $commentChar;
  $startTag = "#!/usr/bin/perl\n";
    
  $sayHey = "print \"Hello World!$newLine\";\n";

  if(empty($license))
  {
    $license = _("Copyright Randy Rando, licensed under the BSD license\n");
  }
  $cLic = $commentChar . "\n" . $license . "\n" . $endChar . "\n";
  $body = $startTag . $cLic . $sayHey . $endTag;
  //echo "Body is:\n$body\n";
  $howMany = fwrite($FD,$body);
  fclose($FD);
  echo "Perl files not implimented\n";
}

function createCprog($FD, $license)
{
  global $commentChar;
  echo "C files not implimented\n";
}

function createHeader($FD, $license)
{
  global $commentChar;
  echo "header files not implimented\n";
}

function createSh($FD, $license)
{
  global $commentChar;
  echo "shell files not implimented\n";
}
function createTxt($FD, $license)
{
  global $commentChar;
  echo "Text files not implimented\n";
}
function createJs($FD, $license)
{
  global $commentChar;
  echo "Javascript files not implimented\n";
}

function createFile($suffix=NULL, $name=NULL, $license=NULL)
{
  require_once 'licenseText.php';
  global $commentChar;
  global $endChar;

  echo "after require\n";

  $licenses = array(
  $gpl2Text,
  $gpl3Text,
  $bsd_d,
  $apache2,
  );

  $suffix = array(
  '.c',
  '.h',
  '.php',
  '.pl',
  '.sh',
  '.txt',
  '.js',
  );

  $defaultName = 'TestFile';

  $sufixNum = rand(0,count($suffix)-1);
  $licensePick = rand(0,count($licenses)-1);

  /*
   * General flow:
   * - use random suffix if none passed in.
   * - create the default name, if none passed in
   *   - Determine comment style
   * - pick a license randomly
   * - construct all needed file parts (choosing comment style before)
   * - write the file.
   *
   */

  // first imp: just use defaults for now.

  $name = $defaultName . $suffix[$sufixNum];
  echo "***** Getting license *****\n";
  $license = $licenses[$licensePick];

  // create the file
  echo "name is:$name\n";
  $FD = fopen($name,'w');

  $commentChar = NULL;
  $endChar = NULL;
  $newLine = "\n";

  switch ($suffix[$sufixNum])
  {
    case '.c':
      $commentChar = '/*';
      $endChar = '*/';
      $rtn = createCprog($FD, $license);
      break;
    case '.h':
      $commentChar = '/*';
      $endChar = '*/';
      $rtn = createHeader($FD, $license);
      break;
    case '.php':
      $commentChar = '/*';
      $endChar = '*/';
      $rtn = createPHP($FD, $license);
      break;
    case '.js':
      $commentChar = '/*';
      $endChar = '*/';
      break;
    case '.pl':
      $commentChar = '#';
      $rtn = createPerl($FD, $license);
      break;
    case '.js':
      $commentChar = '/*';
      $endChar = '*/';
      $rtn = createJs($FD, $license);
      break;
    case '.sh':
      $commentChar = '#';
      $rtn = createSh($FD, $license);
      break;
    case '.txt':
      $commentChar = '#';
      $rtn = createTxt($FD, $license);
      break;

    default:
      $commentChar = NULL;   // should never happen
      break;
  }
}

echo "*********** starting.... *************\n";
createFile();
