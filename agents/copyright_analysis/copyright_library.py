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

months = ['JAN','FEB','MAR','MAY','APR','JUL','JUN','AUG','OCT','SEP','NOV','DEC','January', 'February', 'March', 'April', 'June', 'July', 'August', 'September', 'SEPT', 'October', 'November', 'December',]
tokenizer_pattern = r"""(?ixs) # VERBOSE | IGNORE | DOTALL
(?P<token>
    (?P<start>\<s\>)
    |
    (?P<end>\<\/s\>)
    |
    (?P<date>(?:(?:\d+)[\ ]*(?:%s)[,\ ]*(?:\d+))|(?:(?:%s)[\ ]*(?:\d+)[\ ,]*(?:\d+))|(?:(?:\d+)(?:\ *[-\/\.]\ *)(?:\d+)(?:\ *[-\/\.]\ *)(?:\d+)))
    |
    (?P<time>(?:\d\d\ *:\ *\d\d\ *:\ *\d\d(?:\ *\+\d\d\d\d)?))
    |
    (?P<email>[\<\(]?[A-Za-z0-9\-_\.\+]{1,100}@[A-Za-z0-9\-_\.\+]{1,100}\.[A-Za-z]{1,4})[\>\)]?
    |
    (?P<url>
        (?:(:?ht|f)tps?\:\/\/\S+[^\.\,\s]) # starts with  http:// https:// ftp:// ftps:// plus anything that isnt a white space character
        |
        (?:[a-z\.\_\-]+\.(?:
            com|edu|biz|gov|int|info|mil|net|org|me
            |mobi|us|ca|mx|ag|bz|gs|ms|tc|vg|eu|de
            |it|fr|nl|am|at|be|asia|cc|in|nu|tv|tw
            |jp|[a-z][a-z]\.[a-z][a-z]
        )) # some.thing.(com or edu or ...)
        (?:\:\d+)? # port number
        (?:\/\S*[^\.\,\s]?)? # path do the page, must not end with a '.'
    )
    |
    (?P<path>
        [\/\\][a-z0-9\_\-\+\~\.]+(?:[\/\\a-z0-9\_\-\+\~\.]*)[^\.\,\s]
    )
    |
    (?P<number>\$?\d+(?:\.\d+)?\%%?)
    |
    (?P<abbreviation>[A-Z]\.)+
    |
    (?P<copyright>(?:
        (?:c?opyright)
        |
        (?:\(c\))
        |
        (?:\&copy\;)
        |
        (?:\xc2\xa9)
    ))
    |
    (?P<word>[a-z0-9\-\_]+)
    # | (?P<symbol>[\.\,\?\\\|\/\:\"\'\;\(\)])
    # | (?P<symbol>.) # catch all
    # | (?P<fullstop>\.) # grab '.'s if they don't match anything else.
)
""" % ('|'.join(months),'|'.join(months))

RE_TOKENIZER = re.compile(tokenizer_pattern)
RE_ANDOR = re.compile('and\/or',re.I)
RE_COMMENT = re.compile(r'(^(?:(?P<a>\s*)(?P<b>(?:\*|\/\*|\*\/|#!|#|%|\/\/|;)+))|(?:(?P<c>(?:\*|\/\*|\*\/|#|\/\/)+)(?P<d>\s*))$)', re.I | re.M)

def test():
    text = """
        some random text with a number 3.145, a path /usr/bin/python, an email <someone@someplace.com>, a url http://stuff.com/blah/, Walter H. Mann, and a copyright &copy; /xc2/xa9 (c), May 22 1985 12:34:56.
    """

    correct = [
            'some', 'random', 'text', 'with', 'a', 'number', '3.145', 'a',
            'path', '/usr/bin/python', 'an', 'email', '<someone@someplace.com>',
            'a', 'url', 'http://stuff.com/blah/', 'Walter', 'H.', 'Mann',
            'and', 'a', 'copyright', '&copy;', '/xc2/xa9', '(c)',
            'May 22 1985', '12:34:56',
        ]

    result = findall(RE_TOKENIZER, text, True)

    if correct == result:
        print "Correct!"
        return True
    else:
        print "Test Failed! Something regressed."
        m = max([len(result), len(correct)])
        for i in range(m):
            if i >= len(correct):
                print "EXTRA[%d]: %s" % (i, str(result[i]))
            elif i >= len(result):
                print "MISSING[%d]: %s" % (i, str(correct[i]))
            elif result[i] != correct[i]:
                print "MISSMATCH[%d]:\n\t%s\n\t%s" % (i, str(correct[i]), str(result[i]))

        print result
        return False

    return False

