#!/usr/bin/python

import os
import sys
import re

RE_KV = re.compile('(?P<key>[A-Za-z0-9]+):\ +(?P<value>.+)')
RE_OUT = re.compile('(?P<index>[0-9]+)\ \[(?P<start>[0-9]+),\ (?P<end>[0-9]+)\]')
licenses = [line.rstrip() for line in open('training.txt').readlines()]
short_names = []
for license in licenses:
    try:
        meta = dict(RE_KV.findall(open("%s.meta" % license).read().lower()))
        short_names.append(meta['shortname'])
    except:
        short_names.append(os.path.basename(license))

for file in sys.argv[1:]:
    print(os.path.basename(file))
    r = os.popen("./f1 %s" % file).read()
    out = RE_OUT.findall(r)
    for o in out:
        print("%s [%s, %s]" % (short_names[int(o[0])+1], o[1], o[2]))
