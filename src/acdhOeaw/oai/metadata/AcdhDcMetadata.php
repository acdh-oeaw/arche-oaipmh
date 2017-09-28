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
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;
use acdhOeaw\oai\data\MetadataFormat;

/**
 * Creates OAI-PMH &lt;metadata&gt; element in Dublin Core format from 
 * a FedoraResource RDF metadata.
 * 
 * It reads the metadata property mappings from the ontology being part of the
 * repository by searching for:
 *   [ontologyRes] --cfg:fedoraIdProp--> acdhProp
 *   [ontologyRes] --cfg:oaiEqProp--> dcProp
 * and
 *   [dcRes] --cfg:oaiEqProp--> acdhProp
 *   [dcRes] --cfg:fedoraIdProp--> dcProp
 *
 * Requires metadata format configuration properties:
 * - idProp  -RDF property used to store repository resource identifiers
 * - eqProp - RDF property used to denote properties equivalence
 * - acdhNmsp - ACDH properties namespace
 * 
 * @author zozlak
 */
class AcdhDcMetadata implements MetadataInterface {

    /**
     * Dublin Core namespace
     * @var string
     */
    static private $dcNmsp = 'http://purl.org/dc/elements/1.1/';

    /**
     * Stores metadata property to Dublic Core property mappings
     * @var array
     */
    static private $mappings;

    /**
     * Fetches mappings from the triplestore
     * @param Fedora $fedora
     * @param MetadataFormat $format
     */
    static private function init(Fedora $fedora, MetadataFormat $format) {
        if (is_array(self::$mappings)) {
            return;
        }

        $query   = "
            SELECT DISTINCT ?dc ?acdh WHERE {
                { ?dc ^?@ / ?@ ?acdh . }
                UNION 
                { ?acdh ^?@ / ?@ ?dc . }
                FILTER (regex(str(?dc), '^http://purl.org/dc/') && regex(str(?acdh), ?#))
            }
        ";
        $idProp  = $format->idProp;
        $eqProp  = $format->eqProp;
        $param   = array($idProp, $eqProp, $idProp, $eqProp, '^' . $format->acdhNmsp);
        $query   = new SimpleQuery($query, $param);
        $results = $fedora->runQuery($query);

        self::$mappings = array();
        foreach ($results as $i) {
            self::$mappings[(string) $i->acdh] = (string) $i->dc;
        }
    }

    /**
     * Repository resource object
     * @var \acdhOeaw\fedora\FedoraResource
     */
    private $res;

    /**
     * Metadata format descriptor
     * @var \acdhOeaw\oai\data\MetadataFormat
     */
    private $format;

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
        $this->res    = $resource;
        $this->format = $format;
    }

    /**
     * Creates resource's XML metadata
     * 
     * @return DOMElement 
     */
    public function getXml(): DOMElement {
        self::init($this->res->getFedora(), $this->format);

        $doc    = new DOMDocument();
        $parent = $doc->createElementNS('http://www.openarchives.org/OAI/2.0/oai_dc/', 'oai_dc:dc');
        $parent->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/http://www.openarchives.org/OAI/2.0/oai_dc.xsd');

        $meta       = $this->res->getMetadata();
        $properties = array_intersect($meta->propertyUris(), array_keys(self::$mappings));
        foreach ($properties as $property) {
            $propInNs = str_replace(self::$dcNmsp, 'dc:', self::$mappings[$property]);
            foreach ($meta->all($property) as $value) {
                $el = $doc->createElementNS(self::$dcNmsp, $propInNs);
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
