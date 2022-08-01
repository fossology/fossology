<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\LicenseRef;

interface LicenseClearing
{
  /** @return string */
  public function getEventType();
  /** @return boolean */
  public function isRemoved();
  /** @return LicenseRef */
  public function getLicenseRef();
  /** @return int */
  public function getLicenseId();
  /** @return string */
  public function getLicenseShortName();
  /** @return string */
  public function getLicenseFullName();
  /** @return int */
  public function getTimeStamp();
}