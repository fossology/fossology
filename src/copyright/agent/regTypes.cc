/*
 * Copyright (C) 2014-2015, Siemens AG
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

#include "regTypes.hpp"

#include <iostream>

#include <map>
#include "regexConfProvider.hpp"

// #include <string.h>

#include <iostream>
#include <cstring>
#include <string>

const char* regAuthor::getType(){
  return "author";
};

const char* regAuthor::getRegex(const bool isVerbosityDebug) {
  std::string key("AUTHOR");
  RegexConfProvider::instance(isVerbosityDebug)->maybeLoad("copyright");
  return RegexConfProvider::instance()->getRegexValue("copyright",key);
};

const char* regURL::getType(){
  return "url";
};

const char* regURL::getRegex(const bool isVerbosityDebug) {
  std::string key("URL");
  RegexConfProvider::instance(isVerbosityDebug)->maybeLoad("copyright");
  return RegexConfProvider::instance()->getRegexValue("copyright",key);
};


const char* regEmail::getType(){
  return "email";
};

const char* regEmail::getRegex(const bool isVerbosityDebug) {
  std::string key("EMAILRGX");
  RegexConfProvider::instance(isVerbosityDebug)->maybeLoad("copyright");
  return RegexConfProvider::instance()->getRegexValue("copyright",key);
};





const  char* regEcc::getType(){
  return "ecc";
};

const char* regEcc::getRegex(const bool isVerbosityDebug) {
  std::string key("ECC");
  RegexConfProvider::instance(isVerbosityDebug)->maybeLoad("ecc");
  return RegexConfProvider::instance()->getRegexValue("ecc",key);
};

