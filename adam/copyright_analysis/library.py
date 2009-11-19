import re

def findall(RE, text):
    found = []
    for iter in RE.finditer(text):
        found.append([iter.group(), iter.start(), iter.end()])
    return found

months = ['JAN','FEB','MAR','MAY','APR','JUL','JUN','AUG','OCT','SEP','NOV','DEC','January', 'February', 'March', 'April', 'June', 'July', 'August', 'September', 'SEPT', 'October', 'November', 'December',]
RE_ANDOR = re.compile('and\/or',re.I)
RE_COMMENT = re.compile('\s([\*\/\#\%\!\@]+)')
RE_EMAIL = re.compile('([A-Za-z0-9\-_\.\+]+@[A-Za-z0-9\-_\.\+]+\.[A-Za-z]+)')
RE_URL   = re.compile('([A-Za-z0-9]+://[A-Za-z0-9\/#&\?\.\+\_]*|www[A-Za-z0-9\/#&\?\.\+\_]*)')
RE_PATH  = re.compile('([^\s\*]*[\\\/][^\s\*]+|[^\s\*]+[\\\/][^\s\*]*)')
RE_YEAR  = re.compile('(19[0-9][0-9]|20[0-9][0-9])')
RE_DATE = re.compile('(((\d+)[\ ]*(%s)[,\ ]*(\d+))|((%s)[\ ]*(\d+)[\ ,]*(\d+))|((\d+)(\ *[-\/\.]\ *)(\d+)(\ *[-\/\.]\ *)(\d+)))' % ('|'.join(months),'|'.join(months)), re.I)
RE_TIME = re.compile('(\d\d\ *:\ *\d\d\ *:\ *\d\d(\ *\+\d\d\d\d)?)')
RE_FLOAT = re.compile('(\d+\.\d+)')
RE_COPYRIGHT = re.compile('(\([c]\)|c?opyright|\&copy\;)',re.I)
RE_START = re.compile('(<s>)')
RE_END = re.compile('(<\/s>)')
RE_TOKEN = re.compile('([A-Za-z0-9]+)')

def parsetext(text):
    stuff = {}
    
    text = RE_ANDOR.sub('and or',text)
    text = RE_COMMENT.sub('',text)
    stuff['start'] = RE_START.findall(text)
    text = RE_START.sub(' XXXstartXXX ', text)
    stuff['end'] = RE_END.findall(text)
    text = RE_END.sub(' XXXendXXX ', text)
    stuff['email'] = RE_EMAIL.findall(text)
    text = RE_EMAIL.sub(' XXXemailXXX ', text)
    stuff['url'] = RE_URL.findall(text)
    text = RE_URL.sub(' XXXurlXXX ', text)
    stuff['path'] = RE_PATH.findall(text)
    text = RE_PATH.sub(' XXXpathXXX ', text)
    stuff['date'] = RE_DATE.findall(text)
    text = RE_DATE.sub(' XXXdateXXX ', text)
    stuff['time'] = RE_TIME.findall(text)
    text = RE_TIME.sub(' XXXtimeXXX ', text)
    stuff['year'] = RE_YEAR.findall(text)
    text = RE_YEAR.sub(' XXXyearXXX ', text)
    stuff['float'] = RE_FLOAT.findall(text)
    text = RE_FLOAT.sub(' XXXfloatXXX ', text)
    stuff['copyright'] = RE_COPYRIGHT.findall(text)
    text = RE_COPYRIGHT.sub(' XXXcopyrightXXX ', text)
    stuff['tokens'] = RE_TOKEN.findall(text)

    return stuff

def replace_placeholders(tokens,stuff):
    t = tokens[:]
    n = len(t)
    for needle in ['XXXcopyrightXXX', 'XXXfloatXXX', 'XXXyearXXX', 'XXXtimeXXX',
            'XXXdateXXX', 'XXXpathXXX', 'XXXurlXXX', 'XXXemailXXX',]:
        count = 0
        for i in range(n):
            if t[i] == needle:
                t[i] = stuff[needle.replace('X','')][count]
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

