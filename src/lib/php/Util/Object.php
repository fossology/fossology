<?php

namespace Fossology\Lib\Util;

class Object
{
  /**
   * @return string representing the fully qualified classname of the object for which the method is called
   */
  public static function classname()
  {
    return get_called_class();
  }
}