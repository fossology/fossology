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



const  std::string regIp::getType(){
  return std::string("ip");
};

const std::string regIp::getRegex() {
 return std::string(
   "("
   "patent[ability|ed|ee|ing]*"
   "|(US|EU)[[:space:]]*(PAT|patents)"
   "|(USPTO|PCT)"
   "|invent[ion|or|ive]"
   "|filed"
   "|(innovation|infringement)"
   "|intellectual[[:space:]]*property"
   "|prior[[:space:]]*art"
   "|field[-]of[-]use[[:space:]]*limitation"
   "|fair[[:space:]]*use"
   ")"
   "([[:space:]|[:punct:]])+"
   "[[:alpha:]]*"
   "[[:punct:]]*"
   "[[:alpha:]]*"
   "[[:space:]]*"
   "[[:print:]]{0,60}"
 );
};


const  std::string regEcc::getType(){
  return std::string("ecc");
};

const std::string regEcc::getRegex() {
 return std::string(
   //TODO copy the correct one: see TODO below
   "(eccn|tsu|ecc)"
   "([[:space:]|[:punct:]])+"
   "[[:alpha:]]*"
   "[[:punct:]]*"
   "[[:alpha:]]*"
   "[[:space:]]*"
   "[[:print:]]{0,60}"

   //TODO this is the original but has a syntax error. It can be made as easy as the ip one
   //"(((?#Regular expression to identify 'eccn,tsu,ecc,ccl,wco' in the files)(eccn|tsu|ecc|ccl|wco)[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|(?#Regular expression to identify 'export control'keywords in the files)(export[[:space:]]+control([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'Customs'keyword in the files)customs[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'foreign regulations'keywords in the files)foreign[[:space:]]+trade[[:space:]]+regulations[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'foreign trade' keyword in the files)foreign[[:space:]]+trade[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'Commerce control' keywords in the files)commerce[[:space:]]+control[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'Country of origin'in the files)country[[:space:]]+of[[:space:]]+origin[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'export administration'in the files)export[[:space:]]+administration[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'krypt/crypt' keyword in the files)(krypt|crypt)[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'Information security' keyword in the files)information[[:space]]+security[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular exoression to identify 'Encryption'keyword in the files)encryption[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'nuclear,surveillance,military,defense,marine,avionics,laser'in the input files)(nuclear|surveillance|military|defense|marine|avionics|laser)[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|((?#Regular expression to identify 'Propulsion system' in the files)propulsion[[:space:]]+systems[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|(space[[:space:]]+vehicles[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60}))|(dual[[:space:]]+use[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60})))"
 );
};
