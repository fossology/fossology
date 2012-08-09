#!/usr/bin/python -u
"""
  Functional testing module 
  
  Module uses a simple xml file to describe a set of functional tests. This was
  originally written for the FOSSology scheduler and as such is tailored to
  testing that piece of software.
 
  ==============================================================================
  Copyright (C) 2012 Hewlett-Packard Development Company, L.P.
 
  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  version 2 as published by the Free Software Foundation.
 
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
  ==============================================================================
"""

from xml.dom.minidom import getDOMImplementation
from xml.dom.minidom import parseString
from xml.dom import Node
from optparse import OptionParser
import ConfigParser
import subprocess
import functools
import signal
import shlex
import time
import re
import os

################################################################################
### utility ####################################################################
################################################################################
defsReplace = re.compile('{([^{}]*)}')
defsSplit   = re.compile('([^\s]+):([^\s]+)')

class DefineError(Exception):
  """ Error class used for missing definitions in the xml file """
  def __init__(self, value):
    self.value = value
  def __str__(self):
    return repr(self.value)

class TimeoutError(Exception):
  """ Error class used when a test suite takes too long to run """
  pass

def timeout(func, maxRuntime):
  """
  @brief Allows the caller to set a max runtime for a particular function call.
  
  @param func        the function that will have the max runtime
  @param maxRuntime  the max amount of time alloted to the function in minutes
  
  Returns a Boolean, True indicating that the function finished, False otherwise
  """
  
  def timeout_handler(signum, frame):
    raise TimeoutError()
  
  signal.signal(signal.SIGALRM, timeout_handler)
  signal.alarm(maxRuntime * 60)
  
  try:
    func()
  except TimeoutError:
    return False
  return True

################################################################################
### class that handles running a test suite ####################################
################################################################################

