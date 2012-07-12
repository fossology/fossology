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

/* very first step - check for the FOSSOLOGY_TESTCONFIG environment variable.
   If this exists, then our job here is done */
$fossology_testconfig = getenv('FOSSOLOGY_TESTCONFIG');

if ($fossology_testconfig && strlen($fossology_testconfig) > 1) {
    // just echo the value of the environment variable, and exit
    echo "$fossology_testconfig\n";
    exit(0);
}
else {
    #echo "Did not find a valid FOSSOLOGY_TESTCONFIG environment variable\n";
}

/* First check to see if we can connect to Postgres as the 'fossologytest' user

   This is done with the 'psql' command, using these options:

   --no-password tells psql to never prompt for a password; if authentication 
                 is not possible via other means (.pgpass file or the 
                 PGPASSWORD environment variable) then psql will fail.
   --dbname=template1 specifies the 'template1' database which should
                 exist on all Postgres installations;  we never actually use
                 this database, but Postgres requires that you always 
                 connect to an existing database

    Question:  is there any benefit to doing this in native PHP instead of
    making calls to the command-line psql tool?
*/

#echo "Validating connection to Postgres database via 'psql'... ";
$sql_statement = "\q";
$psql_command = "psql --no-password --username=fossologytest --dbname=template1 --host=localhost \
    --command=\"$sql_statement\" 2>&1";
#echo "$psql_command\n";
exec($psql_command, $psql_output_array, $psql_return_value);

/* Concatenate all the output, separated by newlines */
$psql_output = implode("\n", $psql_output_array);

if ($psql_return_value > 0) {
    // for some reason, we could not connect to Postgres as the fossologytest
    // user.  Try to determine why, and notify the user
    echo "FAIL!  output was:\n";
    echo "$psql_output\n";
    if (   preg_match('/no password supplied/i', $psql_output)
        || preg_match('/peer authentication failed/i', $psql_output) ) {
        echo "Before you can run tests, you must create a Postgres user called 'fossologytest'\n";
        echo "with the CREATEDB permission.  This would be done using the following command:\n";
        echo "\n    CREATE USER fossologytest WITH CREATEDB LOGIN PASSWORD 'fossologytest';\n";
    }
    exit($psql_return_value);
}
else {
    #echo "Successfully connected to PostgreSQL as user 'fossologytest'.\n";
    #echo "$psql_output\n";
}


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
#echo "Creating test database... ";
$test_db_name = "fossologytest_$testing_timestamp";
// note: normal 'mortal' users cannot choose 'SQL_ASCII' encoding 
// unless the LC_CTYPE environment variable is set correctly
#$sql_statement="CREATE DATABASE $test_db_name ENCODING='SQL_ASCII'";
$sql_statement="CREATE DATABASE $test_db_name ENCODING='UTF8'";
$psql_command  = "psql --no-password --username=fossologytest --dbname=template1 --host=localhost \
    --command=\"$sql_statement\" 2>&1";
#echo "$psql_command\n";

exec($psql_command, $psql_output_array, $psql_return_value);

/* Concatenate all the output, separated by newlines */
$psql_output = implode("\n", $psql_output_array);

if ($psql_return_value > 0) {
    echo "FAIL!  output was:\n";
    echo "$psql_output\n";
    exit($psql_return_value);
}
else {
    #echo "Successfully created database '$test_db_name'.\n";
    #echo "$psql_output\n";
}

/* Do some minimal setup of the new database */
// Note: from Postgres 9.1 on, can use 'CREATE OR REPLACE LANGUAGE'
// instead of dropping and then re-creating

// first check to make sure we don't already have the plpgsql language installed
$sql_statement = "select lanname from pg_language where lanname = 'plpgsql'";
$psql_command  = "psql --no-password --username=fossologytest --dbname=$test_db_name --host=localhost \
    --command=\"$sql_statement\" 2>&1";
#echo "$psql_command\n";
exec($psql_command, $psql_output_array, $psql_return_value);

/* Concatenate all the output, separated by newlines */
$psql_output = implode("\n", $psql_output_array);

