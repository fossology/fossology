#!/usr/bin/php
<?php
/*
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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

/*
    cleanup_test_databases.php

    Clean up all FOSSology test databases and associated configuration
    created by create_test_database.php

*/

/* our database parameters.  Connect to template1, since we are going to
   attempt to delete all of the extant test databases */
$postgres_params  = "dbname=template1 ";
$postgres_params .= "host=localhost ";
$postgres_params .= "user=fossologytest ";
$postgres_params .= "password=fossologytest ";

$PG_CONN = pg_connect($postgres_params)
    or die("FAIL: Could not connect to postgres server\n");

/* query postgres for all the test databases */
$sql = "SELECT datname from pg_database where datname like 'fossologytest%'";
$result = pg_query($PG_CONN, $sql)
    or die("FAIL: Could not query postgres database\n");

/* drop each test database found */
while ($row = pg_fetch_row($result)) {
    $dbname = $row[0];
    echo "Dropping test databaes $dbname\n";
    $drop_sql = "DROP DATABASE $dbname";
    pg_query($PG_CONN, $drop_sql) 
        or die("FAIL: Could not drop database $dbname\n");
}

pg_close($PG_CONN);

/* also try and cleanup old style test databases */
/* our database parameters.  Connect to template1, since we are going to
   attempt to delete all of the extant test databases */
$postgres_params  = "dbname=template1 ";
$postgres_params .= "host=localhost ";
$postgres_params .= "user=fossy ";
$postgres_params .= "password=fossy ";

$PG_CONN = pg_connect($postgres_params)
    or die("FAIL: Could not connect to postgres server\n");

/* query postgres for all of the OLD style test databases */
$sql = "SELECT datname from pg_database where datname like 'fosstest%'";
$result = pg_query($PG_CONN, $sql)
    or die("FAIL: Could not query postgres database\n");

/* drop each test database found */
while ($row = pg_fetch_row($result)) {
    $dbname = $row[0];
    echo "Dropping test databaes $dbname\n";
    $drop_sql = "DROP DATABASE $dbname";
    pg_query($PG_CONN, $drop_sql) 
        or die("FAIL: Could not drop database $dbname\n");
}


pg_close($PG_CONN);

/* now delete all of the test directories */
$system_temp_dir = sys_get_temp_dir();
$temp_dirs = glob($system_temp_dir . '/*');

foreach ($temp_dirs as $temp_dir) {
    if (preg_match('/\/fossologytest_\d{8}_\d{6}$/', $temp_dir)) {
        echo "Deleting $temp_dir\n";
        `rm -rf $temp_dir`;
    }
}

exit(0);

?>
