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

import re
import math

def findall(RE, text):
    """
    findall(Regex, text) -> list(['str_1',start_byte_1, end_byte_1], ...)

    Uses the re.finditer method to locate all instances of the search 
    string and return the string and its start and end bytes.

    Regex is a compiled regular expression using the re.compile() method.
    text is the body of text to search through.
    """
    found = []
    for iter in RE.finditer(text):
        found.append([iter.group(), iter.start(), iter.end()])
    return found

def findall_erase(RE, text):
    """
    findall_erase(Regex, text) -> string

    Uses the re.finditer method to locate all instances of the search
    string and replaces them with spaces. The modified text is returned.

    Regex is a compiled regular expression using the re.complile() method.
    text is the body of text to search through.
    """
    new_text = list(text)
    found = []
    for iter in RE.finditer(text):
        l = [iter.group(), iter.start(), iter.end()]
        found.append(l)
        new_text[l[1]:l[2]] = [' ' for i in range(l[2]-l[1])]
    return (found, ''.join(new_text))

# standard Regular Expressions used to tokenize files.
months = ['JAN','FEB','MAR','MAY','APR','JUL','JUN','AUG','OCT','SEP','NOV','DEC','January', 'February', 'March', 'April', 'June', 'July', 'August', 'September', 'SEPT', 'October', 'November', 'December',]
RE_ANDOR = re.compile('and\/or',re.I)
RE_COMMENT = re.compile('\s([\*\/\#\%\!\@]+)')
RE_EMAIL = re.compile('([A-Za-z0-9\-_\.\+]{1,100}@[A-Za-z0-9\-_\.\+]{1,100}\.[A-Za-z]{1,4})')
RE_URL   = re.compile('(((https?)://[\-\w]{1,100}(\.\w[-\w]{0,100}){1,100}|([\-a-z0-9]+\.){1,100}(com|edu|biz|gov|int|info|mil|net|org|me|mobi|us|ca|mx|ag|bz|gs|ms|tc|vg|eu|de|it|fr|nl|am|at|be|asia|cc|in|nu|tv|tw|jp|[a-z][a-z]\.[a-z][a-z])\b)(:\d+)?(/[A-Za-z0-9\/#&\?\.\+\-\_]{0,100})?)', re.I)
RE_PATH  = re.compile('([^\s\*]{0,100}[\\\/][^\s\*]{1,100}|[^\s\*]{1,100}[\\\/][^\s\*]{0,100})')
RE_YEAR  = re.compile('[^0-9](19[0-9][0-9]|20[0-9][0-9])[^0-9]')
RE_DATE = re.compile('(((\d+)[\ ]*(%s)[,\ ]*(\d+))|((%s)[\ ]*(\d+)[\ ,]*(\d+))|((\d+)(\ *[-\/\.]\ *)(\d+)(\ *[-\/\.]\ *)(\d+)))' % ('|'.join(months),'|'.join(months)), re.I)
RE_TIME = re.compile('(\d\d\ *:\ *\d\d\ *:\ *\d\d(\ *\+\d\d\d\d)?)')
RE_FLOAT = re.compile('(\d+(\.\d+)?)')
RE_COPYRIGHT = re.compile('(\([c]\)|c?opyright|\&copy\;)',re.I)
RE_START = re.compile('(<s>)')
RE_END = re.compile('(<\/s>)')
RE_TOKEN = re.compile('([A-Za-z0-9]+)')
RE_ANYTHING = re.compile('.', re.DOTALL)

