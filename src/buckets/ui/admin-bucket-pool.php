<?php
/***********************************************************
 Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.

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
 * \file admin-bucket-pool.php
 * \brief The purpose of this is to facilitate editing an existing bucketpool
 */

define("TITLE_admin_bucket_pool", _("Duplicate Bucketpool"));

class admin_bucket_pool extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "admin_bucket_pool";
    $this->Title      = TITLE_admin_bucket_pool;
    $this->MenuList   = "Admin::Buckets::Duplicate Bucketpool";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /**
   * @brief Clone a bucketpool and its bucketdef records.
   *        Increment the bucketpool version.
   *
   * @param $bucketpool_pk - pk to clone.
   * @param $UpdateDefault - 'on' if true,  or empty if false
   *
   * @return the new bucketpool_pk
   *         A message suitable to display to the user is returned in $msg.
   *         This may be a success message or a non-fatal error message.
   */
  function CloneBucketpool($bucketpool_pk, $UpdateDefault, &$msg)
  {
    global $PG_CONN;

    /* select the old bucketpool record */
    $sql = "select * from bucketpool where bucketpool_pk='$bucketpool_pk' ";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);

    /**
     * Get the last version for this bucketpool name.
     * There could be a race condition between getting the last version and
     * inserting the new version, but this is an admin only function and it
     * would be pretty odd if two admins were modifying the same bucketpool
     * at the same instant.  Besides if this does occur, the loser will just
     * get a message about the dup record and no harm done.
     */
    $sql = "select max(version) as version from bucketpool where bucketpool_name='$row[bucketpool_name]'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $vrow = pg_fetch_assoc($result);
    pg_free_result($result);
    $newversion = $vrow['version'] + 1;

    /** Insert the new bucketpool record  */
    $sql = "insert into bucketpool (bucketpool_name, version, active, description) select bucketpool_name, '$newversion', active, description from bucketpool where bucketpool_pk=$bucketpool_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /* Retrieve the new bucketpool_pk */
    $sql = "select bucketpool_pk from bucketpool where bucketpool_name='$row[bucketpool_name]' and version='$newversion'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    $newbucketpool_pk = $row['bucketpool_pk'];

    /* duplicate all the bucketdef records for the new bucketpool_pk */
    $sql = "insert into bucket_def (bucket_name, bucket_color, bucket_reportorder, bucket_evalorder, bucketpool_fk, bucket_type, bucket_regex, bucket_filename, stopon, applies_to)
select bucket_name, bucket_color, bucket_reportorder, bucket_evalorder, $newbucketpool_pk, bucket_type, bucket_regex, bucket_filename, stopon, applies_to from bucket_def where bucketpool_fk=$bucketpool_pk";
    $insertresult = pg_query($PG_CONN, $sql);
    DBCheckResult($insertresult, $sql, __FILE__, __LINE__);
    pg_free_result($insertresult);

    /* Update default bucket pool in user table for this user only */
    if ($UpdateDefault == 'on')
    {
      $sql = "update users set default_bucketpool_fk='$newbucketpool_pk' where user_pk='$_SESSION[UserId]'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }

    return $newbucketpool_pk;
  }

  /**
   * \brief User chooses a bucketpool to duplicate from a select list.
   * The new bucketpool and bucket_def records will be identical
   * to the originals except for the primary keys and bucketpool version
   * (which will be bumped). \n
   * The user can optionally also set their default bucketpool to the
   * new one.  This is the default. \n
   *
   * The user must then manually modify the bucketpool and/or bucketdef
   * records to create their new (modified) bucketpool.
   */
  public function Output()
  {
    global $PROJECTSTATEDIR;

    /* get the bucketpool_pk to clone */
    $bucketpool_pk = GetParm("default_bucketpool_fk",PARM_INTEGER);
    $UpdateDefault = GetParm("updatedefault",PARM_RAW);

    if (!empty($bucketpool_pk))
    {
      $msg = "";
      $newbucketpool_pk = $this->CloneBucketpool($bucketpool_pk, $UpdateDefault, $msg);
      $text = _("Your new bucketpool_pk is");
      $this->vars['message'] = "$text $newbucketpool_pk";
    }

    $V = "<p>";
    $V .= _("The purpose of this is to facilitate editing an existing bucketpool.  Make sure you understand");
    $V .= " <a href='http://www.fossology.org/projects/fossology/wiki/Buckets'>";
    $V .= _("Creating Bucket Pools");
    $V .= "</a> ";
    $V .= _("before continuing.");
    $V .= _(" It will explain why you should create a new bucketpool rather than edit an old one that has already recorded results.");
    $V .= "<p>";
    $V .= _("Steps to modify a bucketpool:");
    $V .= "<ol>";
    $V .= "<li>";
    $V .= _("Create a baseline with your current bucketpool.  In other words, run a bucket scan on something.  If you do this before creating a new modified bucketpool, you can compare the old results with the new to verify it is working as you expect.");
    $V .= "<li>";
    $V .= _("Duplicate the bucketpool (this will increment the bucketpool version and its bucketdef records).  You should also check 'Update my default bucketpool' since new bucket jobs only use your default bucketpool.");
    $V .= "<li>";
    $V .= _("Duplicate any bucket scripts that you defined in $PROJECTSTATEDIR.");
    $V .= "<li>";
    $V .= _("Manually edit the new bucketpool record, if desired.");
    $V .= "<li>";
    $V .= _("Manually insert/update/delete the new bucketdef records.");
    $V .= "<li>";
    $V .= _("Manually insert a new buckets record in the agent table.");
    $V .= "<li>";
    $V .= _("Queue up the new bucket job in Jobs > Schedule Agents.");
    $V .= "<li>";
    $V .= _("Use Buckets > Compare to compare the new and old runs.  Verify the results.");
    $V .= "<li>";
    $V .= _("If you still need to edit the buckets, use Buckets > Remove Bucket Results to remove the previous runs results and repeat starting with editing the bucketpool or def records.");
    $V .= "<li>";
    $V .= _("When the bucket results are what you want, then you can reset all the users of the old bucketpool to the new one (manual sql step).");
    $V .= "</ol>";
    $V .= "<hr>";

    $V .= "<form method='POST'>";
    $text = _("Choose the bucketpool to duplicate");
    $V .= "$text ";
    $Val = "";
    $V .= SelectBucketPool($Val);

    $V .= "<p>";
    $text = _("Update my default bucketpool");
    $V .= "<input type='checkbox' name='updatedefault' checked> $text.";
    $V .= "<p>";
    $text = _("Submit");
    $V .= "<input type='submit' value='$text'>";
    $V .= "</form>";
    return $V;
  }
}

$NewPlugin = new admin_bucket_pool;
$NewPlugin->Initialize();