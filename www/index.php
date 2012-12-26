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
    simplewebfinger_fatal_error('500 Server Error', 'No configuration file found.  See the SimpleWebFinger documentation for instructions on how to set up a configuration file.');
}
include_once 'config.php';

simplewebfinger_simpleid_init();
simplewebfinger_start();

/**
 * Main endpoint.
 */
function simplewebfinger_start() {
    if (!isset($_GET['resource']) || ($_GET['resource'] == '')) {
        simplewebfinger_fatal_error('400 Bad Request', 'resource parameter missing or empty');
        return;
    }

    $resource = $_GET['resource'];
    $descriptor = simplewebfinger_get_descriptor($resource);
    
    if ($descriptor == NULL) {
        simplewebfinger_fatal_error('404 Not Found', 'resource not found');
        return;
    }
    
    $jrd = simplewebfinger_parse_descriptor($descriptor);
    
    $jrd = simplewebfinger_fix_alias($jrd, $resource);
    
    if (isset($_GET['rel'])) $jrd = simplewebfinger_filter_rel($jrd, $_GET['rel']);

    header('Content-Type: application/json');
    header('Content-Disposition: inline; filename=webfinger.json');
    header('Access-Control-Allow-Origin: ' . SIMPLEWEBFINGER_ACCESS_CONTROL_ALLOW_ORIGIN);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $descriptor['mtime']) . ' GMT');
    header('Etag: ' . $descriptor['etag']);

    print json_encode($jrd);
}

/**
 * Returns an array containing metadata relating to the descriptor for a specified
 * resource URI.
 *
 * This function looks up the XRD or JRD document corresponding to the resource
 * URI specified in the $resource parameter.
 *
 * If SimpleID integration is enabled, this function also calls the
 * {@link simplewebfinger_simpleid_get_user()} function.
 *
 * @param string $resource the resource URI to search
 * @param array $aliases reserved for future use
 * @param int $retry reserved for future use
 * @return array an array containing metadata
 */
function simplewebfinger_get_descriptor($resource, $aliases = array(), $retry = 5) {
    $descriptor = array();
    
    $jrd_file = SIMPLEWEBFINGER_RESOURCE_DIR . '/' . basename(simplewebfinger_urlencode($resource) . '.json');
    $xrd_file = SIMPLEWEBFINGER_RESOURCE_DIR . '/' . basename(simplewebfinger_urlencode($resource) . '.xml');
    
    if (file_exists($jrd_file)) {
        $descriptor['file'] = $jrd_file;
        $descriptor['format'] = 'json';
    } elseif (file_exists($xrd_file)) {
        $descriptor['file'] = $xrd_file;
        $descriptor['format'] = 'xml';
    } elseif (($user = simplewebfinger_simpleid_get_user($resource)) != NULL) {
        $descriptor['simpleid'] = $user;
        $descriptor['format'] = 'simpleid';
        $descriptor['ctime'] = time();
        $descriptor['mtime'] = time();
        $descriptor['etag'] = sha1(serialize($user));
    } else {
        return NULL;
    }
    
    if (isset($descriptor['file'])) {
        $descriptor['ctime'] = filectime($descriptor['file']);
        $descriptor['mtime'] = filemtime($descriptor['file']);
        $descriptor['etag'] = sha1_file($descriptor['file']);
    }
    
    return $descriptor;
}

/**
 * Parses the descriptor metadata from the {@link simplewebfinger_get_descriptor()}
 * function and returns a JRD-equivalent array structure.
 *
 * This function reads the resource descriptor file found in $descriptor['file']
 * and parses it.
 *
 * @param array $descriptor the descriptor metadata
 * @return array the JRD document
 */
function simplewebfinger_parse_descriptor($descriptor) {
    $file = (isset($descriptor['file'])) ? $descriptor['file'] : NULL;
    $format = $descriptor['format'];
    
    switch ($format) {
        case 'json':
            $json = file_get_contents($file);
            return json_decode($json, true);
            break;
        case 'xml':
            $xml = file_get_contents($file);
            $parser = new SimpleXRD();
        
            try {
                $jrd = $parser->parse($xml);
            } catch (Exception $e) {
                $parser->free();  // finally block is supported only after PHP 5.5
                simplewebfinger_fatal_error('500 Server Error', 'Unable to translate XRD file into JSON.', $e->getMessage());
            }
            $parser->free();
            
            return $jrd;
            break;
        case 'simpleid':
            return simplewebfinger_simpleid_jrd($descriptor['simpleid']);
            break;
        default:
            simplewebfinger_fatal_error('500 Server Error', 'Unsupported resource descriptor format.');
    }
}

/**
 * Ensures that a specified resource URI occurs in either the subject or
 * the aliases member of a JRD document.
 *
 * @param array $jrd the JRD document
 * @param string $resource the resource URI
 * @return array the fixed JRD document
 */
