/*
 SPDX-FileCopyrightText: © 2024 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef FOSSOLOGY_COMPATIBILITYSTATUS_HPP
#define FOSSOLOGY_COMPATIBILITYSTATUS_HPP

/**
 * Enum to check the status of compatibility between licenses
 */
enum CompatibilityStatus
{
  COMPATIBLE,    ///< Licenses are compatible
  NOTCOMPATIBLE, ///< Licenses are not compatible
  UNKNOWN,       ///< No rule found
  EXPLICIT_UNKNOWN ///< Explicitly marked as unknown (e.g. Check Dependency)
};

#endif // FOSSOLOGY_COMPATIBILITYSTATUS_HPP
