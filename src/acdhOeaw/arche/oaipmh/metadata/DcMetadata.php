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

namespace acdhOeaw\arche\oaipmh\metadata;

use DOMDocument;
use DOMElement;
use rdfInterface\LiteralInterface;
use termTemplates\QuadTemplate as QT;
use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;

/**
 * Creates OAI-PMH &lt;metadata&gt; element in Dublin Core format from an RDF metadata.
 * 
 * Simply takes all Dublin Core elements and their Dublin Core Terms
 * counterparts and skips all other metadata properties.
 *
 * @author zozlak
 */
class DcMetadata implements MetadataInterface {

    /**
     * Dublin Core and Dublin Core Terms property list
     * @var array<string>
     */
    static private $properties = [
        'contributor', 'coverage', 'creator', 'date', 'description', 'format', 'identifier',
        'language', 'publisher', 'relation', 'rights', 'source', 'subject', 'title',
        'type'
    ];

    /**
     * Dublin Core namespace
     * @var string
     */
    static private $dcNmsp = 'http://purl.org/dc/elements/1.1/';

    /**
     * Dublin Core Terms namespace
     * @var string
     */
    static private $dctNmsp = 'http://purl.org/dc/terms/';

    /**
     * Repository resource object
     * @var RepoResourceDb
     */
    private $res;

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param RepoResourceDb $resource a repository 
     *   resource object
     * @param object $searchResultRow SPARQL search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(RepoResourceDb $resource,
                                object $searchResultRow, MetadataFormat $format) {
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
        $doc->appendChild($parent);

        $sbj        = $this->res->getUri();
        $dataset    = $this->res->getGraph()->getDataset();
        $properties = $dataset->listPredicates(new QT($sbj));
        foreach ($properties as $property) {
            $propUri  = (string) $property;
            $property = preg_replace('|^' . self::$dcNmsp . '|', '', (string) $property);
            $property = preg_replace('|^' . self::$dctNmsp . '|', '', $property);
            if (!in_array($property, self::$properties)) {
                continue;
            }

            foreach ($meta->getIterator(new QT($sbj, $propUri))as $triple) {
                $value = $triple->getObject();
                $el    = $doc->createElementNS(self::$dcNmsp, 'dc:' . $property);
                $el->appendChild($doc->createTextNode((string) $value));
                if ($value instanceof LiteralInterface && !empty($value->getLang())) {
                    $el->setAttribute('xml:lang', $value->getLang());
                }
                $parent->appendChild($el);
            }
        }

        return $parent;
    }

    /**
     * This implementation has no need to extend the search query.
     * 
     * @param MetadataFormat $format
     * @return QueryPart
     */
    static public function extendSearchFilterQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
    }

    /**
     * This implementation has no need to extend the search query.
     * 
     * @param MetadataFormat $format
     * @return QueryPart
     */
    static public function extendSearchDataQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
    }
}
