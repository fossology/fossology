import lucense.database as database
import lucene
import lucense.library as libary
from django.http import HttpResponse, HttpResponseRedirect
import os
import math
from operator import itemgetter
from xml.sax.saxutils import escape

# set path for opennlp
database.library.opennlp.PATH = os.getcwd()+'/../'
database.library.opennlp.classpath()

# Start the JVM so lucene doesn't crash:(
lucene.initVM(lucene.CLASSPATH)

# this stems and splits our files for us:)
analyzer = lucene.SnowballAnalyzer("English",lucene.StopAnalyzer.ENGLISH_STOP_WORDS)


DB = database.load('../DB.dat',analyzer)

# fix funky characters so we can print our data into a nice xml formatted
# document
def htmlentities(u):
    result = []
    for c in u:
        if ord(c) < 128:
		if ord(c) > 31:
            		result.append(c)
        else:
            result.append('&%s;' % htmlentitydefs.codepoint2name[ord(c)])
    return ''.join(result)

# converts crazy unicode stuff before converting funky characters
def escape2(str):
	s = str.encode('ascii','ignore')
	s2 = escape(s)
	s3 = htmlentities(s2)
	return s3

def xml_style_sheet(request):
    response = HttpResponse(mimetype='application/xml')
    response.write(open('../default.xsl').read())
    return response

def index(request):
    response = HttpResponse()

    html = '''<html>
    <head><title>Lucense License Tester</title></head>
    <body>
        <form enctype="multipart/form-data" action="print/" method="POST">
        <input type="file" name="upload_file">
        <input type="submit">
        </form>
    </body>
    </html>\n'''

    response.write(html)
    return response

def post(request):
    response = HttpResponse()
    if request.FILES.get('upload_file'):
        a = request.FILES['upload_file'].read()
    else:
        a = request.raw_post_data
    fname = '/tmp/%s' % (abs(hash(a)))
    open(fname,'w').write(a)

    sentences,matches,unique_hits,cover,maximum,hits,score,fp = database.calculate_matches(DB,fname,thresh=0.7)

    os.popen('rm %s' % fname).read()

    out = []
    #s = library.sortdictionary(score)
    s = sorted(score.iteritems(), key=itemgetter(1),reverse=True)
    for lic,scr in s:
        out.append(lic)
    if len(out)==0:
        out.append('None')

    response.write('%s' % '\n'.join(out))
    return response

def print_file(request):
    response = HttpResponse(mimetype='application/xml')
    if request.FILES.get('upload_file'):
        a = request.FILES['upload_file'].read()
    else:
        a = request.raw_post_data
    fname = '/tmp/%s' % (abs(hash(a)))
    open(fname,'w').write(a)

    name = 'Uploaded Document'
    f = '.'

    sentences,matches,unique_hits,cover,maximum,hits,score,fp = database.calculate_matches(DB,fname,thresh=0.7)

    os.popen('rm %s' % fname).read()

    xml = '<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n'
    xml += '<?xml-stylesheet type=\"text/xsl\" href=\"default.xsl\"?>\n'
    xml += '<analysis>\n'
    xml += '<name>%s</name>\n' % escape2(name)
    xml += '<path>%s</path>\n' % escape2(f)
    xml += '<statistics>\n'
    s = sorted(score.iteritems(), key=itemgetter(1), reverse=True)
    for lic,scr in s:
        xml += '<license>\n<name>%s</name>\n<rank>%2.1f</rank>\n</license>\n' % (escape2(lic),(scr*100.0))
    xml += '</statistics>\n'
    xml += '<breakdown>\n'
    for j in xrange(len(sentences)):
        s = sentences[j]
        xml += '<sentence>\n'
        xml += '<position>%s</position>\n' % (j)
        xml += '<text>%s</text>\n' % escape2(s)
        xml += '<matches>\n'
        for k in hits[j]:
            xml += '<license>\n'
            xml += '<rank>%1.2f</rank>\n' % (matches[j][k][1])
            xml += '<name>%s</name>\n' % escape2(k)
            if k=='Unknown':
                xml += '<position>%s</position>\n' % (0)
                xml += '<text>%s</text>\n' % escape2('Text not found in corpus.')
            else:
                xml += '<position>%s</position>\n' % (DB._to_position[matches[j][k][0]])
                xml += '<text>%s</text>\n' % escape2(DB.sentences[matches[j][k][0]])
            xml += '</license>\n'
        xml += '</matches>\n'
        xml += '</sentence>\n'
    xml += '</breakdown>\n'
    xml += '</analysis>\n'

    response.write('%s' % xml)
    return response
