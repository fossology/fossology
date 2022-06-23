/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "match.hpp"

/**
 * @brief constructor for match class intended to store copyright/holder information
 * @param matchName content for copyright/holder
 * @param startPosition start byte for matched_text
 * @param length  no. of bytes matched
 */
Match::Match(string matchName, string type, unsigned startPosition, unsigned length)
    : matchName(matchName),type(type), startPosition(startPosition), length(length) {}

/**
 * @brief constructor for match class intended to store license information
 * @param matchName spdx key for license
 * @param percentage  score for license
 * @param licenseFullName full name of license
 * @param textUrl reference text url for license
 * @param startPosition start byte for matched_text
 * @param length  no. of bytes matched
 */
Match::Match(string matchName, int percentage, string licenseFullName,
             string textUrl, unsigned startPosition, unsigned length)
    : matchName(matchName), percentage(percentage),
      licenseFullName(licenseFullName), textUrl(textUrl),
      startPosition(startPosition), length(length) {}

/**
 * @brief constructor for match class intended to store No license information
 * @param matchName spdx key for license
 */
Match::Match(string matchName)
    : matchName(matchName) {}
    
/**
 * Default destructor for the match class
 */
Match::~Match() {}

////// Getters //////
/**
 * @brief get the match name
 * @return  string  matchName
 */
const string Match::getMatchName() const { return matchName; }

/**
 * @brief get the match type
 * @return string  type
 */
const string Match::getType() const { return type; }
/**
 * @brief get the percent match
 * @return integer  percentage
 */
int Match::getPercentage() const { return percentage; }

/**
 * @brief get the license full name
 * @return  string  full name of matched license
 */
const string Match::getLicenseFullName() const { return licenseFullName; }

/**
 * @brief get the reference text URL
 * @return  string  reference text URL
 */
const string Match::getTextUrl() const { return textUrl; }

/**
 * @brief get the start byte of the matched text
 * @return  unsigned  integer start position(byte)
 */
unsigned Match::getStartPosition() const { return startPosition; }

/**
 * @brief get the match text length
 * @return  unsigned  integer no. of byte matched
 */
unsigned Match::getLength() const { return length; }
