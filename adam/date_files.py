# Given a textfile with a list of files a file is touched with the latest date
# of the files in the list.

import os
import stat
import sys

files = [line.strip() for line in open(sys.argv[1])]
mtime = 0
for f in files:
    mtime = max([mtime, os.stat(f)[stat.ST_MTIME]])

if not os.path.isfile(sys.argv[2]):
    open(sys.argv[2],'w').close()
os.utime(sys.argv[2],(mtime, mtime))
