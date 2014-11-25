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
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Util\Object;

class ClearingEventProcessor extends Object
{

  /**
   * @param ClearingEvent[] $events
   * @return ClearingLicense[][]
   */
  public function getState($events)
  {
    $selection = array();
    $total = array();

    foreach ($events as $event)
    {
      $clearingLicense = $event->getClearingLicense();
      $shortName = $clearingLicense->getShortName();

      if ($event->isRemoved())
      {
        unset($selection[$shortName]);
      } else
      {
        $selection[$shortName] = $clearingLicense;
      }
      $total[$shortName] = $clearingLicense;
    }

    return array($selection, $total);
  }

  /**
   * @param DateTime|null $lastDecision
   * @param ClearingEvent[] $events
   * @return ClearingLicense[][]
   */
  public function getStateAt($lastDecision, $events)
  {
    $filteredEvents = $this->selectEventsUntilTime($events, $lastDecision);
    return $this->getState($filteredEvents);
  }

  /**
   * @param ClearingLicense[] $previousSelection
   * @param ClearingLicense[] $currentSelection
   * @return ClearingLicense[][]
   */
  public function getStateChanges($previousSelection, $currentSelection)
  {
    return array(
        array_diff($currentSelection, $previousSelection),
        array_diff($previousSelection, $currentSelection)
    );
  }

  /**
   * @param ClearingEvent[] $events
   * @param DateTime|null $lastDecisionDate
   * @return array
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