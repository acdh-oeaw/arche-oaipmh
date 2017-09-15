<?php

/*
 * The MIT License
 *
 * Copyright 2017 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\oai\metadata;

use DOMDocument;
use DOMElement;

/**
 * Creates OAI-PMH <metadata> element in Dublin Core format from 
 * a FedoraResource RDF metadata.
 * 
 * Simply takes all Dublin Core elements and their Dublin Core Terms
 * counterparts and skips all other metadata properties.
 * The only exception is http://fedora.info/definitions/v4/repository#lastModified
 * which is turned into dc:date.
 *
 * @author zozlak
 */
class DcMetadata extends Metadata {

    static private $properties = array(
        'contributor', 'coverage', 'creator', 'date', 'description', 'format', 'identifier', 
        'language', 'publisher', 'relation', 'rights', 'source', 'subject', 'title', 'type'
    );
    static private $dcNmsp = 'http://purl.org/dc/elements/1.1/';
    static private $dctNmsp = 'http://purl.org/dc/terms/';
    
    protected function createDOM(DOMDocument $doc): DOMElement {
        $parent = $doc->createElementNS('http://www.openarchives.org/OAI/2.0/oai_dc/', 'oai_dc:dc');
        $parent->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/http://www.openarchives.org/OAI/2.0/oai_dc.xsd');

        $meta = $this->res->getMetadata();
        foreach ($meta->propertyUris() as $property) {
            $propUri = $property;
            $property = preg_replace('|^' . self::$dcNmsp . '|', '', $property);
            $property = preg_replace('|^' . self::$dctNmsp . '|', '', $property);
            if (!in_array($property, self::$properties)) {
                continue;
            }
            
            foreach($meta->all($propUri) as $value) {
                $el = $doc->createElementNS(self::$dcNmsp, 'dc:' . $property);
                $el->appendChild($doc->createTextNode($value));
                $parent->appendChild($el);
            }
        }
        $parent->appendChild($doc->createElementNS(self::$dcNmsp, 'dc:date', $meta->get('http://fedora.info/definitions/v4/repository#lastModified')));

        return $parent;
    }

}
