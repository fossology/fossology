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
   * @param DateTime|null $lastDecisionDate
   * @return array
   */
  public function filterEventsAfterTime($events, $lastDecisionDate)
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
   * @param ClearingEvent[] $orderedEvents ordered by data_added
   * @return LicenseRef[][]
   */
  public function getFilteredState($orderedEvents)
  {
    $filteredEvents = $this->filterEffectiveEvents($orderedEvents);
    return $this->getCurrentClearingState($filteredEvents);
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

  /**
   * @param ClearingEvent[] $orderedEvents
   * @return ClearingEvent[]
   */
  public function filterEffectiveEvents($orderedEvents)
  {
    $unorderedEvents = array();
    foreach ($orderedEvents as $event)
    {
      $licenseShortName = $event->getLicenseShortName();
      $unorderedEvents[$licenseShortName] = $event;
    }
    return $this->sortEventsInTime($unorderedEvents);
  }

  /**
   * @param ClearingEvent[] $events
   * @param LicenseRef[] $detectedLicenses
   * @return string[]
   */
  public function getUnhandledLicenses($events, $detectedLicenses)
  {
    foreach ($events as $event)
    {
      $licenseShortName = $event->getLicenseShortName();
      if (array_key_exists($licenseShortName, $detectedLicenses))
      {
        unset($detectedLicenses[$licenseShortName]);
      }
    }

    return $detectedLicenses;
  }

  /**
   *
   * @param ClearingEvent[] $events
   * @return LicenseRef[][]
   */
  public function getCurrentClearingState($events)
  {
    $addedLicenses = array();
    $removedLicenses = array();
    foreach ($events as $event)
    {
      $licenseShortName = $event->getLicenseShortName();

      if ($event->isRemoved())
      {
        $removedLicenses[$licenseShortName] = $event->getLicenseRef();
      } else
      {
        $addedLicenses[$licenseShortName] = $event->getLicenseRef();
      }
    }

    return array($addedLicenses, $removedLicenses);
  }

  /**
   * @param ClearingEvent[] $events
   * @return LicenseRef[]
   */
  public function getState($events)
  {
    $selection = array();

    foreach ($events as $event)
    {
      $shortName = $event->getLicenseShortName();

      if ($event->isRemoved())
      {
        unset($selection[$shortName]);
      } else
      {
        $selection[$shortName] = $event->getLicenseRef();
      }
    }

    return $selection;
  }


  /**
   *
   * @param ClearingEvent []
   * @return ClearingEvent[]
   */
  protected function sortEventsInTime($events)
  {
    usort($events, function (ClearingEvent $event1, ClearingEvent $event2)
    {
      return $event1->getDateTime() > $event2->getDateTime();
    });
    return $events;
  }


}