function simplewebfinger_fix_alias($jrd, $resource) {
    if (isset($jrd['subject']) && ($jrd['subject'] == $resource)) return $jrd;
    
    if (isset($jrd['aliases'])) {
        $found = FALSE;
        foreach ($jrd['aliases'] as $alias) {
            if ($alias == $resource) {
                $found = TRUE;
                break;
            }
            if (!$found) $jrd['aliases'][] = $resource;
        }
    } else {
        $jrd['aliases'] = array($resource);
    }
    return $jrd;
}

/**
 * Filters a JRD document for specified link relations.
 *
 * @param array $jrd the JRD document
 * @param string|array $rels a string contain a link relation, or an array containing
 * multiple link relations, to filter
 * @return array the filtered JRD document
 */
function simplewebfinger_filter_rel($jrd, $rels) {
    if (isset($jrd['links'])) {
        if (!is_array($rels))  $rels = array($rels);
         
        $links = $jrd['links'];
        $filtered_links = array();
        
        foreach ($links as $link) {
            if (isset($link['rel']) && in_array($link['rel'], $rels)) {
                $filtered_links[] = $link;
            }
        }
        
        $jrd['links'] = $filtered_links;
    }
    return $jrd;
}

/**
 * Displays a fatal error message and exits.
 *
 * @param string $code the HTTP response status
 * @param string $message the error message
 * @param string $debug debugging information to be displayed if {@link SIMPLEWEBFINGER_DEBUG}
 * is set tot true
 */
function simplewebfinger_fatal_error($code, $message, $debug = NULL) {
    simplewebfinger_header_response_code($code);
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
/**
 * Initialises SimpleID integration.
 *
 * This function loads various SimpleID code from the directory specified
 * by the {@link SIMPLEWEBFINGER_SIMPLEID_WWW_DIR} parameter.  If this parameter is not set,
 * this function does nothing.
 *
 */
function simplewebfinger_simpleid_init() {
    if (!defined('SIMPLEWEBFINGER_SIMPLEID_WWW_DIR')) return;
    if (SIMPLEWEBFINGER_SIMPLEID_WWW_DIR == '') return;
    
    if (file_exists(SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/config.php')) {
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/version.inc.php';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/config.php';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/config.default.php';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/common.inc.php';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/user.inc.php';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/cache.inc.php';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/' . SIMPLEID_STORE . '.store.php';
    } elseif (file_exists(SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/config.inc')) {
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/version.inc';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/config.inc';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/config.default.inc';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/common.inc';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/user.inc';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/cache.inc';
        include_once SIMPLEWEBFINGER_SIMPLEID_WWW_DIR . '/' . SIMPLEID_STORE . '.store.inc';
    } else {
        simplewebfinger_fatal_error('500 Server Error', 'SimpleID files not found.  Check SIMPLEWEBFINGER_SIMPLEID_WWW_DIR in config.php.');
    }
    
    define('CACHE_DIR', SIMPLEID_CACHE_DIR);
}

/**
 * Finds a SimpleID user array based on a specified resource URI.
 *
 * This function calls the relevant functions in SimpleID to find a SimpleID
 * user whose identity URI matches the URI specified by $resource.
 *
 * @param string $resource the resource URI to find
 * @return array the SimpleID user array if found, otherwise NULL
 */
function simplewebfinger_simpleid_get_user($resource) {
    if (!defined('SIMPLEWEBFINGER_SIMPLEID_WWW_DIR')) return NULL;
    if (SIMPLEWEBFINGER_SIMPLEID_WWW_DIR == '') return NULL;
    
    // We need to temporarily change the working directory to SimpleID
    $pwd = getcwd();
    chdir(SIMPLEWEBFINGER_SIMPLEID_WWW_DIR);
    
    $user = user_load_from_identity($resource);
    
    chdir($pwd);
    
    return $user;
}

/**
 * Creates a JRD document based on a SimpleID user.
 *
 * The JRD document created is very simple - it merely points to the
 * SimpleID installation as the OpenID connect provider.
 *
 * @param array $user the SimpleID user
 * @return array the JRD document
 */
function simplewebfinger_simpleid_jrd($user) {
    $jrd = array(
        'subject' => $user['identity'],
        'links' => array(
            array(
                'rel' => 'http://specs.openid.net/auth/2.0/provider',
                'href' => SIMPLEID_BASE_URL . '/'
            )
        )
    );
    
    if (isset($user['aliases'])) {
        if (is_array($user['aliases'])) {
            $jrd['aliases'] = $user['aliases'];
        } else {
            $jrd['aliases'] = array($user['aliases']);
        }
    }
    
    if (version_compare(SIMPLEID_VERSION, '2.0', '>=')) {
        // SimpleID version 2.0 supports OpenID connect
        $jrd['links'][] = array(
            'rel' => 'http://openid.net/specs/connect/1.0/issuer',
            'href' => SIMPLEID_BASE_URL . '/'
        );
    }
    
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
function simplewebfinger_header_response_code($code) {
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
function simplewebfinger_urlencode($s) {
    if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
        return rawurlencode($s);
    } else {
        return str_replace('%7E', '~', rawurlencode($s));
    }
}
?>
