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

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\LicenseRef;

/**
 * @class ClearingEventProcessor
 * @brief Functions to process clearing events
 */
class ClearingEventProcessor
{

  /**
   * @brief Get license refs from clearing events
   * @param ClearingEvent[] $events Clearing events to extract license ref from
   * @return LicenseRef[]
   */
  public function getClearingLicenseRefs($events)
  {
    $result = array();

    foreach ($events as $event)
    {
      $clearingLicense = $event->getClearingLicense();
      $licenseId = $clearingLicense->getLicenseId();

      $result[$licenseId] = $clearingLicense->getLicenseRef();
    }

    return $result;
  }

  /**
   * @brief Filter events based on license id
   * @param ClearingEvent[] $events Clearing events to be filtered
   * @return ClearingEvent[] Clearing events keyed on license id
   */
  public function filterEffectiveEvents($events)
  {
    $reducedEvents = array();
    foreach ($events as $event)
    {
      $licenseId = $event->getLicenseId();
      $reducedEvents[$licenseId] = $event;
    }
    return $reducedEvents;
  }

}
