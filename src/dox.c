/* This file defines the doxygen home page.  Currently doxygen is only used on
 * source.
 */

/*!
 * \mainpage Overview of FOSSology
 * \tableofcontents
 *
 * More developer information can be found at http://www.fossology.org/projects/fossology/wiki/Developer_Documentation
 *
 * \section about About
 * FOSSology is a open source license compliance software system and toolkit.  As a toolkit you can run license, copyright and export control scans from the command line.  As a system, a database and web ui are provided to give you a compliance workflow. In one click you can generate an SPDX file, or a ReadMe with all the copyrights notices from your software. FOSSology deduplication means that you can scan an entire distro, rescan a new version, and only the changed files will get rescanned. This is a big time saver for large projects.
 * [Check out Who Uses FOSSology!](http://www.fossology.org/projects/fossology/wiki/WhoUsesFOSSology)
 * FOSSology does not give legal advice.
 *
 * \section installation Installation
 * FOSSology should work with many Linux distributions.
 * See https://github.com/fossology/fossology/releases for source code download of the releases.
 * For installation instructions see [Github Wiki](https://github.com/fossology/fossology/wiki)
 *
 * \section libsection FOSSology C library
 * \link src/lib/ \endlink contains fossology.a and all the component files that go into this common core C library.
 * \subsection libc FOSSology library for C
 * \link src/lib/c \endlink contains components for FOSSology core C library.
 * \subsection libcpp FOSSology library for CPP
 * \link src/lib/cpp \endlink contains components for FOSSology core CPP library
 * \subsection libphp FOSSology library for PHP
 * \link src/lib/php \endlink contains components for FOSSology PHP library
 *
 * \section schedsection scheduler
 * You can find all the scheduler code in src/scheduler/.  But we might want to move it
 * under modules/ since there is both a backend and UI components.
 *
 * \section modsection modules
 * The module directory structure can be found at http://www.fossology.org/projects/fossology/wiki/Module_Structure
 *
 * \subsection subui ui/
 * Contains UI components for the module.
 * \subsection subuitests ui_tests/
 * Contains UI test components for the module.
 * \subsection subagent agent/
 * Contains actual module source.
 * \subsection subagenttests agent_tests/
 * Contains test components for the module.
 * \subsection submodconf module.conf
 * Contains configurations required by module.
 * \subsection subscripts scripts/
 *
 * \section agents Agents
 * \ref agentlist
 */

/*!
 * \page agentlist Agents in FOSSology
 * \tableofcontents
 * \section agents Agents in FOSSology
 * -# \subpage adj2nest
 * -# \subpage buckets
 * -# \subpage copyright
 * -# \subpage decider
 * -# \subpage deciderjob
 * -# \subpage delagent
 * -# \subpage maintagent
 * -# \subpage mimetype
 * -# \subpage pkgagent
 * -# \subpage readmeoss
 * -# \subpage reuser
 * -# \subpage spdx2
 * -# \subpage unifiedreport
 * -# \subpage ununpack
 * -# \subpage wget_agent
 */

/* General directory definitions */
/**
 * \dir Functional
 * \brief Contains functional test cases
 * \dir Unit
 * \brief Contains unit test cases
 * \dir agent
 * \brief Contains agent's source code
 * \dir agent_tests
 * \brief Contains agent's test cases
 * \dir ui
 * \brief Contains UI modules of the agent
 * \dir ui_tests
 * \brief Contains test cases for UI modules
 */
