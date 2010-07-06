/***************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

***************************************************************/

/* std library includes */
#include <string.h>
#include <stdlib.h>
#include <assert.h>
#include <time.h>
#include <iostream>
using std::cout;
using std::endl;
using std::cerr;
#include <fstream>
using std::ifstream;
using std::ofstream;
#include <string>
using std::string;
#include <sstream>
using std::ostringstream;
#include <vector>
using std::vector;
#include <algorithm>
using std::for_each;


/* local includes */
#include <copyright.h>

/* distance to read into a file */
#define READMAX 1024*1024

string longest_common(const string& lhs, const string& rhs) {
  int result[lhs.length()][rhs.length()];
  string ret;
  int z = 0;

  memset(result, 0, sizeof(result));

  for(unsigned int i = 0; i < lhs.length(); i++) {
    for(unsigned int j = 0; j < rhs.length(); j++) {
      if(lhs[i] == rhs[j]) {
        if(i == 0 || j == 0) {
          result[i][j] = 1;
        } else {
          result[i][j] = result[i - 1][j - 1] + 1;
        }

        if(result[i][j] > z) {
          z = result[i][j];
          ret = "";
        }

        if(result[i][j] == z) {
          ret = lhs.substr(i - z+1, i - 1);
          cout << result[i][j] << " " << i << " " << j << " " << ret << " " << lhs.substr(i - z+1) << endl;
        }
      }
    }
  }

  for(unsigned int i = 0; i < lhs.length(); i++) {
    for(unsigned int j = 0; j < rhs.length(); j++) {
      cout << result[i][j] << "  ";
    }
    cout << endl;
  }
  cout << "GER: \"" << rhs << "\"" << endl;
  cout << "OTH: \"" << lhs << "\"" << endl;
  cout << "RET: \"" << ret << "\"\t" << ret.length() << endl;
  sleep(3);

  return ret;
}

int main(int argc, char** argv) {
  copyright copy;
  vector<string> compare;
  char buffer[READMAX];
  unsigned int first, last, matches, correct, falsep, falsen;
  copyright_iterator copy_iter;

  /* initalize the copyright analyzer */
  copyright_init(&copy);
  memset(buffer, '\0', sizeof(buffer));
  correct = falsep = falsen = 0;

  /* create the file to output false negatives to */
  ofstream fout("False_Negatives");
  ofstream mout("Matches");

  for(int i = 0; i < 140; i++) {
    ostringstream curr_file;
    curr_file << "testdata/testdata" << i << "_raw";

    ifstream curr(curr_file.str().c_str());
    assert(!curr.fail());

    curr.read(buffer, READMAX);
    buffer[READMAX - 1] = '\0';

    string contents(buffer);

    for(string::iterator iter = contents.begin(); iter != contents.end(); iter++) {
      if(*iter >= 'A' && *iter <= 'Z') {
        *iter = *iter - 'A' + 'a';
      }
    }

    while((first = contents.find("<s>")) != string::npos) {
      last = contents.find("</s>");

      if(last == string::npos) {
        cout << "ERROR: unmatched <s>" << endl;
        cout << "ERROR: in file: " << curr_file.str() << endl;
        exit(-1);
      }

      if(last <= first) {
        cout << "ERROR: unmatched </s>" << endl;
        cout << "ERROR: in file: " << curr_file.str() << endl;
        exit(-1);
      }

      compare.push_back(contents.substr(first + 3, last - first - 3));
      contents = contents.substr(last + 4);
    }

    copyright_analyze_file(copy, curr_file.str().substr(0,curr_file.str().length() - 4).c_str());
    matches = 0;

    for(copy_iter = copyright_begin(copy); copy_iter != copyright_end(copy) && compare.size() > 0; copy_iter++) {
      vector<string>::iterator best = compare.begin();
      string best_score = "";

      for(vector<string>::iterator iter = compare.begin(); iter != compare.end(); iter++) {
        string curr_score = longest_common((char*)*copy_iter, *iter);

        if(curr_score.length() > best_score.length()) {
          best_score = curr_score;
          best = iter;
        }
      }

      mout << "================================================================================\n";
      mout << "LOOK:  " << (char*)*copy_iter << "\n" << endl;
      mout << "FOUND: " << best_score << endl;
      compare.erase(best);
      matches++;
    }

    cout << "==========  " << curr_file.str() << "  ==========\n";
    cout << "Correct:         " << matches << "\n";
    cout << "False Positive:  " << copyright_size(copy) - matches << "\n";
    cout << "False Negatives: " << compare.size() << "\n" << endl;

    for(vector<string>::iterator iter = compare.begin(); iter != compare.end(); iter++) {
      fout << "================================================================================\n";
      fout << *iter << endl;
    }

    correct += matches;
    falsep  += copyright_size(copy) - matches;
    falsen  += compare.size();

    compare.clear();
    curr.close();
  }

  cout << "==========  Totals  ==========\n";
  cout << "Correct:         " << correct << "\n";
  cout << "False Positive:  " << falsep << "\n";
  cout << "False Negatives: " << falsen << endl;

  copyright_destroy(copy);
  fout.close();
  mout.close();
}
