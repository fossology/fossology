/*
 * Copyright (C) 2014, Siemens AG
 * Author: Johannes Najjar, Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "regTypes.hpp"


const  std::string regCopyright::getType(){
  return std::string("statement");
};


const std::string regCopyright::getRegex() {
#define NAMESLIST "[[:alpha:]]+(([-, ]{1,3})[[:alpha:]]+)*"
#define DATESLIST "([[:digit:]]{4,4}(([[:punct:][:space:]]+)[[:digit:]]{4,4})*)?"
#define COPYR_SYM "\\(C\\)"
#define COPYR_TXT "copyright(s)?"
#define SPACES    "[[:space:]]+"

 return std::string(
  "("
    "(" COPYR_SYM SPACES COPYR_TXT "|" COPYR_TXT SPACES COPYR_SYM "|" COPYR_TXT ")"
    "[[:space:][:punct:]]*"
    "("
    "((and|hold|info|law|licen|message|notice|owner|state|string|tag|copy|permission|this|timestamp|@author)*)"
    ")?"
    "("
      "[[:space:][:punct:]]*"
      DATESLIST
    ")?"
    "("
      "[[:space:][:punct:]]*"
      NAMESLIST
    ")"
  ")|("
    "(all" SPACES "rights" SPACES "reserved)"
  ")|("
    "("
      "(author|contributor)s?"
      "|(written|contributed)" SPACES "by"
    ")"
    "[[:space:][:punct:]]*"
    NAMESLIST
  ")"
 );
};


const  std::string regURL::getType(){
  return std::string("url");
};

const std::string regURL::getRegex() {
 return std::string(
             "(?:(:?ht|f)tps?\\:\\/\\/[^\\s\\<]+[^\\<\\.\\,\\s])"
 );
};


const  std::string regEmail::getType(){
  return std::string("email");
};

const std::string regEmail::getRegex() {
 return std::string(
             "[\\<\\(]?([\\w\\-\\.\\+]{1,100}@[\\w\\-\\.\\+]{1,100}\\.[a-z]{1,4})[\\>\\)]?"
 );
};



const  std::string regIp::getType(){
  return std::string("ip");
};

const std::string regIp::getRegex() {
 return std::string(
   "("
    "(patent(ability|ed|ee|ing))"
    "|((US|EU)" SPACES "(PAT|patents))"
    "|(USPTO|PCT)"
    "|(invent(ion|or|ive))"
    "|(filed)"
    "|(innovation|infringement)"
    "|(intellectual" SPACES "property)"
    "|(prior" SPACES "art)"
    "|(field[-]of[-]use" SPACES "limitation)"
    "|(fair" SPACES "use)"
   ")"
   "[[:space:][:punct:]]+" // TODO what's the purpose of this???
   "[[:alpha:]]*"
   "[[:punct:]]*"
   "[[:alpha:]]*"
   "[[:space:]]*"
   "[[:print:]]{0,60}"     // \TODO
 );
};


const  std::string regEcc::getType(){
  return std::string("ecc");
};

const std::string regEcc::getRegex() {
 return std::string(
   "("
    "eccn|tsu|ecc|ccl|wco"
    "|(export" SPACES "control)"
    "|(customs)"
    "|(foreign" SPACES "trade(" SPACES "regulations)?)"
    "|(commerce" SPACES "control)"
    "|(country" SPACES "of" SPACES "origin)"
    "|(export" SPACES "administration)"
    "|((k|c)rypt)"
    "|(information" SPACES "security)"
    "|(encryption)"
    "|(nuclear|surveillance|military|defense|marine|avionics|laser)"
    "|(propulsion" SPACES "systems)"
    "|(space" SPACES "vehicles)"
    "|(dual" SPACES "use)"
   ")"
   "[[:space:][:punct:]]+" // TODO what's the purpose of this???
   "[[:alpha:]]*"
   "[[:punct:]]*"
   "[[:alpha:]]*"
   "[[:print:]]{0,60}"     // \TODO
 );
};
