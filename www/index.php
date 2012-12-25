<?php
/*
 * SimpleWebFinger
 *
 * Copyright (C) Kelvin Mo 2012
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public
 * License along with this program; if not, write to the Free
 * Software Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * 
 */

define('SIMPLEWEBFINGER_VERSION', '0.1');

include_once 'simplexrd.class.php';

// Check if the configuration file has been defined
if (!file_exists('config.php')) {
    fatal_error('500 Server Error', 'No configuration file found.  See the SimpleWebFinger documentation for instructions on how to set up a configuration file.');
}
include_once 'config.php';

simplewebfinger_start();

/**
 * Main endpoint.
 */
function simplewebfinger_start() {
    if (!isset($_GET['resource']) || ($_GET['resource'] == '')) {
        fatal_error('400 Bad Request', 'resource parameter missing or empty');
        return;
    }

    $resource = $_GET['resource'];
    
    
    $jrd_file = SIMPLEWEBFINGER_RESOURCE_DIR . '/' . basename(rfc3986_urlencode($resource) . '.json');
    $xrd_file = SIMPLEWEBFINGER_RESOURCE_DIR . '/' . basename(rfc3986_urlencode($resource) . '.xml');
    
    if (file_exists($jrd_file)) {
        $json = file_get_contents($jrd_file);
        $jrd = json_decode($json, true);
    } elseif (file_exists($xrd_file)) {
        $xml = file_get_contents($xrd_file);
        
        $parser = new SimpleXRD();
        $jrd = $parser->parse($xml);
        $parser->free();
    } else {
        fatal_error('404 Not Found', 'resource not found', $jrd_file);
        return;
    }
    
    if (isset($jrd['subject']) && ($jrd['subject'] != $resource)) {
        if (isset($jrd['aliases'])) {
            $jrd['aliases'][] = $jrd['subject'];
        } else {
            $jrd['aliases'] = array($jrd['subject']);
        }
    }
    
    $jrd['subject'] = $resource;
    
    if (isset($_GET['rel'])) {
        if (is_array($_GET['rel'])) {
            $rels = $_GET['rel'];
         } else {
            $rels = array($_GET['rel']);
         }
         
         $links = $jrd['links'];
         $filtered_links = array();
         
         foreach ($links as $link) {
            if (isset($link['rel']) && in_array($link['rel'], $rels)) {
                $filtered_links[] = $link;
            }
         }
         
         $jrd['links'] = $filtered_links;
    }

    header('Content-Type: application/json');
    header('Content-Disposition: inline; filename=webfinger.json');
    header('Access-Control-Allow-Origin: ' . SIMPLEWEBFINGER_ACCESS_CONTROL_ALLOW_ORIGIN);
    
    print json_encode($jrd);

}

/**
 * Displays a fatal error message and exits.
 *
 * @param string $code the HTTP response status
 * @param string $message the error message
 * @param string $debug debugging information to be displayed if {@link SIMPLEWEBFINGER_DEBUG}
 * is set tot true
 */
function fatal_error($code, $message, $debug = NULL) {
    header_response_code($code);
?>
<!DOCTYPE html><html><head><title><?php print htmlspecialchars($code); ?></title></head>
<body>
  <h1><?php print htmlspecialchars($code); ?></h1>
  <p><?php print htmlspecialchars($message); ?></p>
  <?php if (($debug != NULL) && SIMPLEWEBFINGER_DEBUG): ?>
<pre>
<?php print htmlspecialchars($debug) ?>
</pre>
  <?php endif; ?>
</body>
</html>
<?php
    exit();
}

/* ---------------- SimpleID integration ---------------- */
function simpleid_init() {
}

function get_jrd_from_simpleid_identity($identity_file) {

    $jrd = array(
        'subject' => '',
        'links' => array(
            array(
                'rel' => 'http://openid.net/specs/connect/1.0/issuer',
                'href' => SIMPLEID_URL . '/'
            )
        )
    );

    return $jrd;
}

/* ---------------- Miscellaneous functions ---------------- */

/**
 * Send a HTTP response code to the user agent.
 *
 * The format of the HTTP response code depends on the way PHP is run.
 * When run as an Apache module, a properly formatted HTTP response
 * string is sent.  When run via CGI, the response code is sent via the
 * Status response header.
 *
 * @param string $code the response code along
 */
function header_response_code($code) {
    if (substr(PHP_SAPI, 0,3) === 'cgi') {
        header('Status: ' . $code);
    } else {
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code);
    }
}

/**
 * Encodes a URL using RFC 3986.
 *
 * PHP's urlencode function encodes a URL using RFC 1738 for PHP versions
 * prior to 5.3.  RFC 1738 has been updated by RFC 3986, which change the
 * list of characters which needs to be encoded.
 *
 * @param string $s the URL to encode
 * @return string the encoded URL
 */
function rfc3986_urlencode($s) {
    if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
        return rawurlencode($s);
    } else {
        return str_replace('%7E', '~', rawurlencode($s));
    }
}
?>