class testsuite:
  """
  The testsuite class is used to deserialize a test suite from the xml file,
  run the tests and report the results to another xml document.
  
  name     the name of the test suite
  defs     a map of strings to values used to do variables replacement
  setup    list of Actions that will be taken before running the tests
  cleanup  list of Actions that will be taken after running the tests
  tests    list of Actions that are the actual tests
  subpro   list of processes that are running concurrently with the tests
  """
  
  def __init__(self, node):
    """
    Constructor for the testsuite class. This will deserialize the testsuite
    from the xml file that describes all the tests. For each element in the
    setup, and cleanup and action will be created. For each element under each
    <test></test> tag an action will be created.
    
    This will also grab the definitions of variables for the self.defines map. The
    variable substitution will be performed when the definition is loaded from
    the file.
    
    Returns nothing
    """
    
    defNode = node.getElementsByTagName('definitions')[0]
    definitions = defNode.attributes
    
    self.name = node.getAttribute('name')
    
    self.defines = {}
    self.defines['pids'] = {}
    
    # get variable definitions
    for i in xrange(definitions.length):
      if definitions.item(i).name not in self.defines:
        self.defines[definitions.item(i).name] = self.substitute(definitions.item(i).value, defNode)
    
    self.setup    = []
    self.cleanup  = []
    self.tests    = []
    self.subpro   = []
    self.dbresult = None
    
    # parse all actions that will be taken during the setup phase
    if len(node.getElementsByTagName('setup')) != 0:
      setup = node.getElementsByTagName('setup')[0]
      for action in [curr for curr in setup.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        self.setup.append(self.createAction(action))
    
    # parse all actions that will be taken during the cleanup phase
    if len(node.getElementsByTagName('cleanup')) != 0:
      cleanup = node.getElementsByTagName('cleanup')[0]
      for action in [curr for curr in cleanup.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        self.cleanup.append(self.createAction(action))
    
    # parse all actions that will be taken during the testing phase
    for test in node.getElementsByTagName('test'):
      newTest = (test.getAttribute('name'), [])
      for action in [curr for curr in test.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        newTest[1].append(self.createAction(action))
      self.tests.append(newTest) 
  
  def substitute(self, string, node = None):
    """
    Simple function to make calling processVariable a lot cleaner
    
    Returns the string with the variables correctly substituted
    """
    while defsReplace.search(string):
      string = defsReplace.sub(functools.partial(self.processVariable, node), string)
    return string
  
  def processVariable(self, node, match):
    """
    Function passed to the regular expression library to replace variables in a
    string from the xml file.
    doc, dest, "")
      return (1, 0)
    The regular expression used is "{([^\s]*?)}". This will match anything that
    doesn't contain any whitespace and falls between two curly braces. For
    example "{hello}" will match, but "{hello goodbye}" and "hello" will not.
    
    Any variable name that starts with a "$" has a special meaning. The text
    following the "$" will be used as a shell command and executed. The
    "{$text}" will be replaced with the output of the shell command. For example
    "{$pwd}" will be replaced with the output of the shell command "pwd".
    
    If a variable has a ":" in it, anything that follows the ":" will be used
    to index into the associative array in the definitions map. For example
    "{pids:0}" will access the element that is mapped to the string "0" in the
    associative array that is as the mapped to the string "pids" in the defs
    map.
    
    Returns the replacement string
    """
    name = match.group(1)
    
    # variable begins with $, replace with output of shell command
    if name[0] == '$':
      process = os.popen(name[1:], 'r')
      ret = process.read()
      process.close()
      return ret[:-1]
    
    # variable contains a ":", access defs[name] as an associative array or dictionary
    arrayMatch = defsSplit.match(name)
    if arrayMatch:
      name  = arrayMatch.group(1)
      index = self.substitute(arrayMatch.group(2), node)
      
      if not isinstance(self.defines[name], dict):
        raise DefineError('"{0}" is not a dictionary in testsuite "{1}"'.format(name, self.name))
      if name not in self.defines:
        if node and node.hasAttribute(name):
          self.defines[name] = self.substitute(node.getAttribute(name))
        else:
          raise DefineError('"{0}" not defined in testsuite "{1}"'.format(name, self.name))
      if index not in self.defines[name]:
        raise DefineError('"{0}" is out of bounds for "{1}.{2}"'.format(index, self.name, name))
      return self.defines[name][arrayMatch.group(2)]
    
    # this is a simply definition access, check validity and return the result
    if name not in self.defines:
      if node and node.hasAttribute(name):
        self.defines[name] = self.substitute(node.getAttribute(name), node)
      else:
        raise DefineError('"{0}" not defined in testsuite "{1}"'.format(name, self.name))
    return self.defines[name]
  
  def failure(self, doc, dest, type, value):
    """
    Puts a failure node into an the results document
    
    Return nothing
    """
    if doc and dest:
      fail = doc.createElement('failure')
      fail.setAttribute('type', type)
      
      text = doc.createTextNode(value)
      fail.appendChild(text)
      
      dest.appendChild(fail)
  
  ###############################
  # actions that tests can take #
  ###############################
  
  def createAllActions(self, node):
    return [self.createAction(child) for child in node.childNodes if child.nodeType == Node.ELEMENT_NODE]
  
  def createAction(self, node):
    """
    Creates an action given a particular test suite and xml node. This uses
    simple python reflection to get the method of the testsuite class that has
    the same name as the xml node tag. The action is a functor that can be
    called later be another part of the test harness.
    
    To write a new type of action write a function with the signature:
      actionName(self, source_node, xml_document, destination_node)
    
    * The source_node is the xml node that described the action, this node
      should describe everything that is necessary for the action to be
      performed. This is passed to the action when the action is created.
    * The xml_document is the document that the test results are being written
      to. This is passed to the action when it is called, not during creation.
    * The destination_node is the node in the results xml document that this
      particular action should be writing its results to. This is passed in when
      the action is called, not during creation.
    
    The action should return the number of tests that it ran and the number of
    failures that it experienced. A failing action has different meanings
    during different parts of the code. During setup, a failing action
    indicates that the setup is not ready to proceed. Failing actions during
    setup will be called repeatedly once every five seconds until they no
    longer register a failure. Failing actions during testing indicate a
    failing test. The failure will be reported to results document, but the
    action should still call the failure method to indicate in the results
    document why the failure happened. During cleanup what an action returns is
    ignored.
    
    Returns the new action
    """
    def action_wrapper(action, node, doc, dest):
      print '.',# node.nodeName,
      return action(node, doc, dest)
    
    if not hasattr(self, node.nodeName):
      raise DefineError('testsuite "{0}" does not have an "{1}" action'.format(self.name, node.nodeName))
    attr = getattr(self, node.nodeName)
    return functools.partial(action_wrapper, attr, node)
  
  def concurrently(self, node, doc, dest):
    """
    Action
    
    Attributes:
      command [required]: the name of the process that will be executed
      params  [required]: the command line parameters passed to the command
    
    This executes a shell command concurrently with the testing harness. This
    starts the process, sleeps for a second and then checks the pid of the
    process. The pid will be appended to the list of pid's in the definitions
    map. This action cannot fail as it does not check any of the results of the
    process that was created.
    
    Returns True
    """
    command  = self.substitute(node.getAttribute('command'))
    params   = self.substitute(node.getAttribute('params'))
    
    cmd = shlex.split("{0} {1}".format(command, params))
    proc = subprocess.Popen(cmd, 0)
    time.sleep(1)
    self.subpro.append(proc)
    self.defines['pids'][str(len(self.defines['pids']))] = str(proc.pid)
    
    return (1, 0)
  
  def sequential(self, node, doc, dest):
    """
    Action
    
    Attributes:
      command [required]: the name of the process that will be executed
      params  [required]: the command line parameters passed to the command
      result  [optional]: what the process should print to stdout
      retval  [optional]: what the exit value of the process should be
    
    This executes a shell command synchronously with the testing harness. This
    starts the process, grabs anything written to stdout by the process and the
    return value of the process. If the results and retval attributes are
    provided, these are compared with what the process printed/returned. If
    the results or return value do not match, this will return False.
    
    Returns True if the results and return value match those provided
    """
    command  = self.substitute(node.getAttribute('command'))
    params   = self.substitute(node.getAttribute('params'))
    expected = self.substitute(node.getAttribute('result'))
    retval   = self.substitute(node.getAttribute('retval'))
    
    cmd  = "{0} {1}".format(command, params)
    proc = subprocess.Popen(cmd, 0, shell = True, stdout = subprocess.PIPE, stderr = subprocess.PIPE)
    
    result = proc.stdout.readlines()
    if len(result) != 0 and len(expected) != 0 and result[0].strip() != expected:
      #print
      #print expected
      #print result[0].strip()
      self.failure(doc, dest, "ResultMismatch",
          "expected: '{0}' != result: '{1}'".format(expected, result[0].strip()))
      return (1, 1)
    
    proc.wait()
    
    if len(retval) != 0 and proc.returncode != int(retval):
      #print
      #print retval, " != ", proc.returncode
      self.failure(doc, dest, "IncorrectReturn", "expected: {0} != result: {1}".format(retval, proc.returncode))
      return (1, 1)
    return (1, 0)
  
  def sleep(self, node, doc, dest):
    """
    Action
    
    Attributes:
      duration [require]: how long the test harness should sleep for
    
    This action simply pauses execution of the test harness for duration
    seconds. This action cannot fail and will always return True.
    
    Returns True
    """
    duration = node.getAttribute('duration')
    time.sleep(int(duration))
    return (1, 0)
  
  def loadConf(self, node, doc, dest):
    """
    Action
    
    Attributes:
      directory [required]: the directory location of the fossology.conf file
    
    This loads the configuration and VERSION data from the fossology.conf file
    and the VERSION file. It puts the information in the definitions map.
    
    Returns True
    """
    dir = self.substitute(node.getAttribute('directory'))
    
    config = ConfigParser.ConfigParser()
    config.readfp(open(dir + "/fossology.conf"))
    
    self.defines["FOSSOLOGY"] = {}
    self.defines["BUILD"] = {}
    
    self.defines["FOSSOLOGY"]["port"]  = config.get("FOSSOLOGY", "port")
    self.defines["FOSSOLOGY"]["path"]  = config.get("FOSSOLOGY", "path")
    self.defines["FOSSOLOGY"]["depth"] = config.get("FOSSOLOGY", "depth")
    
    config.readfp(open(dir + "/VERSION"))
    
    self.defines["BUILD"]["VERSION"] = config.get("BUILD", "VERSION")
    self.defines["BUILD"]["SVN_REV"] = config.get("BUILD", "SVN_REV")
    self.defines["BUILD"]["BUILD_DATE"] = config.get("BUILD", "BUILD_DATE")
    
    return (1, 0)
  
  def loop(self, node, doc, dest):
    """
    Action
    
    Attributes:
      varname    [required]: the name of the variable storing the current iteration
      values     [optional]: the values that the variable should take
      iterations [optional]: the number of iterations the loop with take
    
    This action actually executes the actions contained within it. This will loop
    over the values specified or loop for the number of iterations specified. The
    current value of the variable will be stored in the definitions mapping with
    the value varname. While both "values" and "iterations" are optional
    parameters, one of them is required to be provided.
    """
    varname    = self.substitute(node.getAttribute('varname'))
    values     = self.substitute(node.getAttribute('values'))
    iterations = self.substitute(node.getAttribute('iterations'))
    
    actions = self.createAllActions(node)
    
    tests  = 0
    failed = 0
    
    if values:
      for value in values.split(','):
        self.defines[varname] = value.strip()
        for action in actions:
          ret = action(doc, dest)
          tests  += ret[0]
          failed += ret[1]
    else:
      for i in xrange(int(iterations)):
        self.defines[varname] = str(i)
        for action in actions:
          ret = action(doc, dest)
          tests  += ret[0]
          failed += ret[1]
    
    del self.defines[varname]
    return (tests, failed)
  
  def upload(self, node, doc, dest):
    """
    Action
    
    Attributes:
      file [required]: the file that will be uploaded to the fossology database
    
    This action uploads a new file into the fossology test(hopefully) database
    so that an agent can work with it. This will place the upload_pk for the
    file in the self.sefs map under the name ['upload_pk'][index] where the
    index is the current number of elements in the ['upload_pk'] mapping. So the
    upload_pk's for the files should showup in the order they were uploaded.
    
    Returns True if and only if cp2foss succeeded
    """
    file = self.substitute(node.getAttribute('file'))
    
    cmd = self.substitute('{pwd}/cli/cp2foss -c {config} --user {user} --password {pass} ' + file)
    proc = subprocess.Popen(cmd, 0, shell = True, stdout = subprocess.PIPE, stderr = subprocess.PIPE)
    proc.wait()
    
    if proc.returncode != 0:
      return (1, 1)
    
    result = proc.stdout.readlines()
    if 'upload_pk' not in self.defines:
      self.defines['upload_pk'] = {}
    self.defines['upload_pk'][str(len(self.defines['upload_pk']))] = re.search(r'\d+', result[-1]).group(0)
    
    return (1, 0)
  
  def schedule(self, node ,doc, dest):
    """
    Action
    
    Attributes:
      upload [required]: the index of the upload in the ['upload_pk'] mapping
      agents [optional]: comma seperated list of agent to schedule. If this is
          not specified, all agents will be scheduled
    
    This action will schedule agents to run on a particular upload.
    
    Returns True if and only if fossjobs succeeded
    """
    upload = self.substitute(node.getAttribute('upload'))
    agents = self.substitute(node.getAttribute('agents'))
    
    if not agents:
      agents = ""
    
    cmd = self.substitute('{pwd}/cli/fossjobs -c {config} --user {user} --password {pass} -U ' + upload + ' -A ' + agents)
    proc = subprocess.Popen(cmd, 0, shell = True, stdout = subprocess.PIPE, stderr = subprocess.PIPE)
    proc.wait()
    
    if proc.returncode != 0:
      return (1, 1)
    return (1, 0)
  
  def database(self, node, doc, dest):
    """
    Action
    
    Attributes:
      sql    [required]: the sql that will be exectued
    
    Sub nodes:
      
    
    This action will execute an sql statement on the relevant database. It can
    check that the results of the sql were correct.
    
    Returns True if results aren't expected or the results were correct
    """
    sql      = self.substitute(node.getAttribute('sql'))
    
    cmd = 'psql --username={0} --host=localhost --dbname={1} --command="{2}" -tA'.format(
        self.defines["dbuser"], self.defines['config'].split('/')[2], sql)
    proc = subprocess.Popen(cmd, 0, shell = True, stdout = subprocess.PIPE)
    proc.wait()
    
    total  = 0
    failed = 0
    
    self.dbresult = [str.split() for str in proc.stdout.readlines()]
    for action in self.createAllActions(node):
      ret = action(doc, dest)
      total += ret[0]
      failed += ret[1]
    
    del self.dbresult
    self.dbresult = None
    return (total, failed)
  
  def dbequal(self, node, doc, dest):
    """
    Action
    
    Attributes:
      row [required]: the row of the database results
      col [required]: the column of the database results
      val [required]: the expected value found at that row and column
    
    checks if a particular row and column in the results of a database call are
    an expected value. This fails if the correct value is not reported by the
    database.
    
    returns True if the expected is the same as the result
    """
    row = int(self.substitute(node.getAttribute('row')))
    col = int(self.substitute(node.getAttribute('col')))
    val = self.substitute(node.getAttribute('val'))
    
    if not self.dbresult:
      raise DefineError("dbresult action must be within a database action")
    result = self.dbresult
    if len(result) <= row:
      self.failure(doc, dest, "DatabaseMismatch", "Index out of bounds: {0} > {1}".format(row, len(result)))
      return (1, 1)
    if len(result[row]) <= col:
      self.failure(doc, dest, "DatabaseMismatch", "Index out of bounds: {0} > {1}".format(col, len(result[row])))
      return (1, 1)
    if val != result[row][col]:
      self.failure(doc, dest, "DatabaseMismatch", "[{2}, {3}]: expected: {0} != result: {1}".format(val, result[row][col], row, col))
      return (1, 1)
    return (1, 0)
  
  ################################
  # run tests and produce output #
  ################################
  
  def performTests(self, suiteNode, document, fname):
    """
    Runs the tests and writes the output to the results document.
    
    Returns nothing
    """
    failures     = 0
    tests        = 0
    totalasserts = 0
    
    print "start up",
    for action in self.setup:
      while action(None, None)[1] != 0:
        time.sleep(5)
    
    print "tests",
    for test in self.tests:
      assertions = 0
      testNode = document.createElement("testcase")
      
      testNode.setAttribute("class", test[0])
      testNode.setAttribute("name", test[0])
      
      starttime = time.time()
      for action in test[1]:
        res = action(document, testNode)
        assertions += res[0]
        failures += res[1]
      runtime = (time.time() - starttime)
      
      testNode.setAttribute("assertions", str(assertions))
      testNode.setAttribute("time", str(runtime))
      
      tests += 1
      
      totalasserts += assertions
      
      suiteNode.appendChild(testNode)
    
    print " clean up",
    for action in self.cleanup:
      action(None, None)
    
    for process in self.subpro:
      process.wait()
      
    suiteNode.setAttribute("failures", str(failures))
    suiteNode.setAttribute("tests", str(tests))
    suiteNode.setAttribute("assertions", str(totalasserts))
    print
 
################################################################################
### MAIN #######################################################################
################################################################################

def main():
  usage = "usage: %prog [options]"
  parser = OptionParser(usage = usage)
  parser.add_option("-t", "--tests",    dest = "testfile",   help = "The xml file to pull the tests from")
  parser.add_option("-r", "--results",  dest = "resultfile", help = "The file to output the junit xml to" )
  parser.add_option("-s", "--specific", dest = "specific",   help = "Only run the test with this particular name")
  
  (options, args) = parser.parse_args()
  
  testFile = open(options.testfile)
  dom = parseString(testFile.read())
  dir = os.getcwd()
  
  os.chdir('../../..')
  
  setupNode   = dom.firstChild.getElementsByTagName('setup')[0]
  cleanupNode = dom.firstChild.getElementsByTagName('cleanup')[0]
  
  resultsDoc = getDOMImplementation().createDocument(None, "testsuites", None)
  top_output = resultsDoc.documentElement
  
  maxRuntime = int(dom.firstChild.getAttribute("timeout"))
  
  for suite in dom.firstChild.getElementsByTagName('testsuite'):
    if not suite.hasAttribute("disable") and (not options.specific or suite.getAttribute("name") == options.specific):
      suiteNode = resultsDoc.createElement("testsuite")
      errors = 0
      
      suiteNode.setAttribute("name", suite.getAttribute("name"))
      suiteNode.setAttribute("errors", "0")
      suiteNode.setAttribute("time", "0")
      
      try:
        curr = testsuite(suite)
        
        setup   = curr.createAllActions(setupNode)
        cleanup = curr.createAllActions(cleanupNode)
        
        curr.setup   = setup + curr.setup
        curr.cleanup = cleanup + curr.cleanup
        
        starttime = time.time()
        print "{0: >15} ::".format(suite.getAttribute("name")),
        if not timeout(functools.partial(curr.performTests, suiteNode, resultsDoc, testFile.name), maxRuntime):
          errors += 1
          errorNode = resultsDoc.createElement("error")
          errorNode.setAttribute("type", "TimeOut")
          errorNode.appendChild(resultsDoc.createTextNode("Test suite took too long to run."))
          suiteNode.appendChild(errorNode)
        runtime = (time.time() - starttime)
        
        suiteNode.setAttribute("time", str(runtime))
        
      except DefineError as detail:
        errors += 1
        errorNode = resultsDoc.createElement("error")
        errorNode.setAttribute("type", "DefinitionError")
        errorNode.appendChild(resultsDoc.createTextNode("DefineError: {0}".format(detail.value)))
        suiteNode.appendChild(errorNode)
        
      finally:
        suiteNode.setAttribute("errors", str(errors))
        top_output.appendChild(suiteNode)
  
  os.chdir(dir);
  
  output = open(options.resultfile, 'w')
  resultsDoc.writexml(output, "", "  ", "\n")
  output.close()
  
  os.chdir(dir)

if __name__ == "__main__":
  main()
