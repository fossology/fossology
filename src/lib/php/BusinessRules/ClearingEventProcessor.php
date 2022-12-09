<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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

    foreach ($events as $event) {
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
    foreach ($events as $event) {
      $licenseId = $event->getLicenseId();
      $reducedEvents[$licenseId] = $event;
    }
    return $reducedEvents;
  }
}
