<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Test scheduler connection and status
 */

error_reporting(E_ALL);

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

$address = '127.0.0.1';
$port = 5555;

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
{
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "<br>\n";
}

$result = socket_connect($sock, $address, $port);
if ($result === false)
{
  echo "<h2>Connection to the scheduler failed.  Is the scheduler running?</h2>";
  echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($sock)) . "<br>\n";
  exit;
}
else
{
  echo "Connected to scheduler at '$address' on port '$port'...<br>";
}

$msg = "status";
socket_write($sock, $msg, strlen($msg));

while ($buf = socket_read($sock, 2048, PHP_NORMAL_READ))
{
  if (substr($buf, 0, 3) == "end") break;  // end of scheduler response
  echo "Status is:<br>$buf<br>";
}

echo "Closing socket<br>";
socket_close($sock);
