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

// If set to TRUE, print additional DEBUG information.  Note that
// printing this information will prevent the script from working
// from within the FOSSology makefiles
$debug = FALSE;

/*
    create_test_database.php

    Create a FOSSology test database and associated configuration,
    if one does not already exist.


    Here is what this script does:

    0) Check whether an environment variable FOSSOLOGY_TESTCONFIG is set
       If so, this environment variable should point to the fossology 
       testing system configuration directory, which will be something like:
 
           /tmp/fossologytest_20120611_172315/ 

       If FOSSOLOGY_TESTCONFIG is set, then simply exit.  
       We do not validate the testing environment further, but any 
       subsequent tests should be able to use this test database and 
       system configuration.

    1) Make sure we can connect to postgres as the 'fossologytest' user.

       For this to work, the password must be specified in one of:

           * the PGPASSWORD environment variable
           * the pgpass file specified by the PGPASSFILE environment variable
           * the current user's ~/.pgpass file 

       If we cannot connect as user 'fossologytest' then a few things
       should be checked:

           a) Is PostgreSQL installed and running?
           b) Does a 'fossologytest' Postgres user exist?
           c) is a known password configured for user 'fossologytest'?

    2) Connect as the fossologytest user and create a new empty database

       The database should have a unique, easily-identifiable name that we
       can use in subsequent FOSSology unit, function, or other tests.
     
       It will be called: 'fossologytest_YYYYMMDD_hhmmss
 
       where YYYYMMDD_hhmmss is a timestamp indicating when the database
       was created.

    3) Load the core-schema.dat database schema into the new database

       This will be the core schema from the current working copy of the
       FOSSology code from which this script is being executed.

    3) Create a temporary fossology systme configuration directory for testing

       This is created in the system's temporary directory (e.g. /tmp).
       It is named after the current timestamp.  Example:

           /tmp/fossologytest_20120611_172315/ 

    4) Create a Db.conf file in the testing system config directory
 
       This contains the database connection parameters that were just set up

    5) Create a mods-enabled directory in the temporary fossology system
       configuration directory, and populate it with symlinks to the 
       working copy of fossology.

    6) Create a repository directory in the temporary fossology system
       configuration directory for use in testing.

    7) Generate a simple fossology.conf file in the system config directory

*/

/* We are going to break some testing rules here, by including and
   using application code within the testing framework:

   PHP Library:                    Function we use:
   ------------	                   ----------------
   ../../lib/php/libschema.php     ApplySchema() (used to load core-schema.dat)
   ../../lib/php/common-db.php     DBCheckResult()
   ../../lib/php/common-cache.php  ReportCachePurgeAll() (required by ApplySchema)

*/
require_once(__DIR__ . '/../../lib/php/libschema.php');
require_once(__DIR__ . '/../../lib/php/common-db.php');
require_once(__DIR__ . '/../../lib/php/common-cache.php');



$test_username = 'fossologytest';
$test_environment_variable = 'FOSSOLOGY_TESTCONFIG';



/* very first step - check for the FOSSOLOGY_TESTCONFIG environment variable.
   If this exists, then our job here is done.  
   We simply echo the value to stdout and exit */
$fossology_testconfig = getenv($test_environment_variable);

if ($fossology_testconfig && strlen($fossology_testconfig) > 1) {

    // just echo the value of the environment variable, and exit
    echo "$fossology_testconfig\n";
    exit(0);

}
else {
    debug("Did not find a valid $test_environment_variable environment variable");
}



// check for a PGPASSWORD or PGPASSFILE environment variable
// PGPASSWORD specifies the actual password to use
// PGPASSFILE specifies the location of the 'pgpass' file
// Otherwise the .pgpass file in the current user's $HOME directory is
// where passwords are normally stored.
$pgpass_file = getenv('HOME') . '/.pgpass';

$pg_password_environment = getenv('PGPASSWORD');
$pg_passfile_environment = getenv('PGPASSFILE');

