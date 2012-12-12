<?php
/*
 * SimpleXRD
 *
 * Copyright (C) Kelvin Mo 2012
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above
 *    copyright notice, this list of conditions and the following
 *    disclaimer in the documentation and/or other materials provided
 *    with the distribution.
 *
 * 3. The name of the author may not be used to endorse or promote
 *    products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
 * GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
 * IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * A simple XRD parser.
 *
 * This XRD parser supports all the features of XRD which can be translated into
 * its JSON representation under RFC 6415.  This means that the parser does
 * not support extensibility under the XRD specification.
 *
 * Using the parser is straightforward.  Assuming the XRD code has been loaded
 * into a variable called $xml. Then the code is simply
 *
 * <code>
 * $parser = new SimpleXRD();
 * $jrd = $parser->parse($xml);
 * $parser->free();
 * </code>
 *
 * @see http://docs.oasis-open.org/xri/xrd/v1.0/xrd-1.0.html, RFC 6415
 */
class SimpleXRD {
    /**
     * XML parser
     * @var resource
     */
    private $parser;
    
    /**
     * XRD namespace constant
     * @var string
     */
    private $XRD_NS = 'http://docs.oasis-open.org/ns/xri/xrd-1.0';
    
    /**
     * XRD namespace constant
     * @var string
     */
    private $XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';
    
    /**
     * XML namespace constant
     * @var string
     */
    private $XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /**
     * JRD equivalent document
     * @var array
     */
    private $jrd = array();
    
    /**
     * CDATA buffer
     * @var string
     */
    private $buffer = '';
    
    /**
     * Attributes buffer
     * @var array
     */
    private $attribs = array();
    
    /**
     * Currently parsed Link buffer
     * @var array
     * @access private
     */
    private $link = NULL;
    
    /**
     * Creates an instance of the XRD parser.
     *
     * This constructor also initialises the underlying XML parser.
     */
    public function __construct() {
        $this->parser = xml_parser_create_ns();
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING,0);
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, 'element_start', 'element_end');
        xml_set_character_data_handler($this->parser, 'cdata');
    }
    
    /**
     * Frees memory associated with the underlying XML parser.
     *
     * Note that only the memory associated with the underlying XML parser is
     * freed.  Memory associated with the class itself is not freed.
     *
     * @access public
     */
    public function free() {
        xml_parser_free($this->parser);
    }
    
    /**
     * Parses an XRD document and returns the JRD-equivalent structure.
     *
     * @param string $xml the XML document to parse
     * @return array the JRD equivalent structure
     * @access public
     */
    public function parse($xml) {
        xml_parse($this->parser, $xml);
        return $this->jrd;
    }
    
    /**
     * XML parser callback
     *
     * @access private
     */
    private function element_start(&$parser, $qualified, $attribs) {
        list($ns, $name) = $this->parse_namespace($qualified);
        
        if ($ns == $this->XRD_NS) {
            switch ($name) {
                case 'XRD':
                    $this->jrd = array();
                    break;
                case 'Link':
                    $this->link = $attribs;
                    break;
            }
        }
        
        $this->buffer = '';
        $this->attribs = $attribs;
    }

    /**
     * XML parser callback
     *
     * @access private
     */
    function element_end(&$parser, $qualified) {
        list($ns, $name) = $this->parse_namespace($qualified);
        
        if ($ns == $this->XRD_NS) {
            switch ($name) {
                case 'Subject':
                case 'Expires':
                    $this->jrd[strtolower($name)] = $this->buffer;
                    break;
                case 'Alias':
                    if (!isset($this->jrd['aliases'])) $this->jrd['aliases'] = array();
                    $this->jrd['aliases'][] = $this->buffer;
                    break;
                case 'Property':
                    if (isset($this->attribs[$this->XSI_NS . ':nil']) && ($this->attribs[$this->XSI_NS . ':nil'] == 'true')) {
                        $value = NULL;
                    } else {
                        $value = $this->buffer;
                    }
                    if (is_null($this->link)) {
                        if (!isset($this->jrd['properties'])) $this->jrd['properties'] = array();
                        $this->jrd['properties'][$this->attribs['type']] = $value;
                    } else {
                        if (!isset($this->link['properties'])) $this->link['properties'] = array();
                        $this->link['properties'][$this->attribs['type']] = $value;
                    }
                    break;
                case 'Link':
                    if (!isset($this->jrd['links'])) $this->jrd['links'] = array();
                    $this->jrd['links'][] = $this->link;
                    break;
                case 'Title':
                    if (is_null($this->link)) {
                        throw new ErrorException('Title element found, but not child of Link.');
                        return;
                    }
                    if (isset($this->attribs[$this->XML_NS . ':lang'])) {
                        $lang = $this->attribs[$this->XML_NS . ':lang'];
                    } else {
                        $lang = 'default';
                    }
                    if (!isset($this->link['titles'])) $this->link['titles'] = array();
                    $this->link['titles'][$lang] = $this->buffer;
                    break;
            }
        }


        $this->attribs = array();
    }

    /**
     * XML parser callback
     *
     * @access private
     */
    function cdata(&$parser, $data) {
        $this->buffer .= $data;
    }
    
    /**
     * Parses a namespace-qualified element name.
     *
     * @param string $qualified the qualified name
     * @return array an array with two elements - the first element contains
     * the namespace qualifier (or an empty string), the second element contains
     * the element name
     * @access protected
     */
    private function parse_namespace($qualified) {
        $pos = strrpos($qualified, ':');
        if ($pos !== FALSE) return array(substr($qualified, 0, $pos), substr($qualified, $pos + 1, strlen($qualified)));
        return array('', $qualified);
    }
}
?>
