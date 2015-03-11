/*
 * Heuristic copyright scanner
 */

#include "copyscan.hpp"
#include "regex.hpp"
#include <cctype>

const char copyrightType[] = "statement";

void AppendToList(list<match>& out, const string& s, int lineStart, int resultsStartInLine);

void hCopyrightScanner::ScanString(const string& s, list<match>& out) const
{
  // Find copyright statements in stream str
#define COPYSYM "(?:\\(c\\)|&copy;|\xA9|\xC2\xA9" "|\\$\xB8|\xE2\x92\xB8|\\$\xD2|\xE2\x93\x92" "|\\$\x9E|\xE2\x92\x9E)"

  rx::regex reg("\\bcopyright[[:space:]]+" COPYSYM
    "|\\bcopyright:"
    "|\\bcopyright[[:space:]]+[[:digit:]]{1,4}"
    "|\\bcopyright(?:ed)[[:space:]]+(?:by|of)"
    "|" COPYSYM "[[:space:]]+(?:[[:digit:]]{1,4}|[[:alpha:]]+)[[:space:],\\.-]"
    "|\\bcopyright[[:space:]]+[[:alpha:]]+:",
      rx::regex_constants::icase);
  rx::regex regWithExceptions("\\bcopyright[[:space:]]([[:alpha:]]+)[[:space:],\\.-]",
      rx::regex_constants::icase);

  
  rx::regex regExceptions(
    "licen[cs]es?|notices?|holders?|and|statements?",
      rx::regex_constants::icase);
  
  unsigned int filePos = 0;
  while (filePos < s.length())
  {
    // Read line
    string sLine; // TODO inefficient
    string::size_type newlinePos = s.find('\n', filePos);
    if (newlinePos == string::npos)
      // Last line
      newlinePos = s.length();
    sLine = s.substr(filePos, newlinePos - filePos);
    string::const_iterator begin = sLine.begin();
    string::const_iterator end = sLine.end();
    rx::smatch results;
    while (begin != end)
    {
      if (rx::regex_search(begin, end, results, reg))
      {
        AppendToList(out, sLine, filePos, results.position());
        break;
      }
      else if (rx::regex_search(begin, end, results, regWithExceptions))
      {
        // Check results[1] for exceptions
        if (results.size() > 1 && !rx::regex_match(results[1].first, results[1].second, regExceptions))
        {
          // No exceptions found
          AppendToList(out, sLine, filePos, results.position());
          break;
        }
        else
        {
          begin = results[0].second;
        }
      }
      else
        break;
    }
    filePos = newlinePos + 1;
  }
}

void AppendToList(list<match>& out, const string& s, int lineStart, int resultsStartInLine)
{
  // Append complete line, except if it is too long
  int lineLength = s.length();
  if (lineLength < 400)
  {
    // Find first alpha character
    int i = 0;
    for ( ; i < resultsStartInLine; i++)
    {
      if (isalpha(s[i]))
        break;
    }
    out.push_back(match(lineStart + i, lineStart + lineLength, copyrightType));
  }
  else if (lineLength - resultsStartInLine >= 400)
    out.push_back(match(lineStart + resultsStartInLine, lineStart + resultsStartInLine + 400, copyrightType));
  else
    out.push_back(match(lineStart + lineLength - 400, lineStart + lineLength, copyrightType));
}

