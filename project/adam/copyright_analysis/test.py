#!/usr/bin/python

## 
## Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
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

# import psyco
# psyco.full()

import re

def findall(RE, text):
    found = []
    for iter in RE.finditer(text):
        found.append([iter.group(), iter.start(), iter.end()])
    return found

months = ['JAN','FEB','MAR','MAY','APR','JUL','JUN','AUG','OCT','SEP','NOV','DEC','January', 'February', 'March', 'April', 'June', 'July', 'August', 'September', 'SEPT', 'October', 'November', 'December',]
RE_ANDOR = re.compile('and\/or',re.I)
RE_EMAIL = re.compile('([A-Za-z0-9\-_\.\+]+@[A-Za-z0-9\-_\.\+]+\.[A-Za-z]+)')
RE_URL   = re.compile('([A-Za-z0-9]+://[A-Za-z0-9\/#&\?\.\+\_]*|www[A-Za-z0-9\/#&\?\.\+\_]*)')
RE_PATH  = re.compile('([^\s\*]*[\\\/][^\s\*]+|[^\s\*]+[\\\/][^\s\*]*)')
RE_YEAR  = re.compile('(19[0-9][0-9]|20[0-9][0-9])')
RE_DATE = re.compile('(((\d+)[\ ]*(%s)[,\ ]*(\d+))|((%s)[\ ]*(\d+)[\ ,]*(\d+))|((\d+)(\ *[-\/\.]\ *)(\d+)(\ *[-\/\.]\ *)(\d+)))' % ('|'.join(months),'|'.join(months)), re.I)
RE_TIME = re.compile('(\d\d\ *:\ *\d\d\ *:\ *\d\d(\ *\+\d\d\d\d)?)')
RE_FLOAT = re.compile('(\d+\.\d+)')
RE_COPYRIGHT = re.compile('(\([c]\)|opyright)',re.I)
RE_TOKEN = re.compile('([A-Za-z0-9]+|[^A-Za-z0-9])')

def main():
    text_orig = open(sys.argv[1]).read()
    
    text = RE_ANDOR.sub('and or',text_orig)
    
    stuff = {}
    
    stuff['email'] = findall(RE_EMAIL, text)
    text = RE_EMAIL.sub('XXXemailXXX', text)
    stuff['url'] = findall(RE_URL, text)
    text = RE_URL.sub('XXXurlXXX', text)
    stuff['path'] = findall(RE_PATH, text)
    text = RE_PATH.sub('XXXpathXXX', text)
    stuff['date'] = findall(RE_DATE, text)
    text = RE_DATE.sub('XXXdateXXX', text)
    stuff['time'] = findall(RE_TIME, text)
    text = RE_TIME.sub('XXXtimeXXX', text)
    stuff['year'] = findall(RE_YEAR, text)
    text = RE_YEAR.sub('XXXyearXXX', text)
    stuff['float'] = findall(RE_FLOAT, text)
    text = RE_FLOAT.sub('XXXfloatXXX', text)

    print stuff

if __name__ == "__main__":
    main()

