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

#include <stddef.h>
#include "copyrightMatch.hpp"

CopyrightMatch::CopyrightMatch(std::string content, std::string type, unsigned int start, unsigned int length) :
        content(content), start(start), length(length), type(type)
{
}

CopyrightMatch::CopyrightMatch(std::string content, std::string type, unsigned int start) :
        content(content), start(start), length(content.length()), type(type)
{
}

CopyrightMatch::~CopyrightMatch()
{
};

size_t CopyrightMatch::getStart() const
{
  return start;
}

size_t CopyrightMatch::getLength() const
{
  return length;
}


const std::string CopyrightMatch::getContent() const
{
  return content;
}


const std::string CopyrightMatch::getType() const
{
  return type;
}

bool operator ==(const CopyrightMatch& first, const CopyrightMatch& other)
{
  return (first.getContent() == other.getContent()) &&
          (first.getType() == other.getType()) &&
          (first.getStart() == other.getStart()) &&
          (first.getLength() == other.getLength());
}

std::ostream& operator <<(std::ostream& os, const CopyrightMatch& match)
{
  size_t start = match.getStart();
  size_t end = start + match.getLength();

  os << "["
          << start << ":" << end << ":" << match.getType()
          << "] '" <<
          match.getContent()
                  << "'";

  return os;
}

std::ostream& operator <<(std::ostream& os, const std::vector<CopyrightMatch>& matches)
{
  typedef std::vector<CopyrightMatch>::const_iterator cpm;
  for (cpm it = matches.begin(); it != matches.end(); ++it)
    os << "\t" << *it << std::endl;
  return os;
}


