/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/

#include "DiffClassifier.hpp"

#include <cctype>
#include <cstdio>
#include <regex>
#include <set>
#include <sstream>
#include <sys/wait.h>

namespace
{

/**
 * @brief Shell-escape a string for use in a popen() command.
 */
std::string shellEscape(const std::string& s)
{
  std::string r = "'";
  for (char c : s)
    r += (c == '\'') ? std::string("'\\''") : std::string(1, c);
  r += "'";
  return r;
}

/**
 * @brief Lower-case and collapse whitespace for comparing lines, keeping all
 *        characters (including years and holder names) so any copyright
 *        statement difference is detected.
 */
std::string normalize(const std::string& s)
{
  std::string out;
  bool prevSpace = false;
  for (unsigned char c : s)
  {
    if (isspace(c))
    {
      if (!prevSpace && !out.empty()) out += ' ';
      prevSpace = true;
    }
    else
    {
      out += static_cast<char>(tolower(c));
      prevSpace = false;
    }
  }
  size_t end = out.find_last_not_of(' ');
  return (end == std::string::npos) ? "" : out.substr(0, end + 1);
}

bool isCopyrightLine(const std::string& s)
{
  // Copyright phrase set mirroring src/copyright/agent/copyright.conf (COPYSYM
  // + REG_COPYRIGHT): "copyright"/"copyrighted"/"copyrights" (substring match),
  // "copr.", "(c)", "&copy;", the UTF-8 copyright sign (U+00A9) and the
  // circled/parenthesized-c symbols U+24B8, U+24D2 and U+249E.  The bare
  // latin-1 byte \xA9 is intentionally not matched: std::regex lacks the
  // lookbehind the copyright agent uses to exclude "é" (0xC3 0xA9).
  static const std::regex copyrightRe(
    "(copyright|copr\\.?|\\(c\\)|&copy;|\\xC2\\xA9|"
    "\\xE2\\x92\\xB8|\\xE2\\x93\\x92|\\xE2\\x92\\x9E)",
    std::regex_constants::icase);
  return std::regex_search(s, copyrightRe);
}

bool hasLicenseTextHint(const std::string& s)
{
  static const std::regex hintRe(
    "(license|licence|spdx|copyright|all rights reserved)",
    std::regex_constants::icase);
  return std::regex_search(s, hintRe);
}

bool hasCommentBlocks(const NirjasOutput& comments)
{
  return !comments.singleLineComments.empty()
      || !comments.contSingleLineComments.empty()
      || !comments.multiLineComments.empty();
}

} // namespace

std::vector<DiffHunk> parseUnifiedDiff(const std::string& diffOutput)
{
  std::vector<DiffHunk> result;
  std::istringstream ss(diffOutput);
  std::string line;

  DiffHunk current;
  bool inHunk = false;
  int oldLine = 0;
  int newLine = 0;

  while (std::getline(ss, line))
  {
    if (!line.empty() && line.back() == '\r')
      line.pop_back();

    if (line.rfind("@@ ", 0) == 0)
    {
      if (inHunk) result.push_back(current);
      current  = DiffHunk();
      inHunk   = true;
      oldLine  = 0;
      newLine  = 0;

      // @@ -oldStart[,oldCount] +newStart[,newCount] @@
      size_t p1 = line.find("@@ ") + 3;
      size_t p2 = line.find('+', p1);
      size_t p3 = line.find(" @@", p2);
      if (p2 == std::string::npos || p3 == std::string::npos)
        continue;
      std::string oldRange = line.substr(p1, p2 - p1 - 1);
      std::string newRange = line.substr(p2 + 1, p3 - p2 - 1);
      // The leading '-' in "-oldStart[,oldCount]" is the diff range marker,
      // not a sign.
      if (!oldRange.empty() && oldRange[0] == '-')
        oldRange.erase(0, 1);
      std::istringstream(oldRange) >> oldLine;
      std::istringstream(newRange) >> newLine;
      continue;
    }

    if (!inHunk)
      continue; // header lines ("--- ...", "+++ ...", "Only in ...", ...)

    if (line.empty() || line[0] == '\\')
      continue; // "\ No newline at end of file"

    char c = line[0];
    std::string content = line.substr(1);
    if (c == '-')
    {
      current.removed.push_back({oldLine, content});
      ++oldLine;
    }
    else if (c == '+')
    {
      current.added.push_back({newLine, content});
      ++newLine;
    }
    else if (c == ' ')
    {
      ++oldLine;
      ++newLine;
    }
    // Any other prefix is ignored.
  }

  if (inHunk) result.push_back(current);
  return result;
}

