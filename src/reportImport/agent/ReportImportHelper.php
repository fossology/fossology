<?php
/*
 * Copyright (C) 2015-2017, Siemens AG
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
 */
namespace Fossology\ReportImport;

use EasyRdf_Graph;

class ReportImportHelper
{
  private static function getTokensFromlicenseExpression($licenseExpr) // TODO
  {
    return array_filter(explode(' ', str_replace(array("(",")"), " ", $licenseExpr)));
  }

  public static function getShortnamesFromLicenseExpression($licenseExpr)
  {
    $licenseExprTokens = self::getTokensFromlicenseExpression($licenseExpr);
    $shortnames = array();
    $licenseRefPrefix = "LicenseRef-";
    foreach($licenseExprTokens as $token){
      if($token == "OR")
      {
        $shortnames[] = "Dual-license";
      }
      else if(substr($token, 0, strlen($licenseRefPrefix)) === $licenseRefPrefix)
      {
        $shortnames[] = urldecode(substr($token, strlen($licenseRefPrefix)));
      }
      else
      {
        $shortnames[] = urldecode($token);
      }
    }
    return $shortnames;
  }

  public static function stripPrefix($str)
  {
    $parts = explode('#', $str, 2);
    if (sizeof($parts) === 2)
    {
      return $parts[1];
    }
    return "";
  }

  public static function stripPrefixes($strs)
  {
    return array_map(array(__CLASS__, "stripPrefix"), $strs);
  }

}
