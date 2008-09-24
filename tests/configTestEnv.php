#!/usr/bin/php
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
 * configTestEnv: configure the test environment
 *
 * @param string $url the url to test, full url with ending /
 * @param string $user the Data-Base user account (e.g. fossy)
 * @param string $password the Data-Base user's password
 *
 * @return 0 if file created, 1 otherwise.
 *
 * @version "$Id$"
 *
 * Created on Jul 31, 2008
 */

// TODO : $usage = "$argv[0] Url User Password [path-to-suite]\n";

// usage done this way as here doc's mess up eclipse colors.
$U .= "Usage: $argv[0] Url User Password \nUrl is a full url with ending /\n";
$U .= "e.g. http://someHost.somedomain/repo/\n";
$U .= "Data-Base User and Data-Base Password\n";
$U .= "Very little parameter checking is done.\n";
$usage = $U;

// simple parameter checks
if($argc < 4)
{
  print $usage;
  exit(1);
}

list($me, $url, $user, $password, $Testpath) = $argv;
//print "Params: U:$url USR:$user PW:password TP:$Testpath\n";
if(empty($url)) { exit(1); }
if('http://' != substr($url,0,7))
{
  print "$me ERROR not a valid URL\n$url\n\n$usage";
  exit(1);
}

$FD = fopen('./TestEnvironment.php', 'w') or die("Can't open ./TestEnvironment $php_errormsg\n");
$startphp = "<?php\n";
$fullUrl = "\$URL='$url';\n";
$usr = "\$USER='$user';\n";
$passwd = "\$PASSWORD='$password';\n";
$endphp = "?>\n";

fwrite($FD, "$startphp$fullUrl$usr$passwd$endphp");
fclose($FD);
print "./TestEnvironment.php created sucessfully\n";
?>
