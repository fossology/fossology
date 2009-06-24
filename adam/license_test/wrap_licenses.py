#!/usr/bin/python

# Reads in a list of files. Then it adds license_section tags around the text
# in the file and writes it to a directory.
# Usage: wrap_license.py licenses.txt /dir/to/write/the/wrapped/files

import sys, os

try:
    files = [file.strip() for file in open(sys.argv[1]).readlines()]
except:
    sys.stderr.write('ERROR: Could not open %s.\n' % sys.argv[1])

for file in files:
    text = ''
    try:
        text = '<LICENSE_SECTION>%s<?LICENSE_SECTION>' % open(file).read()
    except:
        sys.stderr.write('ERROR: Could not open %s.\n' % file)
    try:
        open("%s/%s" % (sys.argv[2], os.path.basename(file)),'w').write(text)
    except:
        sys.stderr.write('ERROR: Could not open %s/%s.\n' % (sys.argv[2], os.path.basename(file)))
