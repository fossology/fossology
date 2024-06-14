/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file
 * \brief Utilities to scan directories
 */

#include "directoryScan.hpp"

using namespace std;
namespace fs = boost::filesystem;

void scanDirectory(const CopyrightState& state, const bool json,
    const string directoryPath)
{
  fs::recursive_directory_iterator dirIterator(directoryPath);
  fs::recursive_directory_iterator end;

  vector<string> filePaths;

  for (fs::path const &p : boost::make_iterator_range(dirIterator, {}))
  {
    if (fs::is_directory(p))
    {
      // Can not do anything with a directory
      continue;
    }
    // Store the paths in a vector as of now since we can not `#pragma omp for`
    // on recursive_directory_iterator
    filePaths.push_back(p.string());
  }
  const unsigned long filePathsSize = filePaths.size();
  bool printComma = false;

  if (json)
  {
    cout << "[" << endl;
  }

#pragma omp parallel
  {
#pragma omp for
    for (unsigned int i = 0; i < filePathsSize; i++)
    {
      string fileName = filePaths[i];
      pair<icu::UnicodeString, list<match>> scanResult = processSingleFile(state, fileName);
      if (json)
      {
        appendToJson(fileName, scanResult, printComma);
      }
      else
      {
        printResultToStdout(fileName, scanResult);
      }
    }
  }
  if (json)
  {
    cout << endl << "]" << endl;
  }
}
