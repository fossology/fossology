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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/************************************************************
 GetMimeType(): Given a pfile_pk, return a string that describes
 the mime type.
 (This is in common-repo since mimetypes apply to repo contents.
 ************************************************************/
function GetMimeType($PfilePk)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }

  $Sql = "SELECT * FROM mimetype JOIN pfile ON pfile.pfile_mimetypefk = mimetype.mimetype_pk WHERE pfile_pk = $PfilePk LIMIT 1;";
  $Results = $DB->Action($Sql);
  $Meta = $Results[0]['mimetype_name'];
  if (empty($Meta)) { $Meta = 'application/octet-stream'; }
  return($Meta);
} /* GetMimeType() */

/************************************************************
 RepPath(): Given a pfile id, retrieve the pfile path.
 NOTE: The filename at the path may not exist.
 In fact, the entire path may not exist!
 Returns the path, or NULL if the pfile record does not exist.
 ************************************************************/
function RepPath($PfilePk, $Repo="files")
{
  global $Plugins;
  global $LIBEXECDIR;
  global $DB;
  if (empty($DB)) { return; }

  $Sql = "SELECT * FROM pfile WHERE pfile_pk = $PfilePk LIMIT 1;";
  $Results = $DB->Action($Sql);
  $Row = $Results[0];
  if (empty($Row['pfile_sha1'])) { return(NULL); }
  $Hash = $Row['pfile_sha1'] . "." . $Row['pfile_md5'] . "." . $Row['pfile_size'];
  exec("$LIBEXECDIR/reppath $Repo $Hash", $Path);
  return($Path[0]);
} // RepPath()

?>
