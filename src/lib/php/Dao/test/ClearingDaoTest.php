<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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

namespace Fossology\Lib\Dao;

use Fossology\Lib\BusinessRules\NewestEditedLicenseSelector;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Db\Driver\SqliteE;
use Fossology\Lib\Test\TestLiteDb;
use Mockery as M;
use Mockery\MockInterface;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use SQLite3;

class ClearingDaoTest extends \PHPUnit_Framework_TestCase
{

  /** @var TestLiteDb */
  private $testDb;

  /** @var DbManager */
  private $dbManager;

  /** @var NewestEditedLicenseSelector|MockInterface */
  private $licenseSelector;

  /** @var UploadDao|MockInterface */
  private $uploadDao;

  /** @var ClearingDao */
  private $clearingDao;

  public function setUp()
  {
    $this->licenseSelector = M::mock(NewestEditedLicenseSelector::classname());
    $this->uploadDao = M::mock(UploadDao::classname());

    $logger = new Logger('default');
    $logger->pushHandler(new ErrorLogHandler());

    $dbFileName = "/tmp/fossology.sqlite";
    if (file_exists($dbFileName))
    {
      unlink($dbFileName);
    }
    $sqlite3Connection = new SQLite3(true ? $dbFileName : ":memory:", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

    $this->dbManager = new DbManager($logger);
    $this->dbManager->setDriver(new SqliteE($sqlite3Connection));

    $this->clearingDao = new ClearingDao($this->dbManager, $this->licenseSelector, $this->uploadDao);

    $this->dbManager->queryOnce("CREATE TABLE clearing_decision
(
  clearing_pk integer primary key,
  uploadtree_fk integer NOT NULL,
  pfile_fk integer NOT NULL,
  user_fk integer NOT NULL,
  type_fk integer NOT NULL, -- Identifier for clearing_decision_types
  scope_fk integer NOT NULL, -- Identifier for clearing_decision_scopes
  comment text, -- User comment
  reportinfo text, -- public comment
  date_added timestamp)");

    $this->dbManager->queryOnce("CREATE TABLE clearing_decision_scopes
(
  scope_pk integer primary key,
  meaning character varying(30) )");

    $this->dbManager->queryOnce("CREATE TABLE clearing_decision_types
(
  type_pk integer primary key,
  meaning character varying(30) )");

    $this->dbManager->queryOnce("CREATE TABLE clearing_licenses
(
  clearing_fk integer NOT NULL,
  rf_fk integer NOT NULL,
  removed boolean NOT NULL DEFAULT false)");

    $this->dbManager->queryOnce("CREATE TABLE license_ref
(
  rf_pk integer primary key, -- Primary Key
  rf_shortname text NOT NULL, -- GPL, APSL, MIT, ...
  rf_text text NOT NULL, -- reference License text, or regex
  rf_url text, -- URL of authoritative license text
  rf_add_date date, -- Date License added to this table
  rf_copyleft boolean, -- Is license copyleft?
  rf_OSIapproved boolean, -- Is license OSI approved?
  rf_fullname text, -- GNU General Public License, Apple Public Source License, ...
  rf_FSFfree boolean, -- Is license FSF free?
  rf_GPLv2compatible boolean, -- Is license GPL v2 compatible
  rf_GPLv3compatible boolean, -- Is license GPL v3 compatible
  rf_notes text, -- General notes (public)
  rf_Fedora text,
  marydone boolean NOT NULL DEFAULT 0,
  rf_active boolean NOT NULL DEFAULT 1, -- change this to false if you don't want this reference license to be used in new analyses (does  not apply to nomos agent)
  rf_text_updatable boolean NOT NULL DEFAULT 0, -- true if the license text can be updated (eg written by nomos)
  rf_md5 character varying(32) -- md5 of the license text, used to keep duplicates out of the system
)");

    $this->dbManager->queryOnce("CREATE TABLE users
(
  user_pk integer primary key,
  user_name text NOT NULL,
  root_folder_fk integer NOT NULL, -- root folder for this user
  user_desc text,
  user_seed text,
  user_pass text,
  user_perm integer,
  user_email text,
  email_notify character varying(1) DEFAULT 'y', -- Email notification flag
  user_agent_list text, -- list of user agents to automatically run on upload
  default_bucketpool_fk integer,
  ui_preference character varying DEFAULT 'simple', -- ui preference for the user, either simple or original
  new_upload_group_fk integer, -- group given new_upload_perm on new uploads
  new_upload_perm integer, -- permission given to new_upload_group on new uploads
  group_fk integer)");

    $this->dbManager->queryOnce("CREATE TABLE group_user_member
(
  group_user_member_pk integer primary key,
  group_fk integer NOT NULL, -- Group user is a member of
  user_fk integer NOT NULL, -- User foreign key
  group_perm integer NOT NULL) -- Permission: 0=user, 1=admin.  Only Admins can add/remove/assign permissions to users.");

    $this->dbManager->queryOnce("INSERT INTO users (user_name, root_folder_fk) VALUES
      ('myself', 1), ('in_same_group', 2), ('in_trusted_group', 3), ('not_in_trusted_group', 4)");

    $this->dbManager->queryOnce("INSERT INTO group_user_member (group_fk, user_fk, group_perm) VALUES
      (1, 1, 0), (1, 2, 0), (2, 3, 0), (3, 4, 0)");

    $this->dbManager->queryOnce("INSERT INTO license_ref (rf_shortname, rf_text) VALUES
      ('FOO', 'foo text'), ('BAR', 'bar text'), ('BAZ', 'baz text'), ('QUX', 'qux text')");

    $this->dbManager->queryOnce("INSERT INTO clearing_decision_types (meaning) VALUES
      ('user decision'), ('tbd'), ('bulk') ");

    $this->dbManager->queryOnce("INSERT INTO clearing_decision_scopes (meaning) VALUES
      ('global'), ('upload')");

    $this->dbManager->queryOnce("INSERT INTO clearing_decision (pfile_fk, uploadtree_fk, user_fk, type_fk, scope_fk, date_added) VALUES
      (100, 1000, 1, 1, 1, '2014-08-15T12:12:12'),
      (100, 1000, 2, 1, 1, '2014-08-15T10:43:58'),
      (100, 1000, 3, 1, 1, '2014-08-14T14:33:45'),
      (100, 1000, 4, 1, 1, '2014-08-14T11:14:22'),
      (100, 1200, 1, 1, 1, '2014-08-15T12:12:12')");

    $this->dbManager->queryOnce("INSERT INTO clearing_licenses (clearing_fk, rf_fk, removed) VALUES
      (1, 1, 0),
      (1, 2, 0),
      (2, 4, 1),
      (3, 4, 0),
      (5, 3, 0)");
  }

  public function testDBStart()
  {
    $result = $this->clearingDao->getRelevantLicenseDecisionEvents(1000);
    foreach ($result as $row)
    {
      print(implode(" ", $row) . "\n");
    }
    $this->markTestSkipped("not yet implemented");
  }

}
 