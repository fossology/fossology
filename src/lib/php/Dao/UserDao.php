<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\Dao;


use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Fossology\Lib\View\Renderer;
use Monolog\Logger;

class UserDao extends Object {

  /* @var DbManager */
  private $dbManager;
  /* @var Renderer */
  private $renderer;
  /* @var Logger */
  private $logger;

  function __construct(DbManager $dbManager, Renderer $renderer)
  {
    $this->dbManager = $dbManager;
    $this->renderer = $renderer;
    $this->logger = new Logger(self::className());
  }


  /**
   * @return array
   */
  public function getUserChoices()
  {
    $userChoices = array();
    $statementN = __METHOD__;

    $this->dbManager->prepare($statementN, "select user_pk, user_name from users left join group_user_member as GUM on users.user_pk  = GUM.user_fk  where GUM.group_fk = $1");
    $res = $this->dbManager->execute($statementN, array($_SESSION['GroupId']));
    while ($rw = $this->dbManager->fetchArray($res))
    {
      $userChoices[$rw['user_pk']] = $rw['user_name'];
    }
    $this->dbManager->freeResult($res);
    return $userChoices;
  }


  /**
   * @param string $selectElementName
   * @param array $databaseMap
   * @param int $selectedValue
   * @return array
   */
  public function createSelectUsers($selectElementName, $databaseMap, $selectedValue, $callbackString ="", $callbackArg = "")
  {
    $action =  " onchange =\"$callbackString( this, $callbackArg )\" ";
    if (array_key_exists($_SESSION['UserId'], $databaseMap))
    {
      $databaseMap[$_SESSION['UserId']] = _('-- Me --');
    }
    $databaseMap[1] = _('Unassigned');
    return $this->renderer->createSelect($selectElementName,$databaseMap, $selectedValue,$action);
  }

} 