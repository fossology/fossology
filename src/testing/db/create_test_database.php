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

    Here is what this script does:

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

    3) Load the core-schema.dat database schema into the new database

       This should be the core schema from the current working copy.

    3) Create a temporary fossology systme configuration directory for testing

       This is created in the system's temporary directory (e.g. /tmp).
       It is named after the current timestamp

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

    library                          function names
   ../../lib/php/libschema.php       ApplySchema()
   ../../lib/php/common-cache.php    ReportCachePurgeAll()
   ../../lib/php/common-db.php       DBCheckResult(), others?

*/
require_once(__DIR__ . '/../../lib/php/libschema.php');
require_once(__DIR__ . '/../../lib/php/common-cache.php');
require_once(__DIR__ . '/../../lib/php/common-db.php');

/* First check to see if we can connect to Postgres as the 'fossologytest' user

   --no-password tells psql to never prompt for a password; if authentication 
                 is not possible via other means (.pgpass file or the 
                 PGPASSWORD environment variable) then psql will fail
   --dbname=template1 specifies the 'template1' database which should
                 exist on all Postgres installations;  we never actually use
                 it but Postgres requires you to always connect to a database

    Question:  is there any benefit to doing this in native PHP instead of
    making calls to the command-line psql tool?
*/

echo "Validating connection to Postgres database via 'psql'... ";
$sql_statement = "\q";
$psql_command = "psql --no-password --username=fossologytest --dbname=template1 \
    --command=\"$sql_statement\" 2>&1";
#echo "$psql_command\n";
exec($psql_command, $psql_output_array, $psql_return_value);

/* Concatenate all the output, separated by newlines */
$psql_output = implode("\n", $psql_output_array);

if ($psql_return_value > 0) {
    // for some reason, we could not connect to Postgres as the fossologytest
    // user.  Try to determine why, and notify the user
    echo "ERROR!  output was:\n";
    echo "$psql_output\n";
    if ( preg_match('/no password supplied/', $psql_output) ) {
        echo "Before you can run tests, you need to create a Postgres user called 'fossologytest'\n";
        echo "with the CREATEDB permission.  This would be done using the following command:\n";
        echo "\n    CREATE USER fossologytest WITH CREATEDB LOGIN PASSWORD 'fossologytest';\n";
    }
    exit($psql_return_value);
}
else {
    echo "Success\n";
    #echo "Successfully connected to PostgreSQL as user 'fossologytest'.\n";
    #echo "$psql_output\n";
}


/* get the system's temporary directory location.  We'll use this as the
   base for the testing instance of the system config directory */
$system_temp_dir = sys_get_temp_dir();

/* generate a timestamp directory name for this testing database instance 
   This will be, for example:
       /tmp/fotest-20120611_172315/  */
$testing_timestamp = date("Ymd_His");
$testing_temp_dir = $system_temp_dir . '/fotest-' . $testing_timestamp;

if ( mkdir($testing_temp_dir, 0755, TRUE) === FALSE ) {
    echo "FATAL! Cannot create test configuration directory at: $testing_temp_dir\n" .
        "    at " . __FILE__ . ":" . __LINE__  . "\n";
    exit(1);
} 
else {
    echo "Successfully created test configuration directory at: $testing_temp_dir\n";
}

/* Now create a new, unique dataabase */
echo "Creating test database... ";
$test_db_name = "fossology_test_$testing_timestamp";
// note: normal 'mortal' users cannot choose 'SQL_ASCII' encoding 
// unless the LC_CTYPE environment variable is set correctly
#$sql_statement="CREATE DATABASE $test_db_name ENCODING='SQL_ASCII'";
$sql_statement="CREATE DATABASE $test_db_name ENCODING='UTF8'";
$psql_command  = "psql --no-password --username=fossologytest --dbname=template1 \
    --command=\"$sql_statement\" 2>&1";
#echo "$psql_command\n";

exec($psql_command, $psql_output_array, $psql_return_value);

/* Concatenate all the output, separated by newlines */
$psql_output = implode("\n", $psql_output_array);

if ($psql_return_value > 0) {
    echo "ERROR!  output was:\n";
    echo "$psql_output\n";
    exit($psql_return_value);
}
else {
    echo "Success\n";
    #echo "Successfully created database '$test_db_name'.\n";
    #echo "$psql_output\n";
}

/* Do some minimal setup of the new database */
// Note: from Postgres 9.1 on, can use 'CREATE OR REPLACE LANGUAGE'
$sql_statement = "CREATE LANGUAGE plpgsql";
$psql_command  = "psql --no-password --username=fossologytest --dbname=$test_db_name \
    --command=\"$sql_statement\" 2>&1";
#echo "$psql_command\n";
exec($psql_command, $psql_output_array, $psql_return_value);

/* Concatenate all the output, separated by newlines */
$psql_output = implode("\n", $psql_output_array);

