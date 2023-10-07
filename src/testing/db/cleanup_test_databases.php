#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/*
    cleanup_test_databases.php

    Clean up FOSSology test databases and associated configuration
    created by create_test_database.php

    If no parameters are provided and no environment variable is set,
    then this script will delete all the test databases it can find.

    If the FOSSOLOGY_TESTCONFIG environment variable is set, then this
    script will delete only the database and associated files for that test.

    Optionally, supply a single command-line parameter of the SYSCONFDIR 
    for a specific test database to be cleaned up.

    Examples:

        ./cleanup_test_database.php
            Clean up all existing test databases

        export FOSSOLOGY_TESTCONFIG=/tmp/fossologytest_20120904_174749; ./cleanup_test_database.php

        ./cleanup_test_database.php /tmp/fossologytest_20120904_174749
            Clean up only the database and files associated with the test DB
            referred to in sysconfdir /tmp/fossologytest_20120904_174749

*/

/* This will be the specific SYSCONFDIR to delete, if one is specified */
$sysconfdir;

/* the name of the environment variable to look for as the SYSCONFDIR */
$test_environment_variable = 'FOSSOLOGY_TESTCONFIG';

/* very first step - check for the FOSSOLOGY_TESTCONFIG environment variable.
   If this exists, then it is the database to be deleted. */
$fossology_testconfig = getenv($test_environment_variable);

if ($fossology_testconfig && strlen($fossology_testconfig) > 1) {
    $sysconfdir = $fossology_testconfig;
}

/* optionally, if a command-line parameter is supplied, then it is the
   sysconfdir (and associated database) to be deleted (overriding the
   FOSSOLOGY_TESTCONFIG environment variable, if present). */
if ($argc > 1) {
    $sysconfdir = $argv[1];
}

/* Check to see if a specific database was provided;  if so make
   sure that its Db.conf file can be read */
if (isset($sysconfdir)) {
    if (is_readable("$sysconfdir/Db.conf")) {
        print "Found a readable '$sysconfdir/Db.conf' file\n";

        /* now get the database name from Db.conf */
        $db_conf_contents = file_get_contents("$sysconfdir/Db.conf");
        /* the database name will be specified as 
              dbname = something; */
        $db_match = preg_match('/dbname\s*\=\s*(.*?)[; ]/i', $db_conf_contents, $matches);

        if ($db_match > 0) {
            $database_name = $matches[1];
            print "Found database name '$database_name'\n";
        }
        else {
            print "Did not find a dbname= parameter in '$sysconfdir/Db.conf'\n";
            exit(1);
        }
    }
    else {
        print "A SYSCONFDIR was specified as '$sysconfdir' but no Db.conf file was found there!\n";
        exit(1);
    }
}
else {
    print "No SYSCONFDIR was specified, so will attempt to delete all test DBs\n";
}


/* our database parameters.  Connect to template1, since we are going to
   attempt to delete one or more of the extant test databases */
$postgres_params  = "dbname=template1 ";
$postgres_params .= "host=localhost ";
/* we assume the fossologytest/fossologytest user exists */
$postgres_params .= "user=fossologytest ";
$postgres_params .= "password=fossologytest ";

$PG_CONN = pg_connect($postgres_params)
    or die("FAIL: Could not connect to postgres server\n");

/* if a sysconfdir / database name was not provided then delete everything */
if ( empty($database_name) ) { 

    /* query postgres for all the test databases */
    $sql = "SELECT datname from pg_database where datname like 'fossologytest%'";
    $result = pg_query($PG_CONN, $sql)
        or die("FAIL: Could not query postgres database\n");

    /* drop each test database found */
    while ($row = pg_fetch_row($result)) {
        $dbname = $row[0];
        echo "Dropping test database $dbname\n";
        $drop_sql = "DROP DATABASE $dbname";
        pg_query($PG_CONN, $drop_sql)
            or die("FAIL: Could not drop database '$dbname'\n");
    }
} 
/* otherwise just delete the specified test database */
else { 
    echo "Dropping test database $database_name ONLY.\n";
    $drop_sql = "DROP DATABASE $database_name";
    pg_query($PG_CONN, $drop_sql)
        or die("FAIL: Could not drop drop database '$dbname'\n");
}
pg_close($PG_CONN);


/* now delete all of the test directories */
$system_temp_dir = sys_get_temp_dir();
$temp_dirs = glob($system_temp_dir . '/*');

/* if a sysconfdir was provided then only delete it */
if (!empty($sysconfdir)) { // delete specified test directory
    $temp_dirs = array($sysconfdir);
}

foreach ($temp_dirs as $temp_dir) {
    /* try to match the directory name for the expected test directory name
       fossologytest_YYYYMMDD_HHmmSS/' */
    if (preg_match('/\/fossologytest_\d{8}_\d{6}\/?$/', $temp_dir)) {
        echo "Deleting $temp_dir\n";
        $escaped_temp_dir = escapeshellarg($temp_dir);
        `rm -rf $escaped_temp_dir`;

    }
}


/* also try and cleanup old style test databases 
   but ONLY if no specific database / sysconfdir was provided */
if (empty($sysconfdir)) {
    print "Attempting to clean up old-style FOSSology test databases\n";

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
        echo "Dropping test databases $dbname\n";
        $drop_sql = "DROP DATABASE $dbname";
        pg_query($PG_CONN, $drop_sql)
            or die("FAIL: Could not drop database $dbname\n");
    }
 
    pg_close($PG_CONN);
}
 
print "Done cleaning up FOSSology test databases\n";

exit(0);
