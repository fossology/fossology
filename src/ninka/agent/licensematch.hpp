/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef NINKA_AGENT_LICENSE_MATCH_HPP
#define NINKA_AGENT_LICENSE_MATCH_HPP

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

#endif // NINKA_AGENT_LICENSE_MATCH_HPP
