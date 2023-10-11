#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * configTestEnv: configure the test environment
 *
 * @param string $url the url to test, full url with ending /
 * @param string $user the Data-Base user account (e.g. fossy)
 * @param string $password the Data-Base user's password
 *
 * @return 0 if file created, 1 otherwise.
 *
 * @version "$Id: configTestEnv.php 3537 2010-10-05 22:09:20Z rrando $"
 *
 * Created on Jul 31, 2008
 */

// TODO : $usage = "$argv[0] Url User Password [path-to-suite]\n";

// usage done this way as here doc's mess up eclipse colors.
$U = NULL;
$U .= "Usage: $argv[0] Url User Password [proxy]\n\nUrl is a full url with ending /\n";
$U .= "e.g. http://someHost.somedomain/repo/\n\n";
$U .= "Data-Base User and Data-Base Password\n\n";
$U .= "Optional proxy in the form http://web-proxy.xx.com:80xx\n";
$U .= "The proxy format is not checked, so make sure it's correct\n";
$U .= "Very little parameter checking is done.\n\n";
$U .= "For example,\n$argv[0] 'http://fossology.org/' dbuser dbpasswd 'http://web-proxy.somebody.com:8080'\n";
$U .= "Note the single quotes are used to keep the shell happy.\n";
$usage = $U;

// simple parameter checks
if((int)$argc < 4)
{
  print $usage;
  exit(1);
}

// code below fixes php notice if no proxy used on the command line
$proxy = '';
if(empty($argv[4]))
{
  $argv[4] = '';
}

list($me, $url, $user, $password, $proxy) = $argv;
//print "Params: U:$url USR:$user PW:password PROX:$proxy\n";
if(empty($url)) { exit(1); }
if('http://' != substr($url,0,7))
{
  print "$me ERROR not a valid URL\n$url\n\n$usage";
  exit(1);
}

$FD = fopen('./TestEnvironment.php', 'w') or die("Can't open ./TestEnvironment $php_errormsg\n");
$startphp = "<?php\n";
$testGlobals = "global \$USER;\n" .
               "global \$PASSWORD;\n" .
               "global \$URL;\n";
$fullUrl = "\$URL='$url';\n";
$usr = "\$USER='$user';\n";
$passwd = "\$PASSWORD='$password';\n";
$useproxy = NULL;
$endphp = "?>\n";
$tests = getcwd();
$define ="define('TESTROOT',\"$tests\");\n";
if(!(empty($proxy)))
{
  $useproxy = "\$PROXY='$proxy';\n";
  fwrite($FD, "$startphp$testGlobals$fullUrl$usr$passwd$useproxy$define$endphp");
}
else
{
  fwrite($FD, "$startphp$testGlobals$fullUrl$usr$passwd$define$endphp");
}
fclose($FD);
print "./TestEnvironment.php created sucessfully\n";