def parsetext(text):
    """
    parsetext(text) -> dict()

    Tokenizes a body of text and returns a dictionary containing
    a set of features located within the text.

    'start':    the byte offset of '<s>' tags found in the text.
    'end':      the byte offset of '</s>' tags found in the text.
    'email':    the byte offset and text of emails found in the text.
    'date':     the byte offset and text of date like strings found.
    'time':     the byte offset and text of time like strings found.
    'year':     the byte offset and text of year like strings found.
    'float':    the byte offset and text of floating point numbers found.
    'copyright':the byte offset and text of copyright strings and symbols.
                includes 'opyright', '(c)', and the copyright characters.
    'tokens':   the byte offset and text of white space split tokens.
                this also includes the characters located between 
                alphanumeric characters.
    """
    stuff = {}
    
    print 0

    text = RE_ANDOR.sub('and or',text)
    (temp, text) = findall_erase(RE_COMMENT, text)

    print 1
    (stuff['start'], text) = findall_erase(RE_START, text)
    print 2
    (stuff['end'], text) = findall_erase(RE_END, text)
    print 3
    (stuff['email'], text) = findall_erase(RE_EMAIL, text)
    print 4
    (stuff['url'], text) = findall_erase(RE_URL, text)
    # (stuff['path'], text) = findall_erase(RE_PATH, text)
    print 5
    #(stuff['date'], text) = findall_erase(RE_DATE, text)
    print 6
    #(stuff['time'], text) = findall_erase(RE_TIME, text)
    print 7
    (stuff['year'], text) = findall_erase(RE_YEAR, text)
    print 8
    (stuff['float'], text) = findall_erase(RE_FLOAT, text)
    print 9
    (stuff['copyright'], text) = findall_erase(RE_COPYRIGHT, text)
    print 10
    (stuff['tokens'], text) = findall_erase(RE_TOKEN, text)
    print 11


    # we replace the original information extracted from the text with place
    # holders so we can learn a generic trend in the structure of the
    # documents.
    stuff['tokens'].extend([['XXXstartXXX', stuff['start'][i][1], stuff['start'][i][2]] for i in range(len(stuff['start']))])
    stuff['tokens'].extend([['XXXendXXX', stuff['end'][i][1], stuff['end'][i][2]] for i in range(len(stuff['end']))])
    stuff['tokens'].extend([['XXXemailXXX', stuff['email'][i][1], stuff['email'][i][2]] for i in range(len(stuff['email']))])
    stuff['tokens'].extend([['XXXurlXXX', stuff['url'][i][1], stuff['url'][i][2]] for i in range(len(stuff['url']))])
    # stuff['tokens'].extend([['XXXpathXXX', stuff['path'][i][1], stuff['path'][i][2]] for i in range(len(stuff['path']))])
    # stuff['tokens'].extend([['XXXdateXXX', stuff['date'][i][1], stuff['date'][i][2]] for i in range(len(stuff['date']))])
    # stuff['tokens'].extend([['XXXtimeXXX', stuff['time'][i][1], stuff['time'][i][2]] for i in range(len(stuff['time']))])
    stuff['tokens'].extend([['XXXyearXXX', stuff['year'][i][1], stuff['year'][i][2]] for i in range(len(stuff['year']))])
    stuff['tokens'].extend([['XXXfloatXXX', stuff['float'][i][1], stuff['float'][i][2]] for i in range(len(stuff['float']))])
    stuff['tokens'].extend([['XXXcopyrightXXX', stuff['copyright'][i][1], stuff['copyright'][i][2]] for i in range(len(stuff['copyright']))])

    stuff['tokens'].sort(token_sort)

    return stuff

def replace_placeholders(tokens,stuff):
    """
    replace_placeholders(tokens, parse_dictionary) -> list()

    Given a set of tokens and a parse_dictionary replace all the
    place holders in the list of tokens with their respective
    counter parts in the parse_dictionary.

    The parse_dictionary is the dictionary returned by the 
    parsetext function.

    the list of tokens is a list of lists containing the token
    as a string and the start and end bytes of the token as structured
    as the tokens element in the parse_dictionary.
    """
    t = tokens[:]
    n = len(t)
    for needle in ['XXXcopyrightXXX', 'XXXfloatXXX', 'XXXyearXXX', 'XXXtimeXXX',
            'XXXdateXXX', 'XXXpathXXX', 'XXXurlXXX', 'XXXemailXXX',]:
        count = 0
        for i in range(n):
            if t[i][0] == needle:
                t[i][0] = stuff[needle.replace('X','')][count][0]
                count += 1
    return t

def token_sort(x,y):
    """
    token_sort(x,y) -> int

    A sort help function. Takes two lists, ['string', int, int], and returns
    {-1: if x < y, 1: if x > 1, 0: if x == y}.
    """
    if (x[1] < y[1]):
        return -1
    if (x[1] > y[1]):
        return 1
    return 0

def tokens_to_BIO(tokens):
    """
    tokens_to_BIO(tokens) -> (tokens, labels)

    Takes a token list and returns the BIO labels based on the position
    of the start and end tags.

    This is used to convert the training data which is labeled using '<s></s>'
    tags into a NLP standard labeling.

    The returned tokens does not include start and end place holders.
    The labels list contained the BIO tag for that token.

    B - begining
    I - inside
    O - outside
    """
    n = len(tokens)
    t = []
    l = []
    
    s = 'O'
    for token in tokens:
        if token == 'XXXstartXXX':
            s = 'B'
            continue
        elif token == 'XXXendXXX':
            s = 'O'
            continue
        t.append(token)
        l.append(s)
        if s == 'B':
            s = 'I'

    return (t,l)

