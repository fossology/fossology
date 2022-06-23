/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "licensematch.hpp"

LicenseMatch::LicenseMatch(string licenseName, unsigned percentage) :
  licenseName(licenseName),
  percentage(percentage)
{
}

LicenseMatch::~LicenseMatch()
{
}

const string LicenseMatch::getLicenseName() const
{
  return licenseName;
}

unsigned LicenseMatch::getPercentage() const
{
  return percentage;
}
