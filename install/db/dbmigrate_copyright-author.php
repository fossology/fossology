<?php
/***********************************************************
 Copyright (C) 2017 Siemens AG
 
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
 * @file dbmigrate_copyright-author.php
 * @brief Reinsert the old email/url/author to new author table
 *        and delete the same from copyright table.
 *        It migrates from 3.1.0 to 3.2.0
 *
 * This should be called after fossinit calls apply_schema.
 **/
$countAuthorColumns = $dbManager->getSingleRow("SELECT count(*) FROM copyright WHERE type <> 'statement' AND content IS NOT NULL;",array(),'getCountAuthorColumns');

if($countAuthorColumns['count'] > 0){

  echo "Insert email/url/author data from copyright to author table... \nIt takes some time depending number of columns... \n";

  $dbManager->queryOnce("INSERT INTO author (agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte, is_enabled)
                         SELECT agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte, is_enabled
                         FROM copyright WHERE type <> 'statement' AND content IS NOT NULL;");
  $countInsertedAuthorColumns = $dbManager->getSingleRow("SELECT count(*) FROM author au INNER JOIN copyright co
                         ON au.pfile_fk = co.pfile_fk WHERE au.author_pk = co.copyright_pk AND au.content = co.content;",array(),'getCountInsertedAuthorColumns');
  if($countAuthorColumns['count'] == $countInsertedAuthorColumns['count']){
    echo "Deleting the email/url/author data from copyright table...\n";
    $dbManager->queryOnce("DELETE FROM copyright WHERE type <> 'statement' AND content IS NOT NULL;");
  } else {
    echo "Something went wrong please execute the postinstall again...\n";
  }
}
