#!/usr/bin/python

import os
import re
import sys

ALL = re.compile('<[Ss][Ee][Nn][Tt][Ee][Nn][Cc][Ee]>|<\/[Ss][Ee][Nn][Tt][Ee][Nn][Cc][Ee]>',re.DOTALL)
FIX = re.compile('(?P<s><\/[Ss][Ee][Nn][Tt][Ee][Nn][Cc][Ee]>?)(?P<m>.*?)(?P<e><[Ss][Ee][Nn][Tt][Ee][Nn][Cc][Ee]>?)',re.DOTALL)

def main():
    print "Validating tagging..."
    total_errors = 0
    for f in sys.argv[1:]:
        text = open(f).readlines()
        start = 0
        errors = 0
        report = []
        for l in range(len(text)):
            line = text[l]
            all = ALL.findall(line)
            for a in all:
                if a[1] == '/':
                    if start == 0:
                        errors += 1
                        report.append("Missing start tag on line %d." % (l+1))
                    start = 0
                else:
                    if start == 1:
                        errors += 1
                        report.append("Missing end tag on line %d." % (l+1))
                    start = 1
        
        if errors > 0:
            print "%s error(s) in %s." % (f,errors)
            print "********************************************************************************"
            for r in report:
                print r
            print "********************************************************************************"
            total_error += errors

    if total_errors < 0:
        print "Validation failed. Please fix these errors before continuing.\n\nExiting..."
        exit()

    print "Fixing tag placement..."
    for f in sys.argv[1:]:
        text = open(f).read()
        text = FIX.sub("\g<m>\g<s>\g<e>",text)
        open(f,'w').write(text)
    print "Finished...\n\nGood Bye;)"

if __name__ == "__main__":
    main()

