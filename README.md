simplewebfinger
===============

SimpleWebFinger is an extraordinarily simple 
[WebFinger](http://tools.ietf.org/html/rfc7033) server written in PHP 5.

Features include:

- Support for WebFinger as specified in [RFC 7033](http://tools.ietf.org/html/rfc7033)
- Multiple resources with one installation. You can use SimpleWebFinger to serve
  as many resources as you want
- Resources can be described in either the original [XML notation](http://docs.oasis-open.org/xri/xrd/v1.0/xrd-1.0.html)
  or the newer JSON notation specified in RFC 7044. SimpleWebFinger will
  translate into JSON as required
- Integration with [SimpleID](http://simpleid.koinic.net/)
- Flat files only, no database required


System Requirements
-------------------

- web server with SSL support;
- PHP version 5.1.2 or greater, with the xml extension installed

Optional requirements:

- [SimpleID](http://simpleid.koinic.net/) version 0.8 or later


Installation
------------

### 1. Download SimpleWebFinger

You can obtain the latest SimpleWebFinger release from GitHub.  Releases can
be found under the Tags tab

### 2. Move the directories to the web server

You should move the following two directories to the web server. (The other
directories are for developers only and can be safely ignored.)

#### resources

This is the resources directory, which stores the resource descriptor files.
For security purposes, this directory should be moved to a place which is
readable by the web server, but not under the document root or public HTML
directory (and thus accessible to user agents).

This directory must be readable by the web server. The directory should not be
writeable by the web server.

#### www

This is the web directory. This must be moved below the document root so that
it is accessible by users. Once this is done, the directory can be renamed to
anything you like.

### 3. Set up configuration options

Make a copy of the file `config.php.dist` in the web directory and rename it
`config.php`.

Open the file with a text editor and edit the configuration options. The file
is formatted as a plain PHP file.

The file contains comments explaining what each configuration option does.

### 4. Redirect `/.well-known/webfinger`

The WebFinger protocol requires SimpleWebFinger to be served from the URL
`/.well-known/webfinger`.  Therefore we need to redirect this URL to the
`index.php` file in the web directory.

For example, if you are using Apache, you may need to modify the Apache
configuration by adding the following line:

    Alias /.well-known/webfinger /path/to/simplewebfinger/www/index.php
    
(Note `/path/to/simplewebfinger/www/index.php` is the physical file location
on the web server, and not a URL path.)

You may also use a redirect instead of an alias.


Usage
-----

### Describing resources

Resource descriptor files are stored in the resources directory.  The name
of each file is the [URL encoded](http://www.ietf.org/rfc/rfc3986.txt)
representation of the resource's URI, followed by either `.xml` for
[XML formatted XRD files](http://docs.oasis-open.org/xri/xrd/v1.0/xrd-1.0.html)
or `.json` for JSON formatted JRD files.

For example, the XRD file for the resource `acct:bob@example.com` would
have the file name `acct%3Abob%40example.com.xml`.

See the resources directory for examples.

Note that certain features of XRD do not have equivalents in JRD.  These
features will be ignored when translating the XRD document into JRD.

### SimpleID integration

SimpleWebFinger can be used as the WebFinger server for SimpleID.
SimpleWebFinger can call SimpleID to look up an identity and creates a JRD
document for that identity.  The JRD document created contains a pointer
to the SimpleID installation.

To enable SimpleID integration, edit the `SIMPLEWEBFINGER_SIMPLEID_WWW_DIR`
parameter in `config.php` to point to the SimpleID web directory.

Security Considerations
-----------------------

SimpleWebFinger does not test whether the connection is secured.  You are
responsible for ensuring that the conneciton to SimpleWebFinger is under
HTTPS, as required by the specification.

SimpleWebFinger does not implement any mechanism to protect the server from
malicious usage, including harvesting or denial-of-service attacks.  You are
responsible for implementing mechanisms, such as packet filters and IP address
rate-limiting to mitigate against such malicious usage.  See [RFC 7033](http://tools.ietf.org/html/rfc7033)
for further details.

Licensing
---------

Licensing information for SimpleWebFinger can be found in the file
COPYING.txt.

SimpleWebFinger uses the [SimpleXRD library](https://github.com/kelvinmo/simplexrd).
SimpleXRD is licensed under the Modified BSD Licence.  Licensing terms can
be found in the header to the file `simplexrd.class.php`.