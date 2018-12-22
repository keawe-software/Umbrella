#!/usr/bin/python
# -*- coding: utf-8 -*-
import sys
sys.path.append("/var/www/tests")
from test_routines import *

import urlparse

# check redirect to login for users that are not logged in
r = requests.get("http://localhost/project/view",allow_redirects=False)
expectRedirect(r,'http://localhost/user/login?returnTo=http%3A%2F%2Flocalhost%2Fproject%2Fview')


# login
admin_session = requests.session();
r = admin_session.post('http://localhost/user/login', data={'email':'admin', 'pass': 'admin'},allow_redirects=False)

# get token
r = admin_session.post('http://localhost/user/login?returnTo=http%3A%2F%2Flocalhost%2Fproject%2Fview',allow_redirects=False)
expect('location' in r.headers)
redirect = r.headers.get('location');

expect('http://localhost/project/view?token=' in redirect)
param = urlparse.parse_qs(urlparse.urlparse(redirect).query)
token=param['token'][0]

# create new admin_session to test token function
admin_session = requests.session()

# redirect should contain a token in the GET parameters, thus the page should redirect to the same url without token parameter
r = admin_session.get(redirect,allow_redirects=False)
expectRedirect(r,'http://localhost/project/view');

# without a project id, an error should be displayed
r = admin_session.get('http://localhost/project/view',allow_redirects=False)
expect('<div class=errors>' in r.text)
expect('Keine Projekt-ID angegeben!' in r.text)

# non-existing project id, should throw error
r = admin_session.get('http://localhost/project/9999/view',allow_redirects=False)
expect('<div class=errors>' in r.text)
expect('Sie sind nicht an diesem Projekt beteiligt!' in r.text)

r = admin_session.get('http://localhost/project/1/view',allow_redirects=False)
expect('<h1>testproject</h1>' in r.text)
expect('<p>this is the description</p>' in r.text)
expect('admin (Eigentümer)' in r.htext)



# TODO: sicherstellen, dass der Nutzer auch auf existierende Projekte keinen Zugriff hat, wenn er nicht mitglied ist

print ('done')