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
/* the threashold over which something matches and isn't a negative */
#define THRESHOLD 10

/* list of words that are searched for by the copyright */
cvector copy_dict;

string longest_common(const string& lhs, const string& rhs) {
  int result[lhs.length()][rhs.length()];
  ostringstream ostr;
  int beg = 0, ths = 0, max = 0;

  memset(result, 0, sizeof(result));

  for(unsigned int i = 0; i < lhs.length(); i++) {
    for(unsigned int j = 0; j < rhs.length(); j++) {
      if(lhs[i] == rhs[j]) {
        if(i == 0 || j == 0) {
          result[i][j] = 1;
        } else {
          result[i][j] = result[i - 1][j - 1] + 1;
        }

        if(result[i][j] > max) {
          max = result[i][j];
          ths = i - result[i][j] + 1;
          // the currect substring is still the logest found, continue to append
          // to it instead of creating a new one
          if(beg == ths) {
            ostr << lhs[i];
          }
          // a new longest common substring has been found, clear the stream and
          // start on a new string
          else {
            beg = ths;
            ostr.clear();
            ostr << lhs.substr(beg, (i + 1) - beg);
          }
        }
      }
    }
  }

  return ostr.str();
}

template<typename T>
T min(const T& rhs, const T& lhs) {
  return (rhs < lhs) ? rhs : lhs;
}

void remove_copyrights(string& str) {
  /*for(cvector_iterator iter = cvector_begin(copy_dict); iter != cvector_end(copy_dict); iter++) {
    unsigned int pos = 0;
    while((pos = str.find((char*)*iter, pos)) != string::npos) {
      str.erase(str.begin() + pos, str.begin() + pos + strlen((char*)*iter));
    }
  }*/
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

  /* get the list of matching strings */
  cvector_init(&copy_dict, string_cvector_registry());
  copyright_dictionary(copy, copy_dict);

  /* create the file to output false negatives to */
  ofstream n_out("False_Negatives");
  ofstream p_out("False_Positives");
  ofstream mout("Matches");

  for(int i = 0; i < 140; i++) {
    FILE* istr;
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

    for(vector<string>::iterator iter = compare.begin(); iter != compare.end(); iter++) {
      remove_copyrights(*iter);
    }

    string curr_filename = curr_file.str().substr(0,curr_file.str().length() - 4).c_str();
    istr = fopen(curr_filename.c_str(), "r");
    assert(istr);

    copyright_analyze(copy, istr);
    matches = 0;

    mout << "================================================================================\n";
    mout << curr_filename << "\n" << endl;
    for(copy_iter = copyright_begin(copy); copy_iter != copyright_end(copy); copy_iter++) {
      mout << copy_entry_dict(*copy_iter) << "\t" << copy_entry_name(*copy_iter) << endl;
      mout << copy_entry_text(*copy_iter) << endl;
    }

    for(copy_iter = copyright_begin(copy); copy_iter != copyright_end(copy); copy_iter++) {
      if(compare.size() != 0) {
        vector<string>::iterator best = compare.begin();
        string best_score = "";
        string curr_iter = (char*)*copy_iter;

        remove_copyrights(curr_iter);

        for(vector<string>::iterator iter = compare.begin(); iter != compare.end(); iter++) {
          string curr_score = longest_common(curr_iter, *iter);

          if(curr_score.length() > best_score.length()) {
            best_score = curr_score;
            best = iter;
          }
        }

        if(best_score.length() > THRESHOLD) {
          compare.erase(best);
          matches++;
        } else {
          p_out << "================================================================================\n";
          p_out << copy_entry_dict(*copy_iter) << "\t" << copy_entry_name(*copy_iter) << endl;
          p_out << copy_entry_text(*copy_iter) << endl;
          falsep++;
        }
      } else {
        p_out << copy_entry_dict(*copy_iter) << "\t" << copy_entry_name(*copy_iter) << endl;
        p_out << copy_entry_text(*copy_iter) << endl;
        falsep++;
      }

    }

    cout << "==========  " << curr_file.str() << "  ==========\n";
    cout << "Correct:         " << matches << "\n";
    cout << "False Positive:  " << copyright_size(copy) << "\n";
    cout << "False Negatives: " << compare.size() << "\n" << endl;

    for(vector<string>::iterator iter = compare.begin(); iter != compare.end(); iter++) {
      n_out << "===== " << curr_filename << " ====================================================\n";
      n_out << *iter << endl;
      falsen++;
    }

    correct += matches;

    compare.clear();
    curr.close();
  }

  cout << "==========  Totals  ==========\n";
  cout << "Correct:         " << correct << "\n";
  cout << "False Positive:  " << falsep << "\n";
  cout << "False Negatives: " << falsen << endl;

  copyright_destroy(copy);
  cvector_destroy(copy_dict);
  n_out.close();
  p_out.close();
  mout.close();
}
