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
 * runtests: run a fossology test suite
 *
 * @param string $url the url to test
 * @param string $user the user account (e.g. fossy)
 * @param string $password the users password
 *
 * @return 0 if test launched, 1 otherwise.
 *
 * @version "$Id: $"
 *
 * Created on Jul 31, 2008
 */

$usage = "$argv[0] Url User Password path-to-suite\n";

var_dump($argv);
var_dump($argc);
print "number of args is:$argc\n";

// process parameters
if($argc <= 4)
{
  print $usage;
  exit(1);
}

list($me, $url, $user, $password, $Testpath) = $argv;
//print "Params: U:$url USR:$user PW:password TP:$Testpath\n";

$FD = fopen('./TestEnvironment.php', 'w') or die("Can't open ./TestEnvironment $php_errormsg\n");
$startphp = "<?php\n";
$furl = "\$URL='$url';\n";
$usr = "\$USER='$user';\n";
$passwd = "\$PASSWORD='$password';\n";
print "$furl";
print "$usr";
print "$passwd";
$endphp = "?>\n";
fwrite($FD, "$startphp$furl$usr$passwd$endphp");

?>
