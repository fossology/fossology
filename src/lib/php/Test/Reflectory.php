<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Steffen Weber
 SPDX-License-Identifier: GPL-2.0-only
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
