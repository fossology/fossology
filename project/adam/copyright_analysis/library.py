## 
## Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
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

import re

def findall(RE, text):
    found = []
    for iter in RE.finditer(text):
        found.append([iter.group(), iter.start(), iter.end()])
    return found

def findall_erase(RE, text):
    new_text = list(text)
    found = []
    for iter in RE.finditer(text):
        l = [iter.group(), iter.start(), iter.end()]
        found.append(l)
        new_text[l[1]:l[2]] = [' ' for i in range(l[2]-l[1])]
    return (found, ''.join(new_text))

months = ['JAN','FEB','MAR','MAY','APR','JUL','JUN','AUG','OCT','SEP','NOV','DEC','January', 'February', 'March', 'April', 'June', 'July', 'August', 'September', 'SEPT', 'October', 'November', 'December',]
RE_ANDOR = re.compile('and\/or',re.I)
RE_COMMENT = re.compile('\s([\*\/\#\%\!\@]+)')
RE_EMAIL = re.compile('([A-Za-z0-9\-_\.\+]+@[A-Za-z0-9\-_\.\+]+\.[A-Za-z]+)')
RE_URL   = re.compile('([A-Za-z0-9]+://[A-Za-z0-9\/#&\?\.\+\_]*|www[A-Za-z0-9\/#&\?\.\+\_]*)')
RE_PATH  = re.compile('([^\s\*]{0,100}[\\\/][^\s\*]{1,100}|[^\s\*]{1,100}[\\\/][^\s\*]{0,100})')
RE_YEAR  = re.compile('[^0-9](19[0-9][0-9]|20[0-9][0-9])[^0-9]')
RE_DATE = re.compile('(((\d+)[\ ]*(%s)[,\ ]*(\d+))|((%s)[\ ]*(\d+)[\ ,]*(\d+))|((\d+)(\ *[-\/\.]\ *)(\d+)(\ *[-\/\.]\ *)(\d+)))' % ('|'.join(months),'|'.join(months)), re.I)
RE_TIME = re.compile('(\d\d\ *:\ *\d\d\ *:\ *\d\d(\ *\+\d\d\d\d)?)')
RE_FLOAT = re.compile('(\d+\.\d+)')
RE_COPYRIGHT = re.compile('(\([c]\)|c?opyright|\&copy\;)',re.I)
RE_START = re.compile('(<s>)')
RE_END = re.compile('(<\/s>)')
RE_TOKEN = re.compile('([A-Za-z0-9]+)')
RE_ANYTHING = re.compile('.', re.DOTALL)

def token_sort(x,y):
    if (x[1] < y[1]):
        return -1
    if (x[1] > y[1]):
        return 1
    return 0

def parsetext(text):
    stuff = {}
    
    text = RE_ANDOR.sub('and or',text)
    (temp, text) = findall_erase(RE_COMMENT, text)

    (stuff['start'], text) = findall_erase(RE_START, text)
    (stuff['end'], text) = findall_erase(RE_END, text)
    (stuff['email'], text) = findall_erase(RE_EMAIL, text)
    (stuff['url'], text) = findall_erase(RE_URL, text)
    (stuff['path'], text) = findall_erase(RE_PATH, text)
    (stuff['date'], text) = findall_erase(RE_DATE, text)
    (stuff['time'], text) = findall_erase(RE_TIME, text)
    (stuff['year'], text) = findall_erase(RE_YEAR, text)
    (stuff['float'], text) = findall_erase(RE_FLOAT, text)
    (stuff['copyright'], text) = findall_erase(RE_COPYRIGHT, text)
    (stuff['tokens'], text) = findall_erase(RE_TOKEN, text)

    stuff['tokens'].extend([['XXXstartXXX', stuff['start'][i][1], stuff['start'][i][2]] for i in range(len(stuff['start']))])
    stuff['tokens'].extend([['XXXendXXX', stuff['end'][i][1], stuff['end'][i][2]] for i in range(len(stuff['end']))])
    stuff['tokens'].extend([['XXXemailXXX', stuff['email'][i][1], stuff['email'][i][2]] for i in range(len(stuff['email']))])
    stuff['tokens'].extend([['XXXurlXXX', stuff['url'][i][1], stuff['url'][i][2]] for i in range(len(stuff['url']))])
    stuff['tokens'].extend([['XXXpathXXX', stuff['path'][i][1], stuff['path'][i][2]] for i in range(len(stuff['path']))])
    stuff['tokens'].extend([['XXXdateXXX', stuff['date'][i][1], stuff['date'][i][2]] for i in range(len(stuff['date']))])
    stuff['tokens'].extend([['XXXtimeXXX', stuff['time'][i][1], stuff['time'][i][2]] for i in range(len(stuff['time']))])
    stuff['tokens'].extend([['XXXyearXXX', stuff['year'][i][1], stuff['year'][i][2]] for i in range(len(stuff['year']))])
    stuff['tokens'].extend([['XXXfloatXXX', stuff['float'][i][1], stuff['float'][i][2]] for i in range(len(stuff['float']))])
    stuff['tokens'].extend([['XXXcopyrightXXX', stuff['copyright'][i][1], stuff['copyright'][i][2]] for i in range(len(stuff['copyright']))])

    stuff['tokens'].sort(token_sort)

    return stuff

def replace_placeholders(tokens,stuff):
    t = tokens[:]
    n = len(t)
    for needle in ['XXXcopyrightXXX', 'XXXfloatXXX', 'XXXyearXXX', 'XXXtimeXXX',
            'XXXdateXXX', 'XXXpathXXX', 'XXXurlXXX', 'XXXemailXXX',]:
        count = 0
        for i in range(n):
            if t[i][0] == needle:
                t[i][0] = stuff[needle.replace('X','')][count]
                count += 1
    return t


def tokens_and_labels_from_text(text):
    a = re.findall('[IO], .*',text)
    labels = []
    tokens = []
    for i in range(len(a)):
        label = a[i][0]
        token = a[i][3:]
        labels.append(label)
        tokens.append(token)
    
    return (tokens, labels)

def features(tokens):
    left = 2
    right = 2
    data = []
    ts = []
    attribs = set()
    for i in range(len(tokens)):
        d = {}
        t = []
        for j in range(-left,right+1):
            if -1 > i+j or i+j < len(tokens):
                t.append(tokens[i+j])
                a = "%s_%d" % (tokens[i+j], j+left)
                d[a] = 'TRUE'
            else:
                t.append("XXXblankXXX")
                a = "%s_%d" % ('XXXblankXXX', j+left)
                d[a] = 'TRUE'
        data.append(d)
        ts.append(t)
    
    for i in range(len(data)):
        n = len(data[i])
        keys = data[i].keys()
        for k in range(left+right+1):
            if re.match('^[A-Z].*', ts[i][k]):
                data[i]['first_capped_%d' % k] = 'TRUE'
            else:
                data[i]['first_capped_%d' % k] = 'FALSE'
            if re.match('^[A-Z]+', ts[i][k]):
                data[i]['all_capped_%d' % k] = 'TRUE'
            else:
                data[i]['all_capped_%d' % k] = 'FALSE'
            if re.match('[0-9]', ts[i][k]):
                data[i]['contains_number_%d' % k] = 'TRUE'
            else:
                data[i]['contains_number_%d' % k] = 'FALSE'
            if re.match('^[0-9]+$', ts[i][k]):
                data[i]['is_number_%d' % k] = 'TRUE'
            else:
                data[i]['is_number_%d' % k] = 'FALSE'
            #data[i]['length_%d' % k] = len(ts[i][k])

        [attribs.add(k) for k in data[i].keys()]

    return data, attribs

