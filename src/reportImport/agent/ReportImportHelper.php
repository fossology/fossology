<?php
/*
 SPDX-FileCopyrightText: © 2015-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\ReportImport;

use EasyRdf\Graph;

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
