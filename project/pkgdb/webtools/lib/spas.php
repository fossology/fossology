<?php
include_once "lib/db.php";
include_once "lib/myphp.php";

function spas_start($dbname, $startsess=true)
{
  if ($startsess) session_start();

  // open the site database
  $pwpath = "/etc/spas/" . $dbname . ".pw";
  $pwarray = parse_ini($pwpath);
  db_connect($pwarray["user"], $pwarray["pw"], $dbname);
}
