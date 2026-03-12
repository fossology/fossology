<?php
// SPDX-FileCopyrightText: © 2024 Fossology contributors
//
// SPDX-License-Identifier: GPL-2.0-only

/**
 * @param int|string $existingFlag Current rf_flag value in DB.
 * @param int|string $incomingFlag rf_flag from import (may be empty).
 * @param string $existingText Current rf_text in DB.
 * @param string $incomingText rf_text from import.
 *
 * @return array|null ['text' => string, 'rf_flag' => int] when update needed.
 */
function buildLicenseTextUpdate($existingFlag, $incomingFlag, $existingText,
  $incomingText)
{
  $incomingText = (string)$incomingText;
  $existingText = (string)$existingText;

  $shouldUpdate = ((int)$existingFlag === 1)
    && $incomingText !== ''
    && stristr($incomingText, 'License by Nomos') === false
    && $existingText !== $incomingText;

  if (! $shouldUpdate) {
    return null;
  }

  $flagValue = ($incomingFlag === '' || $incomingFlag === null)
    ? 1
    : (int)$incomingFlag;

  return [
    'text' => $incomingText,
    'rf_flag' => $flagValue,
  ];
}