def remove_comments(text):
    from cStringIO import StringIO
    io_text = StringIO()
    io_text.write(text)
    for iter in RE_COMMENT.finditer(text):
        gd = iter.groupdict()
        start = iter.start()
        if gd['b']:
            io_text.seek(start+len(gd['a']))
            for i in range(len(gd['b'])):
                io_text.write(' ')
        else:
            io_text.seek(start+len(gd['c']))
            for i in range(len(gd['d'])):
                io_text.write(' ')
    result = io_text.getvalue()
    io_text.close()
    return result

def findall(RE, text, tokens_only=False):
    """
    findall(Regex, text, offsets) -> list(['str_1', token_type, start_byte_1, end_byte_1], ...)

    Uses the re.finditer method to locate all instances of the search 
    string and return the string and its start and end bytes.

    Regex is a compiled regular expression using the re.compile() method.
    text is the body of text to search through.
    tokens_only is a boolean which tells findall to only return a list of string tokens.
    """
    found = []
    for iter in RE.finditer(text):
        gd = iter.groupdict()
        token_type = [k for k,v in gd.iteritems() if v!=None and k!='token']
        if len(token_type) > 1:
            raise Exception("Token matched more than one search parameter.\n\t%s" % token_type)
        if len(token_type) == 0:
            raise Exception("Token did not a token type in the regular expression. Please check your group names.\n\tGROUP DICTIONARY::%s" % gd)
        if not tokens_only:
            found.append([iter.group(), token_type[0], iter.start(), iter.end()])
        else:
            found.append(iter.group())
    return found

def parsetext(text, tokens_only=False):
    """
    parsetext(text) -> []

    Tokenizes a body of text and returns a dictionary containing
    a set of features located within the text.

    """
    stuff = {}
    
    text = RE_ANDOR.sub('and or',text)
    test = remove_comments(text)

    tokens = findall(RE_TOKENIZER, text)

    for i in xrange(len(tokens)):
        word = tokens[i][0]
        if tokens[i][1] != 'word':
            word = 'XXX%sXXX' % tokens[i][1]
        tokens[i].insert(0,word)

    if tokens_only:
        tokens = [token[0] for token in tokens]

    return tokens

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

def train_nb(data, priors=None):
    n = len(data)

    if not priors:
        priors = {'B':{}, 'I':{}, 'O':{}}

    classes = ['B', 'I', 'O',]
    features = ['current_word', 'previous_label', 'previous_word', 'next_word']
    PFC = {} # P(F_{i}|C) accessed as PFC[C][F][i] for simplicity.
    # initialize our model
    for c in classes:
        PFC[c] = {'class':priors[c].get('class',math.log(1.0/3.0)), 'min':{}}
        for f in features:
            PFC[c]['min'][f] = priors[c].get(f, -1e-10)
            PFC[c][f] = {}

    for i in xrange(n):
        (tokens, labels) = data[i]
        nn = len(tokens)
        for j in xrange(nn):
            c = labels[j]
            w = tokens[j]
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

    return PFC

