#!/usr/bin/python -u

## 
## Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
## 
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## version 2 as published by the Free Software Foundation.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License along
## with this program; if not, write to the Free Software Foundation, Inc.,
## 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##

import sys
import os
import re
import vars

# try and open libfosspython. We will just assume it is installed where python
# can find it.

try:
    import libfosspython
except:
    # look in the install location
    if os.path.exists('%s/libfosspython.so' % vars.PYTHONLIBEXECDIR):
        sys.path.append(vars.PYTHONLIBEXECDIR)
    # look in the devel location
    if os.path.exists('%s/libfosspython.so' % vars.PYTHONLIBPATH):
        sys.path.append(vars.PYTHONLIBPATH)

try:
    import libfosspython
except:
    print >> sys.stderr, "FATAL: Could not find libfosspython.so."
    print >> sys.stderr, "\tLooked in '%s' and '%s'." % (vars.PYTHONLIBEXECDIR,
            vars.PYTHONLIBPATH)
    sys.exit(-1)

# set sys.stdout and sys.stderr to blocking. Otherwise we get a resource
# temporarily unavailable error :-(

import fcntl
fcntl.fcntl(sys.stdout.fileno(),fcntl.F_SETFL, fcntl.fcntl(sys.stdout.fileno(), fcntl.F_GETFL) & ~os.O_NONBLOCK)
fcntl.fcntl(sys.stderr.fileno(),fcntl.F_SETFL, fcntl.fcntl(sys.stderr.fileno(), fcntl.F_GETFL) & ~os.O_NONBLOCK)

try:
    import copyright
except:
    print >> sys.stderr, "FATAL: Could not find copyright.py."

try:
    import copyright_library
except:
    print >> sys.stderr, "FATAL: Could not find copyright_library.py."

sys.exit(copyright.main())
