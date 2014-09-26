/*
 * regCopyright.h
 *
 *  Created on: Sep 24, 2014
 *      Author: ”J. Najjar”
 */

#ifndef REGCOPYRIGHT_H_
#define REGCOPYRIGHT_H_

#include <string>

namespace regCopyright{
   const std::string getRegex();
   const std::string getType();
}


namespace regURL{
   const std::string getRegex();
   const std::string getType();
}


namespace regEmail{
   const std::string getRegex();
   const std::string getType();
}

namespace regEcc{
   const std::string getRegex();
   const std::string getType();
}

namespace regIp{
   const std::string getRegex();
   const std::string getType();
}
#endif /* REGCOPYRIGHT_H_ */