def tuned_model(iob, priors=None, debug=False):
    n = len(iob)
    classes = ['B', 'I', 'O']
    features = ['class', 'current_word', 'previous_label', 'previous_word', 'next_word']
    mins = ['current_word', 'previous_label', 'previous_word', 'next_word']
    if not priors:
        priors = {}
        for c in classes:
            priors[c] = {}
            for f in features:
                priors[c][f] = math.log(1e-20)
            priors[c]['class'] = math.log(1.0/3.0)

    for j in range(20):
        for i in range(n):
            tiob = iob[:]
            tiob.pop(i)
            PFC = train_nb(tiob, priors)
            L, P = label_nb(PFC, iob[i][0], True)
            d = len(L)
            cp = {}
            for c in classes:
                cp[c] = {}
                for f in features:
                    cp[c][f] = []
            for j in range(d):
                a = L[j]
                b = iob[i][1][j]
                if a != b:
                    dif = math.exp(P[j][a]) - math.exp(P[j][b])
                    cp[b]['class'].append(dif)
                    if j>0:
                        t = iob[i][0][j-1]
                        if not PFC[b]['previous_word'].get(t,False):
                            cp[b]['previous_word'].append(1e-22)
                        if not PFC[a]['previous_word'].get(t,False):
                            cp[a]['previous_word'].append(-1e-22)
                    if j>d-1:
                        t = iob[i][0][j+1]
                        if not PFC[b]['next_word'].get(t,False):
                            cp[b]['next_word'].append(1e-22)
                        if not PFC[a]['next_word'].get(t,False):
                            cp[a]['next_word'].append(-1e-22)
                    t = iob[i][0][j]
                    if not PFC[b]['current_word'].get(t,False):
                        cp[b]['current_word'].append(1e-22)
                    if not PFC[a]['current_word'].get(t,False):
                        cp[a]['current_word'].append(-1e-22)

            for c in classes:
                for f in features:
                    if len(cp[c][f]) > 0:
                        priors[c][f] = math.exp(priors[c][f]) + sum(cp[c][f])/float(len(cp[c][f]))
                    else:
                        priors[c][f] = math.exp(priors[c][f])

            s = sum([priors[c]['class'] for c in classes])
            for c in classes:
                priors[c]['class'] = priors[c]['class']/s
            for c in classes:
                for f in features:
                    if priors[c][f]>1.0:
                        print "%s %s > 1.0" % (c,f)
                        priors[c][f] = 1.0
                    elif priors[c][f]<1e-25:
                        print "%s %s < 0.0" % (c,f)
                        priors[c][f] = 1e-25
                    priors[c][f] = math.log(priors[c][f])

    PFC = train_nb(iob, priors)
    
    return PFC

def label_nb(PFC, tokens, debug=False):
    L = []
    P = []

    classes = PFC.keys()
    features = ['current_word', 'previous_label', 'previous_word', 'next_word']

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
                p[c] += PFC[c][f].get(fv[f],PFC[c]['min'][f])
    
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

def create_model(training_data, debug=False):
    n = len(training_data)
    # need to convert the string data into BIO labels and tokens.
    parsed_data = [parsetext(text) for text in training_data]
    tokens = [[parsed_data[i][j][0] for j in xrange(len(parsed_data[i]))] for i in xrange(n)]
    bio_data = [tokens_to_BIO(tokens[i]) for i in xrange(n)]

    # create the naive Bayes model
    PFC = tuned_model(bio_data)

    model = {'id':hex(abs(hash(str(PFC)))), 'P(F|C)':PFC}

    return model

def label_file(file, model):
    PFC = model['P(F|C)']
    text = open(file).read()

    # parse the file and get the tokens
    parsed_text = parsetext(text)
    tokens = [parsed_text[j][0] for j in xrange(len(parsed_text))]

    offsets = []
    
    starts = []
    ends = []
    L = label_nb(PFC, tokens)
    for l in xrange(len(L)):
        if L[l] == 'B':
            starts.append(parsed_text[l][3])
        if l>0 and L[l-1] == 'B' and L[l] == 'O':
            starts.pop()
        elif l>0 and L[l-1] != 'O' and L[l] == 'O':
            ends.append(parsed_text[l-1][4])
        elif l>0 and L[l-1] == 'I' and L[l] == 'B':
            ends.append(parsed_text[l-1][4])
    if len(starts)>len(ends):
        ends.append(parsed_text[-1][4])

    for i in xrange(len(starts)):
        offsets.append((starts[i], ends[i], 'statement'))

    offsets.extend([(item[3], item[4], 'email') for item in parsed_text if item[2] == 'email'])
    offsets.extend([(item[3], item[4], 'url') for item in parsed_text if item[2] == 'url'])

    return offsets
