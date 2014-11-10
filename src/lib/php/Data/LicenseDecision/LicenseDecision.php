<?php
/*
Copyright (C) 2014, Siemens AG

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
namespace Fossology\Lib\Data\LicenseDecision;

use DateTime;
use Fossology\Lib\Data\LicenseRef;

interface LicenseDecision
{
  /**
   * @return DateTime
   */
  public function getDateTime();

  /**
   * @return string
   */
  public function getEventType();

  /**
   * @return boolean
   */
  public function isRemoved();

  /**
   * @return LicenseRef
   */
  public function getLicenseRef();

  /**
   * @return int
   */
  public function getLicenseId();

  /**
   * @return string
   */
  public function getLicenseShortName();

  /**
   * @return string
   */
  public function getLicenseFullName();

  /**
   * @return string
   */
  public function getComment();

  /**
   * @return string
   */
  public function getReportinfo();

   /**
   * @return int
   */
  public function getEventId();
}