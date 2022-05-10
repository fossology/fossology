<?php
/*
Copyright (C) 2022, Siemens AG

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

namespace Fossology\Lib\Data\Package;

class ComponentType
{
  /**
   * @var int PURL
   * Component Type purl
   */
  const PURL = 0;
  /**
   * @var int MAVEN
   * Component Type maven
   */
  const MAVEN = 1;
  /**
   * @var int NUGET
   * Component Type nuget
   */
  const NUGET = 2;
  /**
   * @var int NPM
   * Component Type npm
   */
  const NPM = 3;
  /**
   * @var int PYPI
   * Component Type pypi
   */
  const PYPI = 4;
  /**
   * @var int PACKAGEURL
   * Component Type package-url
   */
  const PACKAGEURL = 5;
  /**
   * @var array TYPE_MAP
   * Corresponding type name for id
   */
  const TYPE_MAP = [
    self::PURL => 'purl',
    self::MAVEN => 'maven',
    self::NUGET => 'nuget',
    self::NPM => 'npm',
    self::PYPI => 'pypi',
    self::PACKAGEURL => 'package-url'
  ];
}