def train_nb(data):
    n = len(data)

    classes = ['B', 'I', 'O',]
    features = ['current_word', 'previous_label', 'previous_word', 'next_word']
    PFC = {} # P(F_{i}|C) accessed as PFC[C][F][i] for simplicity.
    # initialize our model
    for c in classes:
        PFC[c] = {'class':0.0}
        for f in features:
            PFC[c][f] = {}

    for i in xrange(n):
        (tokens, labels) = data[i]
        nn = len(tokens)
        for j in xrange(nn):
            c = labels[j]
            w = tokens[j]
            PFC[c]['class'] += 1.0
            PFC[c]['current_word'][w] = PFC[c]['current_word'].get(w, 0.0) + 1.0
            c_1 = ''
            if j != 0:
                c_1 = labels[j-1]
            PFC[c]['previous_label'][c_1] = PFC[c]['previous_label'].get(c_1, 0.0) + 1.0
            w_1 = ''
            if j != 0:
                w_1 = tokens[j-1]
            PFC[c]['previous_word'][w_1] = PFC[c]['previous_word'].get(w_1, 0.0) + 1.0
            w_1 = ''
            if j != nn-1:
                w_1 = tokens[j+1]
            PFC[c]['next_word'][w_1] = PFC[c]['next_word'].get(w_1, 0.0) + 1.0

    # normalize everything
    for c in classes:
        for f in features:
            s = sum(PFC[c][f].values())
            for (k,v) in PFC[c][f].iteritems():
                PFC[c][f][k] = math.log(v/s)

    s = 0.0
    for c in classes:
        s += PFC[c]['class']
    for c in classes:
        PFC[c]['class'] = math.log(PFC[c]['class']/s)

    return PFC

def label_nb(PFC, tokens, debug=False):
    L = []
    P = []

    classes = PFC.keys()
    features = ['current_word', 'previous_label', 'previous_word', 'next_word']
    minimums = {}
    minimums['B'] = {'current_word':math.log(1e-23), 'previous_label':math.log(1e-23), 'previous_word':math.log(1e-23), 'next_word':math.log(1e-23)}
    minimums['I'] = {'current_word':math.log(1e-10), 'previous_label':math.log(1e-23), 'previous_word':math.log(1e-10), 'next_word':math.log(1e-10)}
    minimums['O'] = {'current_word':math.log(1e-10), 'previous_label':math.log(1e-10), 'previous_word':math.log(1e-10), 'next_word':math.log(1e-10)}

    for t in xrange(len(tokens)):
        fv = {}
        fv['current_word'] = tokens[t]
        fv['previous_label'] = ''
        if t > 0:
            fv['previous_label'] = L[-1]
        fv['previous_word'] = ''
        if t > 0:
            fv['previous_word'] = tokens[t-1]
        fv['next_word'] = ''
        if t < len(tokens)-1:
            fv['next_word'] = tokens[t+1]

        p = dict([(c,0.0) for c in classes])
        for c in classes:
            p[c] += PFC[c]['class']
            for f in features:
                p[c] += PFC[c][f].get(fv[f],minimums[c][f])
    
        i = 'O'
        if t == 0:
            if p['B'] > p['O']:
                i = 'B'
        elif L[-1] == 'O':
            if p['B'] > p['O']:
                i = 'B'
        elif L[-1] == 'B':
            if p['B'] > max([p['O'], p['I']]):
                i = 'B'
            elif p['I'] > p['O']:
                i = 'I'
        elif L[-1] == 'I':
            if p['I'] > max([p['O'], p['B']]):
                i = 'I'
            elif p['B'] > p['O']:
                i = 'B'

        P.append(p)
        L.append(i)

    if debug:
        return L,P
    return L

def create_model(training_data):
    n = len(training_data)
    # need to convert the string data into BIO labels and tokens.
    parsed_data = [parsetext(text) for text in training_data]
    tokens = [[parsed_data[i]['tokens'][j][0] for j in xrange(len(parsed_data[i]['tokens']))] for i in xrange(n)]
    bio_data = [tokens_to_BIO(tokens[i]) for i in xrange(n)]

    # create the naive Bayes model
    PFC = train_nb(bio_data)

    model = {'id':hex(abs(hash(str(PFC)))), 'P(F|C)':PFC}

    return model

def label_file(file, model):
    PFC = model['P(F|C)']
    text = open(file).read(64000)

    # parse the file and get the tokens
    parsed_text = parsetext(text)
    tokens = [parsed_text['tokens'][j][0] for j in xrange(len(parsed_text['tokens']))]

    offsets = []
    
    starts = []
    ends = []
    L = label_nb(PFC, tokens)
    for l in xrange(len(L)):
        if L[l] == 'B':
            starts.append(parsed_text['tokens'][l][1])
        if l>0 and L[l-1] == 'B' and L[l] == 'O':
            starts.pop()
        elif l>0 and L[l-1] != 'O' and L[l] == 'O':
            ends.append(parsed_text['tokens'][l-1][2])
        elif l>0 and L[l-1] == 'I' and L[l] == 'B':
            ends.append(parsed_text['tokens'][l-1][2])
    if len(starts)>len(ends):
        ends.append(parsed_text['tokens'][-1][2])

    for i in xrange(len(starts)):
        offsets.append((starts[i], ends[i], 'statement'))

    for item in parsed_text['email']:
        offsets.append((item[1], item[2], 'email'))
    for item in parsed_text['url']:
        offsets.append((item[1], item[2], 'url'))

    return offsets
