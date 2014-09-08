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


use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class UserDao extends Object {

  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var Logger
   */
  private $logger;

  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className());
  }


  /**
   * @return DatabaseEnum[]
   */
  public function getUserChoices()
  {
    $userChoices = array();
    $statementN = __METHOD__;

    $this->dbManager->prepare($statementN, "select user_pk, user_name from users left join group_user_member as GUM on users.user_pk  = GUM.user_fk  where GUM.group_fk = $1");
    $res = $this->dbManager->execute($statementN, array($_SESSION['GroupId']));
    while ($rw = pg_fetch_assoc($res))
    {
      $userChoices[] = new DatabaseEnum($rw['user_pk'], $rw['user_name']);
    }
    pg_free_result($res);
    return $userChoices;
  }


  /**
   * @param string $selectElementName
   * @param DatabaseEnum[] $databaseEnum
   * @param int $selectedValue
   * @return array
   */
  static function createSelectUsers($selectElementName, $databaseEnum, $selectedValue, $callbackString ="", $callbackArg = "")
  {
    $output = "<select name=\"$selectElementName\" id=\"$selectElementName\" size=\"1\" ";
    if(!empty($callbackString)) {
      $output .= " onchange =\"$callbackString( this, $callbackArg )\" ";
    }
    $output .= ">\n";
    foreach ($databaseEnum as $option)
    {
      $output .= "<option ";
      $ordinal = $option->getOrdinal();
      if ($ordinal == $selectedValue) $output .= " selected ";

      if($ordinal == $_SESSION['UserId']) {
        $name = _("-- Me --");
      }
      else {
        $name = $option->getName();
      }
      $output .= "value=\"" . $ordinal . "\">" . $name . "</option>\n";
    }
    $output .= "</select>";
    return $output;
  }

} 