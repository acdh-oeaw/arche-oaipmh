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
use quickRdf\DataFactory;
use termTemplates\QuadTemplate as QT;
use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\data\HeaderData;

/**
 * Creates OAI-PMH &lt;metadata&gt; element in Dublin Core format from an RDF metadata.
 * 
 * Simply takes all Dublin Core Elements properties and their Dublin Core Terms
 * counterparts and skips all other metadata properties.
 * 
 * Skips Dublin Core Terms which don't have Dublin Core Elements counterparts.
 *
 * @author zozlak
 */
class DcMetadata implements MetadataInterface {

    const DCT_NMSP  = 'http://purl.org/dc/terms/';
    const DCE_NMSP  = 'http://purl.org/dc/elements/1.1/';
    const DCE_PROPS = [
        'contributor', 'coverage', 'creator', 'date', 'description', 'format', 'identifier',
        'language', 'publisher', 'relation', 'rights', 'source', 'subject', 'title',
        'type'
    ];

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
     * @param HeaderData $searchResultRow  search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(RepoResourceDb $resource,
                                HeaderData $searchResultRow,
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
        $doc->appendChild($parent);

        $sbj  = $this->res->getUri();
        $meta = $this->res->getGraph()->getDataset();
        $tmpl = new QT($this->res->getUri());

        foreach (self::DCE_PROPS as $property) {
            $tmpl = $tmpl->withPredicate(DataFactory::namedNode(self::DCE_NMSP . $property));
            if ($meta->none($tmpl)) {
                $tmpl = $tmpl->withPredicate(DataFactory::namedNode(self::DCT_NMSP . $property));
            }
            foreach ($meta->getIterator($tmpl) as $triple) {
                $value = $triple->getObject();
                $el    = $doc->createElement('oai_dc:' . $property);
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
