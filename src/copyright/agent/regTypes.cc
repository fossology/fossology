/*
 * regCopyright.cpp
 *
 *  Created on: Sep 24, 2014
 *      Author: ”J. Najjar”
 */

#include "regTypes.hpp"


const  std::string regCopyright::getType(){
  return std::string("statement");
};


const std::string regCopyright::getRegex() {
 return std::string(
  "("
  "("
  "(Copyright|(\\(C\\) Copyright([[:punct:]]?))) "
  "("
  "((and|hold|info|law|licen|message|notice|owner|state|string|tag|copy|permission|this|timestamp|@author)*)"
  "|"
  "([[:print:]]{0,10}|[[:print:]]*)" // TODO this is equivalent to [[:print:]]*
  ")"
  "("
  "([[:digit:]]{4,4}([[:punct:]]|[[:space:]])[[:digit:]]{4,4})+ |[[:digit:]]{4,4}"
  ")"
  "(([[:space:]]|[[:punct:]]))" // TODO wth do we match all this junk?
  "([[:print:]]*)" // TODO wth do we match all this junk?
  ")|("
  "Copyright([[:punct:]]*) \\(C\\) "
  "("
  "((and|hold|info|law|licen|message|notice|owner|state|string|tag|copy|permission|this|timestamp)*)"
  "|"
  "[[:print:]]*" // TODO this matches everything and overrides the previous ???
  ")"
  "("
  "([[:digit:]]{4,4}([[:punct:]]|[[:space:]])[[:digit:]]{4,4})+ |[[:digit:]]{4,4}"
  ")"
  "(([[:space:]]|[[:punct:]]))"
  "([[:print:]]*)"
  ")|("
  "(\\(C\\)) ([[:digit:]]{4,4}[[:punct:]]*[[:digit:]]{4,4})([[:print:]]){0,60}"
  ")|("
  "Copyrights [[:blank:]]*[a-zA-Z]([[:print:]]{0,60})"
  ")|("
  "(all[[:space:]]*rights[[:space:]]*reserved)"
  ")|("
  "(author|authors)[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*[[:space:]]*([[:print:]]{0,60})|(contributors|contributor)[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*[[:space:]]*([[:print:]]{0,60})|written[[:space:]]*by[[:space:]|[:punct:]]*([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60})|contributed[[:space:]]*by[[:space:]|[:punct:]]*([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60})"
  ")"
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
