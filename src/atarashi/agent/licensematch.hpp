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

#ifndef ATARASHI_AGENT_LICENSE_MATCH_HPP
#define ATARASHI_AGENT_LICENSE_MATCH_HPP

#include <string>

using namespace std;

class LicenseMatch
{
public:
  LicenseMatch(string licenseName, unsigned percentage);
  ~LicenseMatch();

  const string getLicenseName() const;
  unsigned getPercentage() const;

private:
  string licenseName;
  unsigned percentage;
};

#endif // ATARASHI_AGENT_LICENSE_MATCH_HPP
