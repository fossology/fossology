<?php
/***********************************************************
 Copyright (C) 2015 Siemens AG
 
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


$verbose = true;

function guessSysconfdir($verbose=false)
{
  $rcfile = "fossology.rc";
  $varfile = __DIR__.'/../../variable.list';
  $sysconfdir = getenv('SYSCONFDIR');
  if ((false===$sysconfdir) && file_exists($rcfile))
  {
    if($verbose) echo "touch $rcfile\n";
    $sysconfdir = file_get_contents($rcfile);
  }
  if ((false===$sysconfdir) && file_exists($varfile))
  {
    if($verbose) echo "touch $varfile\n";
    $ini_array = parse_ini_file($varfile);
    if($ini_array!==false && array_key_exists('SYSCONFDIR', $ini_array))
    {
      $sysconfdir = $ini_array['SYSCONFDIR'];
    }
  }
  if (false===$sysconfdir)
  {
    $text = _("FATAL! System Configuration Error, no SYSCONFDIR.");
    echo "$text\n";
    exit(1);
  }
  return $sysconfdir;
}

$sysconfdir = guessSysConfdir(true);
if($verbose)
{
  echo "assuming SYSCONFDIR=$sysconfdir\n";
}

include_once( __DIR__.'/../../src/lib/php/common-db.php' );
DBconnect($sysconfdir);
if(empty($PG_CONN))
{
  echo "failed db connection\n";
  exit(1);
}

if($verbose)
{
  echo "DB connection ok\n";
}


pg_query($PG_CONN, 'ALTER TABLE "clearing_decision" ADD COLUMN "group_fk" int4');
if ($verbose)
{
  echo "Link decisions with groups\n";
}
pg_query($PG_CONN, 'UPDATE clearing_decision cd SET group_fk=u.group_fk FROM users u WHERE cd.user_fk=u.user_pk');

if ($verbose)
{
  echo "Create clearing event table";
}
pg_query($PG_CONN, '
CREATE TABLE clearing_event
(
clearing_event_pk serial NOT NULL,
uploadtree_fk integer NOT NULL,
rf_fk integer NOT NULL,
removed boolean,
user_fk integer NOT NULL,
group_fk integer NOT NULL,
job_fk integer,
type_fk integer NOT NULL,
comment text,
reportinfo text,
date_added timestamp with time zone NOT NULL DEFAULT now(),
CONSTRAINT clearing_event_pkey PRIMARY KEY (clearing_event_pk )
)
WITH (  OIDS=FALSE )');
pg_query($PG_CONN, '
CREATE TABLE clearing_decision_event (
clearing_event_fk integer NOT NULL,
clearing_decision_fk integer NOT NULL
) WITH (OIDS=FALSE)');
if ($verbose)
{
  echo ", fill in old decisions";
}
pg_query($PG_CONN, '
INSERT INTO clearing_event (  uploadtree_fk,
rf_fk,
removed,
user_fk,
group_fk,
job_fk,
type_fk,
comment,
reportinfo,
date_added)
SELECT 
cd.uploadtree_fk,
cl.rf_fk,
(0=1) removed,
cd.user_fk,
cd.group_fk,
null job_fk,
type_fk,
cd.comment,
cd.reportinfo,
cd.date_added
FROM clearing_decision cd, clearing_licenses cl
WHERE cd.clearing_pk=cl.clearing_fk');
if ($verbose)
{
  echo " and link them with decisions\n";
}
pg_query($PG_CONN, '
INSERT INTO clearing_decision_event
SELECT cd.clearing_pk clearing_fk,ce.clearing_event_pk clearing_event_fk
FROM clearing_decision cd, clearing_event ce
WHERE cd.date_added=ce.date_added');

if ($verbose)
{
  echo "Good bye\n";
}