if ( $pg_password_environment ) {
    // A PGPASSWORD environment variable overrides any other
    // password authentication mechanism
    debug("Found a PGPASSWORD environment variable of '$pg_password_environment' overriding any PGPASSFILE or ~/.pgpass authentication");
}
else {
    if ( $pg_passfile_environment ) {
        // A PGPASSFILE environment variable specifies the location 
        // of a pgpass file
        debug("Found a PGPASSFILE environment variable of '$pg_passfile_environment' overriding any ~/.pgpass file");
        $pgpass_file = $pg_passfile_environment;
    }
    if (is_file($pgpass_file)) {
        $pgpass_perms = substr( sprintf("%o", fileperms($pgpass_file)), -4);
        if ($pgpass_perms == '0600') {
            debug("Permissions for $pgpass_file are correct (0600)");
            $pgpass_contents = file($pgpass_file);
            $testuser_found = FALSE;
            foreach ($pgpass_contents as $line) {
                if ( preg_match("/$test_username:[^:]*$/", $line) ) {
                    $testuser_found = TRUE;
                }
            }
                
            if ( $testuser_found == TRUE ) {
                debug("Found a '$test_username' user in $pgpass_file");
            }
            else {
                echo "FAIL: Did not find a '$test_username' user in $pgpass_file\n";
                echo "Before you can run tests, you must first create a Postgres user called '$test_username'\n";
                echo "which has the CREATEDB permission.  This would be done using the following SQL command:\n";
                echo "\n    CREATE USER $test_username WITH CREATEDB LOGIN PASSWORD '$test_username';\n";
                echo "\nOnce done, this user needs to be added to a ~/.pgpass file\n";
                exit(1);
            }
        }
        else {
            echo "FAIL - Permissions for $pgpass_file are NOT correct, must be 0600\n";
            exit (1);
        }
    }
    else {
        echo "FAIL - Pgpass file $pgpass_file does not exist, or is not a regular file\n";
        exit (1);
    }
}



/* Check to see if we can connect to the Postgres server on the
   local host as the 'fossologytest' user, using the built-in
   PHP postgres connector
*/

// database 'template1' should exist by default on all Postgres servers
$template_db              = 'template1';
$initial_postgres_params  = "dbname=$template_db ";
$initial_postgres_params .= "host=localhost ";
/* the default Postgres port is 5432 */
//$postgres_params       .= "port=5432 ";
$initial_postgres_params .= "user=$test_username ";

// make sure that the new database can actually connect
$test_pg_conn = @pg_connect($initial_postgres_params);

/* pg_connect returns a database connection handle, or FALSE if it
   was not able to connect.  
 
   If we were not able to connect, try to figure out why and 
   provide a helpful message to the tester */
if ( $test_pg_conn == FALSE ) {

    $error_array = error_get_last();
    $pg_error_message = $error_array['message'];
    echo "FAIL:  Cannot connect to the local Postgres server.  ";

    if ( preg_match('/no password supplied/', $pg_error_message) ) {
        echo "The '$test_username' user must already exist and be included in a ~/.pgpass file, or the PGPASSWORD environment variable must be set!\n";
        echo "Before you can run tests, you must first create a Postgres user called '$test_username'\n";
        echo "which has the CREATEDB permission.  This would be done using the following SQL command:\n";
        echo "\n    CREATE USER $test_username WITH CREATEDB LOGIN PASSWORD '$test_username';\n";
        echo "\nOnce done, this user needs to be added to a ~/.pgpass file\n";
    }
    elseif ( preg_match('/authentication failed/', $pg_error_message) ) {
        echo "The password for user '$test_username' is not correct!\n";
    }
    elseif ( preg_match("/database \"$template_db\" does not exist/", $pg_error_message) ) {
        echo "The database '$template_db' does not exist!\n";
    }
    else {
        echo "Unknown problem: $pg_error_message\n";
    }

    exit(1);
}
else {
    debug("Successfully connected to local Postgres server as user '$test_username'");
}

pg_close($test_pg_conn) or die ("FAIL: We could not close the posgres connection!");


/* get the system's temporary directory location.  We'll use this as the
   base for the testing instance of the system config directory */
$system_temp_dir = sys_get_temp_dir();

/* generate a timestamp directory name for this testing database instance 
   This will be, for example:
       /tmp/fossologytest_20120611_172315/  */
$testing_timestamp = date("Ymd_His");
$testing_temp_dir = $system_temp_dir . '/fossologytest_' . $testing_timestamp;

mkdir($testing_temp_dir, 0755, TRUE)
    or die("FAIL! Cannot create test configuration directory at: $testing_temp_dir\n");

/* Now create a new, unique dataabase */
debug("Creating test database... ");
$test_db_name = "fossologytest_$testing_timestamp";

// re-connect using the same template1 database as above
$test_pg_conn = @pg_connect($initial_postgres_params)
    or die("FAIL: Could not connect to Postgres server!");

