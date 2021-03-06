#!/usr/bin/python
import os
import requests
import sqlite3
import urlparse
import json

CRED = '\033[91m'
CYEL = '\033[33m'
CEND = '\033[0m'
error_count = 0

# next three lines allow unicode handling
import sys
from multiprocessing import Condition
reload(sys)
sys.setdefaultencoding('utf8')

def abortX():
    global error_count
    error_count -= 1
    if error_count < 1:
         assert(False)


def expect(r,text=None):
    if text is None:
        # use first parameter as boolean
        assert r 
    else:
        if text not in r.text:
            print r.text
            print CYEL+'expected text not found: '+CRED+text+CEND
            abortX()
        
    sys.stdout.write('.')
    sys.stdout.flush()
    
def expectError(response,message):
    if '<div class="errors">' not in response.text:
        print response.text
        print CYEL+'error tag expected, but not found!'+CEND
        abortX()

    if message not in response.text:
        print response.text
        print CYEL+'error '+CRED+message+CYEL+' expected, but other text found!'+CEND
        abortX()

def expectInfo(response,message):
    if '<div class="infos">' not in response.text:
        print response.text
        print CYEL+'info tag expected, but not found!'+CEND
        abortX()
    if message not in response.text:
        print response.text
        print CYEL+'info '+CRED+message+CYEL+' expected, but other text found!'+CEND
        abortX()
        
def expectJson(response,json_string):
    j1 = json.loads(json_string)
    j2 = json.loads(response.text)
    if j1 == j2:
        sys.stdout.write('.')
    else:
        print ''
        print 'expected json: '+json.dumps(j1)
        print '     got json: '+json.dumps(j2)
        abortX()
        
def expectNot(r,text):
    if text in r.text:
        print r.text
        print CYEL+'found unexpected text: '+CRED+text+CEND
        abortX()
        
    sys.stdout.write('.')
    sys.stdout.flush()

def expectRedirect(response,url):
    keys = response.headers.keys()
    if ('Location' in keys):
        sys.stdout.write('.')
    else:
        print ''
        print CYEL+'response:'+CEND
        print response.text
        print CYEL+'No Location header set, but '+CRED+url+CYEL+' expected'+CEND
        abortX()
        
    if response.headers.get('Location') == url:
        sys.stdout.write('.')
        sys.stdout.flush()
    else:
        print CYEL+'Expected redirect to '+CRED+url+CYEL+', but found '+CRED+response.headers.get('Location')+CEND
        abortX()

def expectWarning(response,message):
    if '<div class="warnings">' not in response.text:
        print response.text
        print CYEL+'warnings tag expected, but not found!'+CEND
        abortX()
    if message not in response.text:
        print response.text
        print CYEL+'warning '+CRED+message+CYEL+' expected, but other text found!'+CEND
        abortX()

def params(url):
    return urlparse.parse_qs(urlparse.urlparse(url).query)

def getSession(login, password, module):
    session = requests.session();
    r = session.post('http://localhost/user/login', data={'username':login, 'pass': password},allow_redirects=False)

    # get token
    r = session.get('http://localhost/user/login?returnTo=http://localhost/'+module+'/',allow_redirects=False)
    expect('Location' in r.headers)
    redirect = r.headers.get('Location');

    expect('http://localhost/'+module+'/?token=' in redirect)
    prefix,token=redirect.split('=')

    # create new session to test token function
    session = requests.session()
    
    # redirect should contain a token in the GET parameters, thus the page should redirect to the same url without token parameter
    r = session.get(redirect,allow_redirects=False)
    expectRedirect(r,'http://localhost/'+module+'/');
    
    return session,token