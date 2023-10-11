<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * timer
 *
 * general class to determine the elapse time between two times in
 * time() format.
 *
 * @version "$Id: $"
 *
 * Created on Sep 19, 2008
 */

 /**
  * timer
  *
  * Determine the elapse time between two times in time() format.
  *
  * Usage: $t = new timer();
  *
  * To determine elase time:
  * $timePhrase = $t->timeAgo($t->getStartTime);
  *
  */
class timer
{
  public $startTime;

  public function __construct()
  {
    $this->startTime = time();
  }
  public function getStartTime()
  {
    return ($this->startTime);
  }
  /**
   * TimeAgo($timestamp)
   *
   * Return the text of the elapsed time between the timestamp and
   * 'now'.
   *
   * @param string $timestamp in time() format.
   * @return string $text the elapsed time as a phrase up to decades
   * ago. E.g. 30 seconds or 1 day 2 hours 30 seconds.
   *
   * This routine was taken from the php web site.
   */
  public static function TimeAgo($timestamp)
  {
    // Store the current time
    $current_time = time();

    // Determine the difference, between the time now and the timestamp
    $difference = $current_time - $timestamp;

    // Set the periods of time
    $periods = array (
      "second",
      "minute",
      "hour",
      "day",
      "week",
      "month",
      "year",
      "decade"
    );

    // Set the number of seconds per period
    $lengths = array (
      1,
      60,
      3600,
      86400,
      604800,
      2630880,
      31570560,
      315705600
    );

    // Determine which period we should use, based on the number of seconds lapsed.
    // If the difference divided by the seconds is more than 1, we use that. Eg 1 year / 1 decade = 0.1, so we move on
    // Go from decades backwards to seconds
    for ($val = sizeof($lengths) - 1;($val >= 0) && (($number = $difference / $lengths[$val]) <= 1); $val--);

    // Ensure the script has found a match
    if ($val < 0)
      $val = 0;

    // Determine the minor value, to recurse through
    $new_time = $current_time - ($difference % $lengths[$val]);

    // Set the current value to be floored
    $number = floor($number);

    // If required create a plural
    if ($number != 1)
      $periods[$val] .= "s";

    // Return text
    $text = sprintf("%d %s ", $number, $periods[$val]);

    // Ensure there is still something to recurse through, and we have not found 1 minute and 0 seconds.
    if (($val >= 1) && (($current_time - $new_time) > 0))
    {
      $text .= self :: TimeAgo($new_time);
    }
    return $text;
  }
}
