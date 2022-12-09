/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef SCANCODE_AGENT_MATCH_HPP
#define SCANCODE_AGENT_MATCH_HPP

#include <string>

using namespace std;

/**
 * class to store license/copyright information for an upload 
 * scanned with scancode-toolkit
 */
class Match {
public:
  Match(string matchName, string type, unsigned startPosition, unsigned length);
  Match(string matchName, int percentage, string licenseFullName,
        string textUrl, unsigned startPosition, unsigned length);
  Match(string matchName);
  ~Match();
  const string getType() const;
  const string getMatchName() const;
  int getPercentage() const;
  const string getLicenseFullName() const;
  const string getTextUrl() const;
  unsigned getStartPosition() const;
  unsigned getLength() const;

private:

  /**
   * value/content matched
   * spdx short name incase of licenses
   * copyright content incase of copyright
   * copyright holder
   */
  string matchName;

  /**
   * scan type
   */
  string type;

  /**
   * score of a rule to matched with the output licenes
   */
  int percentage;

  /**
   * Full name of the licenses scanned
   */
  string licenseFullName;

  /**
   * reference text URL
   */
  string textUrl;

  /**
   * start byte of matched text
   */
  unsigned startPosition;

  /**
   * no of bytes matched 
   */
  unsigned length;
};

#endif // SCANCODE_AGENT_MATCH_HPP