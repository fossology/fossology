/*
 * Copyright (C) 2019, Siemens AG
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

#ifndef SRC_OJO_AGENT_OJOMATCH_HPP_
#define SRC_OJO_AGENT_OJOMATCH_HPP_

#include <string>

/**
 * @struct ojomatch
 * @brief Store the results of a regex match
 */
struct ojomatch
{
  /**
   * @var long int start
   * Start position of match
   * @var long int end
   * End position of match
   * @var long int len
   * Length of match
   * @var long int license_fk
   * License ref of the match if found
   */
  long int start, end, len, license_fk;

  /**
   * @var
   * Matched string
   */
  std::string content;
  /**
   * Constructor for ojomatch structure
   * @param s Start of match
   * @param e End of match
   * @param l Length of match
   * @param c Content of match
   */
  ojomatch(const long int s, const long int e, const long int l,
    const std::string c) :
    start(s), end(e), len(l), content(c)
  {
    license_fk = -1;
  }

  bool operator==(const std::string& matchcontent) const
  {
    if(this->content.compare(matchcontent) == 0)
    {
      return true;
    }
    else
    {
      return false;
    }
  }
};

#endif /* SRC_OJO_AGENT_OJOMATCH_HPP_ */
