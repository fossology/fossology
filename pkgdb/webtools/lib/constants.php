<?php
// global constants

$testing = false;

// webtools url
$webtools_url = "/webtools";  // doesn't work with public_html
//$webtools_url = "http://". $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]) . "/webtools";

$ldap_server = 'ldap://ldap.hp.com';
$ldap_port   = 389;                          // not used
$ldap_secureserver = 'ldaps://ldap.hp.com';
$ldap_secureport = 636;      
$ssl_port = 443;

if ($testing)
    $login_url = "http://bobgcorp.fc.hp.com/ambas/login.php";
else
    $login_url = "https://linux.corp.hp.com/ambas/login.php";

// authentication session limit (in minutes)
$sess_max_minutes = 60 * 24;

// access
$read_access    = 0;
$readall_access = 1;
$write_access   = 2;