DiffResult unifiedDiff(const std::string& oldPath, const std::string& newPath)
{
  DiffResult result;
  result.status = DiffStatus::Ok;

  if (oldPath.empty() || newPath.empty())
  {
    result.status = DiffStatus::Error;
    return result;
  }

  std::string cmd = "LC_ALL=C diff -u -- " + shellEscape(oldPath) + " "
                  + shellEscape(newPath) + " 2>/dev/null";
  FILE* pipe = popen(cmd.c_str(), "r");
  if (!pipe)
  {
    result.status = DiffStatus::Error;
    return result;
  }

  std::string out;
  char buf[4096];
  while (fgets(buf, sizeof(buf), pipe))
    out += buf;
  int status = pclose(pipe);

  if (WIFEXITED(status) && WEXITSTATUS(status) == 2)
  {
    result.status = DiffStatus::Error;
    return result;
  }

  // Binary files are reported with a single "Binary files a and b differ"
  // line and no hunks; treat as a diff error instead of "identical".
  if (out.find("Binary files") != std::string::npos)
  {
    result.status = DiffStatus::Error;
    return result;
  }

  result.hunks = parseUnifiedDiff(out);
  return result;
}

bool isLineInComments(int lineNo, const NirjasOutput& comments)
{
  if (lineNo <= 0) return false;
  for (const auto& cb : comments.singleLineComments)
    if (lineNo >= cb.startLine && lineNo <= cb.endLine) return true;
  for (const auto& cb : comments.contSingleLineComments)
    if (lineNo >= cb.startLine && lineNo <= cb.endLine) return true;
  for (const auto& cb : comments.multiLineComments)
    if (lineNo >= cb.startLine && lineNo <= cb.endLine) return true;
  return false;
}

bool isCopyrightChange(const std::vector<ChangedLine>& removed,
                       const std::vector<ChangedLine>& added)
{
  std::set<std::string> rLines, aLines;
  for (const auto& cl : removed)
    if (isCopyrightLine(cl.text))
      rLines.insert(normalize(cl.text));
  for (const auto& cl : added)
    if (isCopyrightLine(cl.text))
      aLines.insert(normalize(cl.text));
  return rLines != aLines;
}

// Whether license/SPDX-hint text differs between removed and added lines,
// even when those lines sit inside a comment nirjas recognizes: being inside
// a comment does not by itself prove the license text is unchanged.
bool hasLicenseHintChange(const std::vector<ChangedLine>& removed,
                          const std::vector<ChangedLine>& added)
{
  std::set<std::string> rLines, aLines;
  for (const auto& cl : removed)
    if (!isCopyrightLine(cl.text) && hasLicenseTextHint(cl.text))
      rLines.insert(normalize(cl.text));
  for (const auto& cl : added)
    if (!isCopyrightLine(cl.text) && hasLicenseTextHint(cl.text))
      aLines.insert(normalize(cl.text));
  return rLines != aLines;
}

ChangeType classifyChange(const std::vector<DiffHunk>& hunks,
                          const NirjasOutput& oldComments,
                          const NirjasOutput& newComments)
{
  if (hunks.empty()) return ChangeType::LicenseSame;

  const bool oldHasComments = hasCommentBlocks(oldComments);
  const bool newHasComments = hasCommentBlocks(newComments);

  bool sawCode      = false;
  bool sawCopyright = false;
  bool sawComment   = false;

  for (const auto& hunk : hunks)
  {
    // License-hint changes take precedence over copyright changes: a hunk
    // that alters both a copyright statement and license/SPDX text must be
    // vetoed, not accepted as a mere copyright update.
    if (hasLicenseHintChange(hunk.removed, hunk.added))
      return ChangeType::LicenseChanged; // license/SPDX text itself changed

    if (isCopyrightChange(hunk.removed, hunk.added))
    {
      sawCopyright = true;
      continue;
    }

    bool codeLine    = false;
    bool commentLine = false;

    for (const auto& cl : hunk.removed)
    {
      if (isLineInComments(cl.line, oldComments))
        commentLine = true;
      else if (!oldHasComments && !isCopyrightLine(cl.text) && hasLicenseTextHint(cl.text))
        return ChangeType::LicenseChanged; // conservative: no comment data
      else
        codeLine = true;
    }
    for (const auto& cl : hunk.added)
    {
      if (isLineInComments(cl.line, newComments))
        commentLine = true;
      else if (!newHasComments && !isCopyrightLine(cl.text) && hasLicenseTextHint(cl.text))
        return ChangeType::LicenseChanged;
      else
        codeLine = true;
    }

    if (codeLine)    sawCode = true;
    if (commentLine) sawComment = true;
  }

  if (sawCode && sawCopyright) return ChangeType::CodeAndCopyrightChange;
  if (sawCode)      return ChangeType::CodeChange;
  if (sawCopyright) return ChangeType::CopyrightChange;
  if (sawComment)   return ChangeType::LicenseSame;
  return ChangeType::Unknown;
}
