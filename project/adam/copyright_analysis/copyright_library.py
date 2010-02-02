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
    # (stuff['path'], text) = findall_erase(RE_PATH, text)
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
    # stuff['tokens'].extend([['XXXpathXXX', stuff['path'][i][1], stuff['path'][i][2]] for i in range(len(stuff['path']))])
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

def calc_bigram_prob(bigram_hash, word1, word2, word3, default = 0.0):
    p = bigram_hash.get('%s %s %s' % (word1, word2, word3),0.0)
    return p + default

def norm_bigram_hash(bigram_hash):
    n = sum([bigram_hash[k] for k in bigram_hash.keys()])
    for k in bigram_hash.keys():
        bigram_hash[k] = bigram_hash[k] / n
    return n

def create_bigram_hash(tokens, bigram_hash = {}):
    for i in range(1,len(tokens)-1):
        bigram = '%s %s %s' % (tokens[i-1][0], tokens[i][0], tokens[i+1][0])
        bigram_hash[bigram] = bigram_hash.get(bigram,0.0) + 1.0
    return bigram_hash

def create_model(files):
    bigram_hash = {}
    P_inside = {}
    P_outside = {}
    for file in files:
        text = open(file).read()
        stuff = parsetext(text)
        tokens = stuff['tokens']
        inside = False
        for t in tokens:
            if t[0] == 'XXXstartXXX':
                inside = True
                continue
            if t[0] == 'XXXendXXX':
                inside = False
                continue
            if inside:
                P_inside[t[0]] = P_inside.get(t[0],0.0) + 1.0
            else:
                P_outside[t[0]] = P_outside.get(t[0],0.0) + 1.0
    
        tokens.insert(0,['XXXdocstartXXX',-1,-1])
        tokens.insert(0,['XXXdocstartXXX',-1,-1])
        tokens.append(['XXXdocendXXX',len(text),len(text)])
        tokens.append(['XXXdocendXXX',len(text),len(text)])
    
        bigram_hash = create_bigram_hash(tokens,bigram_hash)
    
    norm = 1.0 / sum([P_inside[k] for k in P_inside.keys()])
    for k in P_inside.keys():
        P_inside[k] = norm*P_inside[k]
    norm = 1.0 / sum([P_outside[k] for k in P_outside.keys()])
    for k in P_outside.keys():
        P_outside[k] = norm*P_outside[k]
    norm = norm_bigram_hash(bigram_hash)

    return {"bigram_hash": bigram_hash, "P_inside": P_inside, "P_outside": P_outside, "norm": norm}

def label_file(file, model):
    text = open(file).read(64000)
    
    stuff = parsetext(text)
    tokens = stuff['tokens']
    n = len(tokens)
    tokens.insert(0,['XXXdocstartXXX',-1,-1])
    tokens.insert(0,['XXXdocstartXXX',-1,-1])
    tokens.append(['XXXdocendXXX',len(text),len(text)])
    tokens.append(['XXXdocendXXX',len(text),len(text)])
    
    starts = []
    ends = []
    steps = 0
    in_copyright = False
    for i in range(2,n+2):
        v = tokens[i-2][0]
        w = tokens[i-1][0]
        x = tokens[i+0][0]
        y = tokens[i+1][0]
        z = tokens[i+2][0]
        s = 'XXXstartXXX'
        e = 'XXXendXXX'
    
        P_v_w_x = calc_bigram_prob(model['bigram_hash'], v, w, x, model['norm'])
        P_v_w_s = calc_bigram_prob(model['bigram_hash'], v, w, s, model['norm'])
        P_e_y_z = calc_bigram_prob(model['bigram_hash'], e, y, z, model['norm'])
        P_x_y_z = calc_bigram_prob(model['bigram_hash'], x, y, z, model['norm'])
        if re.match('^[^A-Z]',x):
            P_e_y_z += 0.2*model['P_outside'].get(x,0.0)
            P_x_y_z += 0.8*model['P_inside'].get(x,0.0)
    
        if (x in ['the', 'and']):
            starts.append(False)
            ends.append(False)
            continue
    
        if P_v_w_s > P_v_w_x:
            starts.append(True)
            in_copyright = True
            steps = 0
        else:
            starts.append(False)
            steps += 1

        #if P_e_y_z > P_x_y_z:
        #    ends.append(True)
        #else:
        #    ends.append(False)
    
        if in_copyright and steps > 10:
            in_copyright = False
            ends.append(True)
        else:
            ends.append(False)
    
    offsets = []
    tokens = replace_placeholders(tokens,stuff)
    i = 0
    inside = False
    beginning = 0
    finish = 0
    while (i < len(starts)):
        if starts[i] and not inside:
            beginning = tokens[i][1]
            inside = True
        if inside:
            finish = tokens[i+2][2]
        if ends[i] and inside:
            inside = False
            offsets.append([beginning, finish])
            # print "[%d:%d] ''%r''" % (beginning, finish, text[beginning:finish])
        i += 1
    
    return offsets
