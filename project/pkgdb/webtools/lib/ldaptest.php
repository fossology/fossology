<?php
/**
 * Test ldap_query()
 */
require_once "lib/ldap.php";

$ldap_server = 'ldap://ldap.hp.com';

// some sample filters, pick one
//$filter="hporganizationchart=Linux";
$filter="uid=bob.gobeille@hp.com";
$filter="uid=scott.k.peterson@hp.com";
if (array_key_exists("q", $_GET))
{
$filter=$_GET["q"];
}

//$filter="uid=steven.herman@hp.com";
//$filter="ou=TSG ESS Linux&OpenSource Lab";
//$filter="(& (ou=TSG ESS Linux&OpenSource Lab) (manager=*madden*))";

$fieldarray=array( "cn", "c", "manager");
$fieldarray=array( "uid");

//$userarray=ldap_query($ldap_server, $filter, $fieldarray, "");
$userarray=ldap_query($ldap_server, $filter, "all", "print");

echo "ldap test; $filter<hr>";
$i = 0;
print "<table border=1>";
foreach ($userarray as $record)
{
   ++$i;
   print "<tr><td>{$i}</td> ";
   foreach ($fieldarray as $field)
       print "<td>  {$record[$field][0]}   </td>";
   print "</tr>";
}
print "</table>";
print "<hr>";

foreach ($userarray as $record)
{
   foreach ($fieldarray as $field)
       print "  {$record[$field][0]} <br>  ";
}
?>

