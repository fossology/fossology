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

use DateTime;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Util\Object;

class ClearingEventProcessor extends Object
{

  /**
   * @param ClearingEvent[] $events
   * @return ClearingEvent[]
   */
  public function getClearingLicenses($events)
  {
    $result = array();

    foreach ($events as $event)
    {
      $shortName = $event->getLicenseShortName();

      $result[$shortName] = $event;
    }

    return $result;
  }

  /**
   * @param ClearingEvent[] $events
   * @return LicenseRef[]
   */
  public function getClearingLicenseRefs($events)
  {
    $result = array();

    foreach ($events as $event)
    {
      $clearingLicense = $event->getClearingLicense();
      $shortName = $clearingLicense->getShortName();

      $result[$shortName] = $clearingLicense->getLicenseRef();
    }

    return $result;
  }

  /**
   * @param DateTime|null $lastDecision
   * @param ClearingEvent[] $events
   * @return ClearingLicense[]
   */
  public function getClearingLicensesAt($lastDecision, $events)
  {
    $filteredEvents = $this->selectEventsUntilTime($events, $lastDecision);
    return $this->getClearingLicenses($filteredEvents);
  }

  /**
   * @param ClearingEvent[] $events
   * @param DateTime|null $lastDecisionDate
   * @return ClearingEvent[]
   */
  public function selectEventsUntilTime($events, $lastDecisionDate)
  {
    if ($lastDecisionDate !== null)
    {
      $filterEventsBefore = function (ClearingEvent $event) use ($lastDecisionDate)
      {
        return $event->getDateTime() <= $lastDecisionDate;
      };
      return array_filter($events, $filterEventsBefore);
    }
    return $events;
  }

  /**
   * @param ClearingEvent[] $events
   * @return ClearingEvent[]
   */
  public function filterEffectiveEvents($events)
  {
    $reducedEvents = array();
    foreach ($events as $event)
    {
      $licenseShortName = $event->getLicenseShortName();
      $reducedEvents[$licenseShortName] = $event;
    }
    return $reducedEvents;
  }

  /**
   * @param ClearingEvent[] $events
   * @return ClearingEvent[]
   */
  public function indexByLicenseShortName($events)
  {
    $values = array();
    foreach ($events as $license)
    {
      $values[$license->getLicenseShortName()] = $license;
    }
    return $values;
  }
}