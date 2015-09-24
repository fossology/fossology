/*
 * Copyright (C) 2014, Siemens AG
 * Author: Johannes Najjar, Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef REGCOPYRIGHT_H_
#define REGCOPYRIGHT_H_


namespace regURL
{
  const char* getRegex(const bool isVerbosityDebug);
  // const char* getRegex() { return regURL::getRegex(false); }
  const char* getType();
}


namespace regEmail
{
  const char* getRegex(const bool isVerbosityDebug);
  // const char* getRegex() { return regEmail::getRegex(false); }
  const char* getType();
}

namespace regAuthor
{
  const char* getRegex(const bool isVerbosityDebug);
  // const char* getRegex() { return regAuthor::getRegex(false); }
  const char* getType();
}

namespace regEcc
{
  const char* getRegex(const bool isVerbosityDebug);
  // const char* getRegex() { return regEcc::getRegex(false); }
  const char* getType();
}

#endif /* REGCOPYRIGHT_H_ */
