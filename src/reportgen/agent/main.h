#include <json/json.h>
#include <stdlib.h>
#include <time.h>

#define READMAX 1024*1024            ///< farthest into a file to look for copyrights
#define THRESHOLD 10                 ///< tuned threshold for testing purposes
#define TESTFILE_NUMBER 140          ///< the number of files pairs used in tests
#define AGENT_NAME "reportgen"       ///< the name of the agent, used to get agent key
#define AGENT_DESC "reportgen agent" ///< what program this is
#define AGENT_ARS  "reportgen_ars"   ///< name used for the ars table
#define DECLEN 256
#define myBUFSIZ 1000

#define HEADERXML "header1.xml"
#define FOOTERXML "footer1.xml"
#define FONTTABLE "fontTable.xml"
#define DOCUMENTXML "document.xml"
#define STYLESXML "styles.xml"
#define NUMBERINGXML "numbering.xml"
#define DOCXMLRELS "document.xml.rels"
#define CONTENTTYPEXML "[Content_Types].xml"
#define APPXML "app.xml"
#define COREXML "core.xml"
#define HIDDENRELSXML ".rels"

#ifndef FOSSREPO_CONF
#define FOSSREPO_CONF "/srv/fossology/repository"
#endif