// note: normal 'mortal' users cannot choose 'SQL_ASCII' encoding 
// unless the LC_CTYPE environment variable is set correctly
#$sql_statement="CREATE DATABASE $test_db_name ENCODING='SQL_ASCII'";
$sql_statement="CREATE DATABASE $test_db_name ENCODING='UTF8'";
$result = pg_query($test_pg_conn, $sql_statement) 
    or die("FAIL: Could not create test database!\n");

// close the connection to the template1 database. Now we can 
// reconnect to the newly-created test database
pg_close($test_pg_conn);


/* Now connect to the newly-created test database */
$test_db_params  = "dbname=$test_db_name ";
$test_db_params .= "host=localhost ";
/* the default Postgres port is 5432 */
//$postgres_params       .= "port=5432 ";
$test_db_params .= "user=$test_username ";

$test_db_conn = pg_connect($test_db_params) 
    or die ("Could not connect to the new test database '$test_db_name'\n");

##################################################
##  DEBUG:: CONTINUE WORKING HERE
###################################################

/* Do some minimal setup of the new database */
// Note: from Postgres 9.1 on, can use 'CREATE OR REPLACE LANGUAGE'
// instead of dropping and then re-creating

// first check to make sure we don't already have the plpgsql language installed
$sql_statement = "select lanname from pg_language where lanname = 'plpgsql'";

$result = pg_query($test_db_conn, $sql_statement)
    or die("Could not check the database for plpgsql language\n");

$plpgsql_already_installed = FALSE;
if ( $row = pg_fetch_row($result) ) {
    $plpgsql_already_installed = TRUE;
}

// then create language plpgsql if not already created
if ( $plpgsql_already_installed == FALSE ) {
    $sql_statement = "CREATE LANGUAGE plpgsql";
    $result = pg_query($test_db_conn, $sql_statement)
        or die("Could not create plpgsql language in the database\n");
}

/* now create a valid Db.conf file in the testing temp directory 
   for accessing our fancy pants new test database */
$db_conf_fh = fopen("$testing_temp_dir/Db.conf", 'w')
    or die("FAIL! Cannot write $testing_temp_dir/Db.conf\n");
fwrite($db_conf_fh, "dbname   = $test_db_name;\n");
fwrite($db_conf_fh, "host     = localhost;\n");
fwrite($db_conf_fh, "user     = $test_username;\n");
// Note: because the Db.conf file is itself just the parameters
//       used in the pg_connect() command, we should be able to 
//       safely omit the password, since whatever mechanism was
//       already in place to authenticate can still be used.
//fwrite($db_conf_fh, "password = fossologytest;\n");
fclose($db_conf_fh);


/* now create a mods-enabled directory to contain symlinks to the 
   agents in the current working copy of fossology */
$mods_enabled_dir = "$testing_temp_dir/mods-enabled";
mkdir($mods_enabled_dir, 0755, TRUE)
    or die("FAIL! Cannot create test mods-enabled directory at: $mods_enabled_dir\n");


/* here we have to do the work that each of the agents' 'make install'
   targets would normally do, but since we want the tests to be able
   to execute before FOSSology is installed, we need to do a minimal
   amount of work to enable them */

/* for each src/ directory above us, create a symlink for it in the 
   temporary testing mods-enabled directory we just created;  
   but always skip the cli and lib directories */

$fo_base_dir = realpath(__DIR__ . '/../..');
$src_dirs = scandir($fo_base_dir);

foreach ($src_dirs as $src_dir) {
    $full_src_dir = $fo_base_dir . "/" . $src_dir;
    // skip dotted directories, lib/, cli/, and other irrelevant directories
    if ( preg_match("/^\./", $src_dir) 
         || $src_dir == 'lib' 
         || $src_dir == 'cli' 
         || $src_dir == 'bsam' 
         || $src_dir == 'example_wc_agent' 
         || $src_dir == 'tutorials' 
         || $src_dir == 'srcdocs' 
         || $src_dir == 'testing' 
        ) {
        continue;
    }
    if (is_dir($full_src_dir)) {
        symlink($full_src_dir, "$mods_enabled_dir/$src_dir")
            or die("FAIL - could not create symlink for $src_dir in $mods_enabled_dir\n");
    }
}

/* Now let's set up a test repository location, which is just an empty
   subdirectory within our temporary testing system config directory */
$test_repo_dir = "$testing_temp_dir/repository";
mkdir($test_repo_dir, 0755, TRUE)
    or die ("FAIL! Cannot create test repository directory at: $test_repo_dir\n");


/* now create a valid fossology.conf file in the testing 
   temp directory  */

