/*
 * cleanEntries.hpp
 *
 *  Created on: Sep 25, 2014
 *      Author: ”J. Najjar”
 */

#ifndef CLEANENTRIES_HPP_
#define CLEANENTRIES_HPP_

#include <string>
#include "regex.hpp"

class DatabaseEntry {
public:

  long agent_fk;
  long pfile_fk;
  std::string content;
  std::string hash;
  std::string type;
  int copy_startbyte;
  int copy_endbyte;
};


bool CleanDatabaseEntry(DatabaseEntry& input);


#endif /* CLEANENTRIES_HPP_ */
