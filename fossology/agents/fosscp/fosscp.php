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
 * fosscp
 *
 * cp2foss agent, upload items from the ui (upload from server).
 * It is expected that this program will be called from a ui-plugin.
 * Scheduler should pass in the following parameters
 *
 * @param string $archive the path to the archive to upload
 * @param string $folder_pk the folder id to load under
 * @param (optional) string $description a short meaningful description
 * @param (optional) string $name the name to use for the upload
 * @param string $recurse recurse flag (0 | 1). 0 is only files, 1 is the
 * complete tree.
 * @parm int $upload_pk the upload associated with this request
 *
 * @return 0 for success, 1 for failure....
 *
 * @version "$Id$"
 *
 */

/*
 * This agent should appear in the scheduler.conf as:
 * agent=fosscopy |
 * /usr/local/fossology/agents/engine-shell fosscp_agent \
 * '/usr/local/fossology/agents/fosscp_agent'
 *
 * engine-shell will convert all of the SQL columns into environment
 * variables.  E.G. The MSQ will return pfile=... and pfile_fk=...
 * These will become $ARG_pfile and $ARG_pfile_fk.
 *
 * Access them with the php $_ENV global array.
 *
 */
/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
$GlobalReady = 1;
require_once("pathinclude.h.php");
global $LIBDIR;
global $WEBDIR;
require_once("$LIBDIR/libcp2foss.h.php");

global $Plugins;
global $LIBEXECDIR;
$UI_CLI=1;

// Check Required parameters, save all parameters passed in
// if they are not saved they get over written on the next SQL?

if (empty($_ENV['ARG_upload_pk']))
{
  echo "FATAL: Upload ID (\$ARG_upload_pk) not set. Aborting.\n";
  exit(-1);
}
else
{
  $upload_pk = $_ENV['ARG_upload_pk'];
}

if (empty($_ENV['ARG_folder_pk']))
{
  echo "FATAL: Folder ID (\$ARG_folder_pk) not set. Aborting.\n";
  exit(-1);
}
else
{
  $parent_id = $_ENV['ARG_folder_pk'];
}
if (empty($_ENV['ARG_recurse']))
{
  echo "FATAL: \$ARG_recurse not set. Aborting.\n";
  exit -1;
}
else
{
  $recurse = $_ENV['ARG_recurse'];
}
if (empty($_ENV['ARG_upload_file']))
{
  echo "FATAL: \$ARG_upload_file not set. Aborting.\n";
  exit -1;
}
else
{
  $upload_file = $_ENV['ARG_upload_file'];
}

/*
 * Steps:
 * Check name and description
 * make sure parent (folder_pk) exists
 * if name given (or use default),
 *   check to make sure name isn't associated with parent
 *   IS: Fatal
 *   NOT: create folder
 *        get folder_pk of just created folder
 *        create folderconents (use mode 1<<3, folder_pk, parent_id).
 * depending on the recuse flag, tar up either just the files or the whole
 * tree.
 * schedule wget_agent on the upload_file
 * schedule the default agents via fossjobs
 */

if (empty($_ENV['ARG_name']))
{
  $name = $_ENV['ARG_upload_file'];
}
else
{
  $name = $_ENV['ARG_name'];
}
if (empty($_ENV['ARG_description']))
{
  $ARG_description="Upload of $name";
}
else
{
  $description=$_ENV['ARG_description'];
}

// Make sure the parent folder exists
echo "SELECT * FROM folder WHERE folder_pk = '$parent_id';";
if ( $_ENV['ARG_folder_pk'] != $parent_id )
{
  echo "FATAL: Upload folder $name already exists, upload canceled\n";
  exit(1);
}

// folder name exists under the parent?
echo "SELECT * FROM leftnav WHERE name = '$name' AND parent = '$parent_id' AND foldercontents_mode = '1';";
/*
 if (!empty($_ENV['ARG_folder_pk']))
 {
 echo "FATAL: Upload folder $name already exists under parent folder, upload canceled\n";
 exit(1);
 }
 echo "DEBUG: folder Create: fosscp agent\n";
 /*
 * Create the folder
 * Block SQL injection by protecting single quotes
 *
 * Protect the folder name with htmlentities.
 */
$name        = str_replace("'", "''", $name);           // PostgreSQL quoting
$description = str_replace("'", "''", $description);    // PostgreSQL quoting

echo "!INSERT INTO folder (folder_name,folder_desc) VALUES ('$name','$description');";
echo "SELECT folder_pk FROM folder WHERE folder_name='$name' AND folder_desc='$description';";
if (empty($_ENV['ARG_folder_pk']))
{
  echo "FATAL: Upload folder $name was not get created, upload canceled\n";
  exit(1);
}
else
{
  $child_id = $_ENV['ARG_folder_pk'];
}

// put the folder info into foldercontest table

echo "!INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES ('$parent_id','1<<3','$child_id');";

/*
 * save files or the whole tree?
 * NOTE, need to also test the type of the $upload_path, if they said get
 * everything, but they pointed at a file, the corect thing should occur.
 */

if (is_dir($upload_file))
{
  if ($recurse == 'y')
  {
    //tar up everything
    if( false === $upload_path = suckupfs($upload_file, TRUE));
    {
      echo "FATAL: Could not open $upload_path\n";
      exit(1);
    }
  }
  else
  {
    // save just the files
    if( false === $upload_path = suckupfs($upload_file, FALSE));
    {
      echo "FATAL: Could not open $upload_path\n";
      exit(1);
    }
  }
}
else
{
  $upload_path = $upload_file;
}
// Run wget_agent locally to import the file.

$Prog = "$LIBEXECDIR/agents/wget_agent -k $upload_pk '$upload_path'";
$last = exec($Prog, $output, $rtn_code);
// unlink($upload_path);
// echo "LOG: return code from wget is:$rtn_code";

if ($rtn_code != 0)
{
  echo "FATAL: Could not download the file with wget\n";
  exit(1);
}

exit(0);  # done successfully

?>