if ($psql_return_value > 0) {
    echo "FAIL!  output was:\n";
    echo "$psql_output\n";
    exit($psql_return_value);
}
else {
    if ( preg_match('/plpgsql/', $psql_output) ) {
        $plpgsql_already_installed = TRUE;
    }
    else {
        $plpgsql_already_installed = FALSE;
    }
}

// then create language plsql if not already created
if ( $plpgsql_already_installed == FALSE ) {
    $sql_statement = "CREATE LANGUAGE plpgsql";
    $psql_command  = "psql --no-password --username=fossologytest --dbname=$test_db_name --host=localhost \
        --command=\"$sql_statement\" 2>&1";
    #echo "$psql_command\n";
    exec($psql_command, $psql_output_array, $psql_return_value);

    /* Concatenate all the output, separated by newlines */
    $psql_output = implode("\n", $psql_output_array);

    if ($psql_return_value > 0) {
        echo "FAIL!  output was:\n";
        echo "$psql_output\n";
        exit($psql_return_value);
    }
    else {
        #echo "Successfully created plpgsql language.\n";
        #echo "$psql_output\n";
    }
}

/* now create a valid Db.conf file in the testing temp directory 
   for accessing our fancy pants new test database */
$db_conf_fh = fopen("$testing_temp_dir/Db.conf", 'w')
    or die("FAIL! Cannot write $testing_temp_dir/Db.conf\n");
fwrite($db_conf_fh, "dbname   = $test_db_name;\n");
fwrite($db_conf_fh, "host     = localhost;\n");
fwrite($db_conf_fh, "user     = fossologytest;\n");
// Note: there may be an inconsistency here between the implicit password
// specified in a .pgpass file or $PGPASSWORD environment variable, and
// the value we write to the Db.conf file.
fwrite($db_conf_fh, "password = fossologytest;\n");
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
    // skip dotted directories, lib/, cli/, and other non-agent directories
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
$fo_conf_fh = fopen("$testing_temp_dir/fossology.conf", 'w')
    or die("FAIL: Could not open $testing_temp_dir/fossology.conf for writing\n");
fwrite($fo_conf_fh, "; fossology.conf for testing\n");
fwrite($fo_conf_fh, "[FOSSOLOGY]\n");
// for the moment, pick a seemingly-innocuous high port number
// at some future time we in fact want to assign a random port number
// during the tests, both to prevent collisions, and also to fully 
// exercise the application code's ability to deal with different port numbers
fwrite($fo_conf_fh, "port = 18529\n");
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
fwrite($fo_conf_fh, "LIBEXECDIR==$fo_base_dir/lib\n");
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

//echo "Connecting to test database via PHP pg_connect()\n";
// create a native PHP database connection to our test database
$postgres_params  = "dbname=$test_db_name ";
$postgres_params .= "host=localhost ";
/* let's not assume anything about the port number here.  At some
   point we may want to query the system to verify the correct postgres
   port number, but not right now.  pg_connect assumes the default
   postgres port of 5432, which is good enough for the moment */
$postgres_params .= "user=fossologytest ";

// make sure that the new database could actually connect
$PG_CONN = pg_connect($postgres_params)
    or die("FAIL! Cannot connect to newly-created database $postgres_params\n");

// apply the core schema
// We need to silence the normal output generated by ApplySchema 
// or it will interfere with our makefile interface
ob_start();
$apply_result = ApplySchema($core_schema_dat_file);
ob_end_clean();
if (!empty($apply_result)) { 
    die("FAIL:  ApplySchema did not succeed.  Output was:\n$apply_result\n");
}
// insert a test user
$random_seed = rand().rand();
$hash = sha1($random_seed . "fossy");
$user_sql = "INSERT INTO users (user_name, user_desc, user_seed, user_pass, user_perm, user_email, email_notify, root_folder_fk) VALUES ('fossy', 'Default Administrator', '$random_seed', '$hash', 10, 'fossy', 'n', 1);";
pg_query($PG_CONN, $user_sql)
    or die("FAIL: could not insert default user into user table\n");

pg_close($PG_CONN);

/* When we finish successfully, print out the testing SYSCONFDIR on
   the second-to-last line, and the testing database name on the last
   line of the script's output */
echo "$testing_temp_dir\n";

// indicate a successful run
exit(0);

?>
