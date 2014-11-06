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
use Fossology\Lib\Util\Object;

class ClearingEventProcessor extends Object
{

  /**
   *
   * @param ClearingEvent[] $events
   * @return ClearingEvent[][]
   */
  public function getCurrentClearings($events)
  {
    $addedLicenses = array();
    $removedLicenses = array();
    foreach ($events as $event)
    {
      $licenseShortName = $event->getLicenseShortName();

      if ($event->isRemoved())
      {
        $removedLicenses[$licenseShortName] = $event;
      } else
      {
        $addedLicenses[$licenseShortName] = $event;
      }
    }

    return array($addedLicenses, $removedLicenses);
  }

  /**
   * @param ClearingEvent[] $events
   * @param DateTime|null $lastDecisionDate
   * @return array
   */
  public function filterEventsByTime($events, $lastDecisionDate)
  {
    if ($lastDecisionDate !== null)
    {
      $filterEventsBefore = function (ClearingEvent $event) use ($lastDecisionDate)
      {
        return $event->getDateTime() > $lastDecisionDate;
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
    $addingEvents = array();
    $removingEvents = array();

    if ($events !== null)
    {
      foreach ($events as $event)
      {
        $licenseShortName = $event->getLicenseShortName();
        if ($event->isRemoved())
        {
          if (array_key_exists($licenseShortName, $addingEvents)) {
            unset($addingEvents[$licenseShortName]);
          } else {
            $removingEvents[$licenseShortName] = $event;
          }
        } else
        {
          if (array_key_exists($licenseShortName, $removingEvents)) {
            unset($removingEvents[$licenseShortName]);
          } else {
            $addingEvents[$licenseShortName] = $event;
          }
        }
      }
    }

    return $this->sortEventsInTime(array_merge(array_values($addingEvents), array_values($removingEvents)));
  }

  /**
   *
   * @param ClearingEvent[]
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