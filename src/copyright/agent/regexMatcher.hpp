/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef REGEXMATCHER_H
#define REGEXMATCHER_H

#include "matcher.hpp"
#include <iostream>
#include "regex.hpp"
#include <string>

class RegexMatcher : public virtual Matcher {
public:
    RegexMatcher(const std::string  type,const std::string  pattern, int regexIndex = 0);
    virtual std::vector <CopyrightMatch> match(const std::string content) const;
    virtual ~RegexMatcher();
private:
    int regexIndex;
    rx::regex matchingRegex;
};

#endif // REGEXMATCHER_H
