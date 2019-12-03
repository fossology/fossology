<?php
/*
Copyright (C) 2015, Siemens AG
Author: Steffen Weber

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

namespace Fossology\Lib\Test;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

// setup autoloading
require_once(dirname(dirname(dirname(__DIR__))) . "/vendor/autoload.php");


class Reflectory
{
  public static function invokeObjectsMethodnameWith($object, $fun, array $args=array())
  {
    $reflection = new ReflectionClass($object);
    /** @var ReflectionMethod */
    $method = $reflection->getMethod($fun);
    $method->setAccessible(true);
    return $method->invokeArgs($object,$args);
  }

  public static function getObjectsProperty($object, $prop)
  {
    $reflection = new ReflectionClass($object);
    /** @var ReflectionProperty */
    $property = $reflection->getProperty($prop);
    $property->setAccessible(true);
    return $property->getValue($object);
  }

  public static function setObjectsProperty($object, $prop, $value)
  {
    $reflection = new ReflectionClass($object);
    /** @var ReflectionProperty */
    $property = $reflection->getProperty($prop);
    $property->setAccessible(true);
    $property->setValue($object, $value);
  }
}
