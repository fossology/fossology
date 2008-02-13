<?php
/***********************************************************
 del.h.php
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

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
require_once("pathinclude.h.php");


/**
 * Delete an upload
 * This includes:
 *    upload record
 *    uploadtree records
 *    unshared pfile recs
 *    agent_lic_meta recs
 *    agent_lic_status recs
 *    unshared ufile recs
 *    unshared repo files
 *
 * This needs to be kept current with any new tables
 *
 * @param  int      $upload_pk  upload  to delete
 * 
 */
function del_upload($upload_pk)
{
    // delete any jobs that refer to this upload_pk
    if (!empty($upload_pk))
    {
        $sql = "select job_pk from job where job_upload_fk=$upload_pk";
        $job_pkrecs = db_queryall($sql);
        $jobpkarray = array();
        foreach ($job_pkrecs as $jobpk) $jobpkarray[] = $jobpk["job_pk"];
        job_delete($jobpkarray);
    }

    $sql = "select * from uptreeup where upload_fk=$upload_pk";
    $uptreeuprecs = db_queryall($sql);

    $pfiles2del = array();
    $ufiles2del = array();

    foreach ($uptreeuprecs as $utrec)
    {
        // check if the pfile is reused in another upload
        $sql = "select count(*) from uptreeup where pfile_pk=$utrec[pfile_pk] 
                   and upload_fk <> $upload_pk limit 1";
        $pcount = db_query1($sql);

        // if there is no other upload that uses  this pfile,
        // then delete the repo file, pfile rec, agent_lic_meta rec, agent_lic_status rec
        if ($pcount == 0)
        {
           // make a list of ufiles to delete so they can be removed after the upload rec
           $ufiles2del[] = $utrec['ufile_pk'];

           // make a list of pfiles to delete so they can be removed after the ufiles
           $pfiles2del[] = $utrec['pfile_pk'];

            // remove the repo file
            // check if exists before deleting rather than using @unlink so that permission and
            // other problems are not masked
            $rpath = reppath($utrec);
            if (file_exists($rpath))
            {
                $success = unlink($rpath);
                if ($success == false) 
                    log_write("log_error", "Failed to delete: $utrec[ufile_name] $rpath",
                              "del_upload()", 'Unknown', 'NULL', 'NULL', true);
           }

           // delete records
           $sql = "delete from agent_lic_status where pfile_fk=$utrec[pfile_pk]";
           $success = db_query($sql);
            if ($success == false) 
                log_write("log_error", "Failed to clean agent_lic_status. pfile: $utrec[pfile_pk]",
                          "del_upload()", 'Unknown', 'NULL', 'NULL', true);

           $sql = "delete from agent_lic_meta where pfile_fk=$utrec[pfile_pk]";
           $success = db_query($sql);
            if ($success == false) 
                log_write("log_error", "Failed to clean agent_lic_meta. pfile: $utrec[pfile_pk]",
                          "del_upload()", 'Unknown', 'NULL', 'NULL', true);

           // delete attribs
           $sql = "delete from attrib where pfile_fk=$utrec[pfile_pk]";
           $success = db_query($sql);
            if ($success == false) 
                log_write("log_error", "Failed to clean attrib.pfile_fk: $utrec[pfile_pk]",
                          "del_upload()", 'Unknown', 'NULL', 'NULL', true);
 
        }
    }


    // delete uploadtree recs for this upload
    $sql = "delete from uploadtree where upload_fk=$upload_pk";
    db_query($sql);

    // delete upload rec for this upload
    $sql = "delete from upload where upload_pk=$upload_pk";
    db_query($sql);

    // check if each ufile is reused, and delete it if not
    foreach ($ufiles2del as $ufile_pk)
    {
        $sql = "select count(*) from uploadtree where upload_fk<> $upload_pk
                    and ufile_fk=$ufile_pk";
        $ucount = db_query1($sql);
  
        // if ufile is not reused, delete it
        if ($ucount == 0)
        {
            $sql = "delete from ufile where ufile_pk=$ufile_pk";
            db_query($sql);
        }
    }

    // delete the unused pfiles
    foreach ($pfiles2del as $pfile_pk)
    {
       $sql = "delete from pfile where pfile_pk=$pfile_pk";
       $success = db_query($sql);
        if ($success == false) 
            log_write("log_error", "Failed to clean pfile. pfile: $pfile_pk",
                      "del_upload()", 'Unknown', 'NULL', 'NULL', true);
    }

    return;
}


/** Delete a folder and all its contained folders and uploads
 * @param  int      $folder_pk  folder  to delete
 */
function del_folder($folder_pk)
{
    $sql = "select * from foldercontents where parent_fk=$folder_pk";
    $fcontents = db_queryall($sql);
    foreach ($fcontents as $child)
    {
        if ($child['foldercontents_mode'] == 2)  // if child is an upload
            del_upload($child['child_id']);
        else
            del_folder($child['child_id']);

        // delete foldercontents record
        $sql = "delete from foldercontents where foldercontents_pk=$child[foldercontents_pk]";
        db_query($sql);
    }

    // delete the original folder
    if ($folder_pk != 1)  // hack for root folder
    $sql = "delete from folder where folder_pk=$folder_pk";
    db_query($sql);
}

?>
