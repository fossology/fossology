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

#include "copyrightMatch.hpp"

CopyrightMatch::CopyrightMatch(std::string content, std::string type, unsigned int start, unsigned int length):
 content(content), start(start), length(length), type(type)
{}

CopyrightMatch::~CopyrightMatch(){};

unsigned CopyrightMatch::getStart() const
{
  return start;
}

unsigned CopyrightMatch::getLength() const
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

std::ostream& operator<<(std::ostream& os, const CopyrightMatch& match)
{
  unsigned start = match.getStart();
  unsigned end = start + match.getLength();

  os << "["
          << start << ":" << end << ":" << match.getType()
     << "] '" <<
              match.getContent()
       << "'";

  return os;
}