// be lazy and just use a system call to gather user and group name
$user_name = rtrim(`id -un`);
$group_name = rtrim(`id -gn`);
// generate a random port number above 10,000 to use for testing
$fo_port_number = mt_rand(10001, 32768);

$fo_conf_fh = fopen("$testing_temp_dir/fossology.conf", 'w')
    or die("FAIL: Could not open $testing_temp_dir/fossology.conf for writing\n");
fwrite($fo_conf_fh, "; fossology.conf for testing\n");
fwrite($fo_conf_fh, "[FOSSOLOGY]\n");
fwrite($fo_conf_fh, "port = $fo_port_number\n");
fwrite($fo_conf_fh, "address = localhost\n");
fwrite($fo_conf_fh, "depth = 3\n");
fwrite($fo_conf_fh, "path = $test_repo_dir\n");
fwrite($fo_conf_fh, "[HOSTS]\n");
fwrite($fo_conf_fh, "localhost = localhost AGENT_DIR 10\n");
fwrite($fo_conf_fh, "[REPOSITORY]\n");
fwrite($fo_conf_fh, "localhost = * 00 ff\n");
fwrite($fo_conf_fh, "[DIRECTORIES]\n");
fwrite($fo_conf_fh, "PROJECTUSER=$user_name\n");
fwrite($fo_conf_fh, "PROJECTGROUP=$group_name\n");
fwrite($fo_conf_fh, "MODDIR=$fo_base_dir\n");
fwrite($fo_conf_fh, "BINDIR=$fo_base_dir/cli\n");
fwrite($fo_conf_fh, "SBINDIR=$fo_base_dir/cli\n");
fwrite($fo_conf_fh, "LIBEXECDIR=$fo_base_dir/lib\n");
fclose($fo_conf_fh);


/* Write a VERSION file */

$fo_version_fh = fopen("$testing_temp_dir/VERSION", 'w')
    or die("FAIL: Could not open $testing_temp_dir/VERSION for writing\n");
fwrite($fo_version_fh, "[BUILD]\n");
fwrite($fo_version_fh, "VERSION=trunk\n");
fwrite($fo_version_fh, "SVN_REV=0000\n");
$build_date = date("Y/m/d H:i");
fwrite($fo_version_fh, "BUILD_DATE=$build_date\n");
fclose($fo_version_fh);

/* now load the fossology core schema into the database */
$core_schema_dat_file = $fo_base_dir . "/www/ui/core-schema.dat";

debug("Connecting to test database via PHP pg_connect()");

// use our existing test database connection to populate the
// database with the FOSSology ApplySchema() function

// To make absolutely sure we do not interfere with any existing
// database connections, save off any pre-existing database connections
if (isset($PG_CONN)) {
    $previous_PG_CONN = $PG_CONN;
}

// assign the global PG_CONN variable used by ApplySchema
$PG_CONN = $test_db_conn;

// apply the core schema
// We need to buffer the output in order to silence the normal 
// output generated by ApplySchema, or it will interfere with 
// our makefile interface
ob_start();
$apply_result = ApplySchema($core_schema_dat_file);
ob_end_clean();

// then re-assign the previous PG_CONN, if there was one
if (isset($previous_PG_CONN)) {
    $PG_CONN = $previous_PG_CONN;
}

if (!empty($apply_result)) { 
    die("FAIL:  ApplySchema did not succeed.  Output was:\n$apply_result\n");
}

// insert the 'fossy' user into the test database
$random_seed = rand().rand();
$hash = sha1($random_seed . "fossy");
$user_sql = "INSERT INTO users (user_name, user_desc, user_seed, user_pass, user_perm, user_email, email_notify, root_folder_fk) VALUES ('fossy', 'Default Administrator', '$random_seed', '$hash', 10, 'fossy', 'n', 1);";
pg_query($test_db_conn, $user_sql)
    or die("FAIL: could not insert default user into user table\n");

/* now we are done setting up the test database */
pg_close($test_db_conn);


/* When we finish successfully, print out the testing SYSCONFDIR 
   to stdout as the ONLY output from the script.  In this way it
   can be "imported" into the GNU Make environment  */
debug("Successful test database creation");
echo "$testing_temp_dir\n";

// indicate a successful run
exit(0);






/*********************************************************************
 *********************************************************************
 ***                                                               ***
 ***   Function definitions                                        ***
 ***                                                               ***
 *********************************************************************
 *********************************************************************/

// print a debug message, but only when "$debug" is set
function debug($message) {
    global $debug;
    if ($debug == TRUE) {
        echo "DEBUG: $message\n";
    }
}






?>
