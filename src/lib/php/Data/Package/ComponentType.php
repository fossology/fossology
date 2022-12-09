<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
    self::PACKAGEURL => 'package-url',
    '' => ''
  ];
}
