<?php

/**
 * The MIT License
 *
 * Copyright 2017 Austrian Centre for Digital Humanities.
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
use stdClass;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\oai\data\MetadataFormat;

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
class DcMetadata implements MetadataInterface {

    /**
     * Dublin Core and Dublin Core Terms property list
     * @var array
     */
    static private $properties = array(
        'contributor', 'coverage', 'creator', 'date', 'description', 'format', 'identifier',
        'language', 'publisher', 'relation', 'rights', 'source', 'subject', 'title',
        'type'
    );

    /**
     * Dublin Core namespace
     * @var string
     */
    static private $dcNmsp     = 'http://purl.org/dc/elements/1.1/';

    /**
     * Dublin Core Terms namespace
     * @var string
     */
    static private $dctNmsp    = 'http://purl.org/dc/terms/';

    /**
     * Repository resource object
     * @var \acdhOeaw\fedora\FedoraResource
     */
    private $res;

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param FedoraResource $resource repository resource object
     * @param stdClass $sparqlResultRow SPARQL search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(FedoraResource $resource,
                                stdClass $sparqlResultRow,
                                MetadataFormat $format) {
        $this->res = $resource;
    }

    /**
     * Creates resource's XML metadata
     * 
     * @return DOMElement 
     */
    public function getXml(): DOMElement {
        $doc    = new DOMDocument();
        $parent = $doc->createElementNS('http://www.openarchives.org/OAI/2.0/oai_dc/', 'oai_dc:dc');
        $parent->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/http://www.openarchives.org/OAI/2.0/oai_dc.xsd');

        $meta = $this->res->getMetadata();
        foreach ($meta->propertyUris() as $property) {
            $propUri  = $property;
            $property = preg_replace('|^' . self::$dcNmsp . '|', '', $property);
            $property = preg_replace('|^' . self::$dctNmsp . '|', '', $property);
            if (!in_array($property, self::$properties)) {
                continue;
            }

            foreach ($meta->all($propUri) as $value) {
                $el = $doc->createElementNS(self::$dcNmsp, 'dc:' . $property);
                $el->appendChild($doc->createTextNode($value));
                $parent->appendChild($el);
            }
        }
        $parent->appendChild($doc->createElementNS(self::$dcNmsp, 'dc:date', $meta->get('http://fedora.info/definitions/v4/repository#lastModified')));

        return $parent;
    }

    /**
     * This implementation has no need to extend the SPRARQL search query.
     * 
     * @param MetadataFormat $format
     * @param string $resVar
     * @return string
     */
    public static function extendSearchQuery(MetadataFormat $format,
                                             string $resVar): string {
        return '';
    }

}
