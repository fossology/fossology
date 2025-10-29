/*
 * Copyright (C) 2019-2020, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
