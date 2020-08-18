<?php
/*
 * Copyright (C) 2020, Siemens AG
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */
/**
 * @namespace Fossology::Lib::Data::Spasht
 * This namespace holds data structures used by spasht agent
 */
namespace Fossology\Lib\Data\Spasht;

/**
 * @class Coordinate
 * Holds the coordinates of a pakcage in ClearlyDefined
 */
class Coordinate
{
  /**
   * @var string $revision
   * Revision of package
   */
  private $revision;

  /**
   * @var string $type
   * Type of package
   */
  private $type;

  /**
   * @var string $name
   * Name of package
   */
  private $name;

  /**
   * @var string $provider
   * Provider of package
   */
  private $provider;

  /**
   * @var string $namespace
   * Namespace of the package
   */
  private $namespace;

  /**
   * Set the object based on array returned by API
   * @param array $obj Array containing the data
   * @throws \InvalidArgumentException If the input obj does not contain
   *         required fields
   */
  public function __construct($obj)
  {
    if (count(array_diff(['type', 'provider', 'name'], array_keys($obj))) == 0) {
      $this->type = $obj['type'];
      $this->provider = $obj['provider'];
      $this->name = $obj['name'];
    } else {
      throw new \InvalidArgumentException('type, provider and name are required');
    }
    if (array_key_exists('namespace', $obj) && !empty($obj['namespace'])) {
      $this->namespace = $obj['namespace'];
    } else {
      $this->namespace = '-';
    }
    if (array_key_exists('revision', $obj) && !empty($obj['revision'])) {
      $this->revision = $obj['revision'];
    } else {
      $this->revision = '';
    }
  }

  /**
   * Generate API understandable URI
   * @return string
   */
  public function generateUrlString()
  {
    return "$this->type/$this->provider/$this->namespace/$this->name/" .
      $this->revision;
  }

  /**
   * @return string
   */
  public function getRevision()
  {
    return $this->revision;
  }

  /**
   * @return string
   */
  public function getType()
  {
    return $this->type;
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getProvider()
  {
    return $this->provider;
  }

  /**
   * @return string
   */
  public function getNamespace()
  {
    return $this->namespace;
  }

  /**
   * Helper function to generate Coordinate from string
   * @param string $coordinate Coordinates returned from definintions API
   * @throws \InvalidArgumentException If the coordinate string is not in valid
   *         format
   * @return Coordinate
   */
  public static function generateFromString($coordinate)
  {
    $parts = explode("/", $coordinate);
    if (count($parts) != 5) {
      throw new \InvalidArgumentException("Invalid coordinate string");
    }
    $obj = [
      'type' => $parts[0],
      'provider' => $parts[1],
      'namespace' => $parts[2],
      'name' => $parts[3],
      'revision' => $parts[4]
    ];
    return new Coordinate($obj);
  }
}
