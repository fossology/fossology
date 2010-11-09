#!/usr/bin/php
<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/**
 * xml2Junit
 * \brief transform an xml output into junit style xml that is
 * compatible with hudson
 *
 * @version "$Id$"
 *
 * Created on Oct 14, 2010
 */

/*
 * xml2Junit (x2j?)
 *
 * x2j [- h] -f <file> [-o <file>] -x <file>
 *
 * -h usage
 * -f file the xml input file
 * -o optional output file, will overwrite if it exists
 * -x xsl file to use in the transformation
 *
 */

$usage = "{$argv[0]} [-h] -f <file> [-o <file>] -x <file>\n" .
         "Options:\n" .
         "-h: usage\n" .
         "-f <file>: the xml input file\n" .
         "-o <file>: optional output filepath, overwritten if exists\n" .
         "-x <file>: the xsl file to use in the transformation\n";

// process options
$options = getopt('hf:o:x:');

if(array_key_exists('h',$options))
{
  echo "$usage\n";
}

if(array_key_exists('f',$options))
{
  // make sure it exists and readable
  if(is_readable($options['f']))
  {
    $xmlFile = $options['f'];
  }
  else
  {
    echo "FATAL: xml file {$options['f']} does not exist or cannot be read\n";
  }
}

if(array_key_exists('o',$options))
{
  $outputFile = $options['o'];
}

if(array_key_exists('x',$options))
{
  // make sure it exists and readable
  if(is_readable($options['x']))
  {
    $xslFile = $options['x'];
  }
  else
  {
    echo "FATAL: xsl file {$options['x']} does not exist or cannot be read\n";
  }
}
//$xslFile = 'simpletest_to_junit.xsl';
//$ckz = '../../nomos/ckzend.xml';

$xslDoc = new DOMDocument();
$xslDoc->load($xslFile);

$xmlDoc = new DOMDocument();
$xmlDoc->load($xmlFile);

$proc = new XSLTProcessor();
$proc->importStylesheet($xslDoc);
$transformed = $proc->transformToXML($xmlDoc);

if($outputFile)
{
  // open file and write output
  $OF = fopen($outputFile, 'w') or
    die("Fatal cannot open output file $outputFile\n");
  $wrote = fwrite($OF, $transformed);
  fclose($OF);
}
else
{
  // no output file, echo to stdout
  echo "$transformed\n";
}
?>
