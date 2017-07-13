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
namespace Fossology\SpdxTwoImport;

use EasyRdf_Graph;

class SpdxTwoImportHelper
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

  //////////////////////////////////////////////////////////////////////////////
  // TODO: (level 2)

  // private static function getTypes($properties)
  // {
  //   $key = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
  //   if (isset($properties[$key]))
  //   {
  //     $func = function($value) { return $value['value']; };
  //     return array_map($func, $properties[$key]);
  //   }
  //   return null;
  // }

  // // or $kind='licenseInfoInFile'
  // private static function getLicenseInfoForFile(&$properties, $kind='licenseConcluded', &$index=null)
  // {
  //   $func = function($value) { return $value['value']; };
  //   $key = self::TERMS . $kind;

  //   if($properties[$key][0]['type'] === 'uri')
  //   {
  //     return array_map($func, $properties[$key]);
  //   }
  //   else if($properties[$key][0]['type'] === 'bnode' &&
  //           array_key_exists($properties[$key][0]['value'],$index))
  //   {
  //     $conclusion = ($index[$properties[$key][0]['value']]);
  //     if ($conclusion[self::SYNTAX_NS . 'type'][0]['value'] == self::TERMS . 'DisjunctiveLicenseSet' &&
  //       array_key_exists(self::TERMS . 'member',$conclusion))
  //     {
  //       return array_map($func, $conclusion[self::TERMS . 'member']);
  //     }
  //   }
  //   echo "the license info type ".$properties[$key][0]['type']." is not supported";
  //   return array();
  // }

  // private static function isPropertyAFile(&$property)
  // {
  //   $key = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
  //   $target = self::TERMS . 'File';

  //   return isset($property[$key]) &&
  //     $property[$key][0]['value'] === $target;
  // }

  // private static function getFileName(&$property)
  // {
  //   return self::getValue($property, 'fileName');
  // }

  // private static function getValue(&$property, $key)
  // {
  //   $key = self::TERMS . $key;
  //   if (self::isPropertyAFile($property) &&
  //       isset($property[$key]))
  //   {
  //     return $property[$key][0]['value'];
  //   }
  //   return false;
  // }

}