if ($psql_return_value > 0) {
    echo "ERROR!  output was:\n";
    echo "$psql_output\n";
    exit($psql_return_value);
}
else {
    #echo "Successfully created plpgsql language.\n";
    #echo "$psql_output\n";
}


/* now create a valid Db.conf file in the testing temp directory 
   for accessing our fancy pants new test database */
$db_conf_fh = fopen("$testing_temp_dir/Db.conf", 'w');
fwrite($db_conf_fh, "dbname   = $test_db_name;\n");
fwrite($db_conf_fh, "host     = localhost;\n");
fwrite($db_conf_fh, "user     = fossologytest;\n");
// Note: there may be an inconsistency here between the implicit password
// specified in a .pgpass file or $PGPASSWORD environment variable, and
// the value we write to the Db.conf file.
fwrite($db_conf_fh, "password = fossologytest;\n");
fclose($db_conf_fh);
echo "Wrote Db.conf file to $testing_temp_dir\n";


/* now create a mods-enabled directory to contain symlinks to the 
   agents in the current working copy of fossology */
$mods_enabled_dir = "$testing_temp_dir/mods-enabled";
if ( mkdir($mods_enabled_dir, 0755, TRUE) === FALSE ) {
    echo "FATAL! Cannot create test mods-enabled directory at: $mods_enabled_dir\n" .
        "    at " . __FILE__ . ":" . __LINE__  . "\n";
    exit(1);
} 
else {
    echo "Successfully created test mods-enabled directory at: $mods_enabled_dir\n";
}


/* here we have to do the work that each of the agents' 'make install'
   targets would normally do, but since we want the tests to be able
   to execute before FOSSology is installed, we need to do a minimal
   amount of work to enable them */

/* for each src/ directory above us, create a symlink for it in the 
   temporary testing mods-enabled directory we just created;  
   but always skip the cli and lib directories */

$base_dir = realpath(__DIR__ . '/../..');
$src_dirs = scandir($base_dir);
echo "Populating symlinks in $mods_enabled_dir\n";

foreach ($src_dirs as $src_dir) {
    // skip dotted directories, and lib/ and cli/
    if (    preg_match('/^\..*/', $src_dir) 
         || $src_dir == 'lib'
         || $src_dir == 'cli' ) {
        continue;
    }
    $full_src_dir = $base_dir . "/" . $src_dir;
    if (is_dir($full_src_dir)) {
        if (symlink($full_src_dir, "$mods_enabled_dir/$src_dir") != TRUE) {
            echo "Error - could not create symlink for $full_src_dir in $mods_enabled_dir\n";
            exit (1);
        }
        else {
            #echo "Created symlink for $full_src_dir in $mods_enabled_dir\n";
        }
    }
}

/* Now let's set up a test repository location, which is just an empty
   subdirectory within our temporary testing system config directory */
$test_repo_dir = "$testing_temp_dir/repository";
if ( mkdir($test_repo_dir, 0755, TRUE) === FALSE ) {
    echo "FATAL! Cannot create test repository directory at: $test_repo_dir\n" .
        "    at " . __FILE__ . ":" . __LINE__  . "\n";
    exit(1);
} 
else {
    echo "Successfully created test repository directory at: $test_repo_dir\n";
}


/* now create a valid fossology.conf file in the testing 
   temp directory  */

// be lazy and just use a system call
$group_name = rtrim(`id -gn`);
$fo_conf_fh = fopen("$testing_temp_dir/fossology.conf", 'w');
fwrite($fo_conf_fh, "; fossology.conf for testing\n");
fwrite($fo_conf_fh, "[FOSSOLOGY]\n");
fwrite($fo_conf_fh, "port = 18529\n");
fwrite($fo_conf_fh, "address = localhost\n");
fwrite($fo_conf_fh, "depth = 3\n");
fwrite($fo_conf_fh, "path = $test_repo_dir\n");
fwrite($fo_conf_fh, "[HOSTS]\n");
fwrite($fo_conf_fh, "localhost = localhost AGENT_DIR 10\n");
fwrite($fo_conf_fh, "[REPOSITORY]\n");
fwrite($fo_conf_fh, "localhost = * 00 ff\n");
fwrite($fo_conf_fh, "[DIRECTORIES]\n");
fwrite($fo_conf_fh, "PROJECTGROUP=$group_name\n");

fclose($fo_conf_fh);
echo "Wrote fossology.conf file to $testing_temp_dir\n";

/* now load the fossology core schema into the database */
$core_schema_dat_file = $base_dir . "/www/ui/core-schema.dat";

echo "Connecting to test database via PHP pg_connect()\n";
// create a native PHP database connection to our test database
$postgres_params  = "dbname=$test_db_name ";
$postgres_params .= "host=localhost ";
$postgres_params .= "port=5433 ";
$postgres_params .= "user=fossologytest ";
$postgres_params .= "password=fossologytest ";

$PG_CONN = pg_connect($postgres_params);

echo "Applying the core schema in $core_schema_dat_file\n";
// apply the core schema
ApplySchema($core_schema_dat_file);

// now what?


exit;

?>
