<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace acdhOeaw\oai\metadata;

use DOMDocument;
use DOMElement;
use stdClass;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\oai\data\MetadataFormat;

/**
 * Creates OAI-PMH &lt;metadata&gt; element in as an RDF-XML serialization of
 * a FedoraResource RDF metadata.
 *
 * @author zozlak
 */
class RdfXml implements MetadataInterface {

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
    function __construct(FedoraResource $resource, stdClass $sparqlResultRow,
                         MetadataFormat $format) {
        $this->res = $resource;
    }

    /**
     * Creates resource's XML metadata
     * 
     * @return DOMElement 
     */
    public function getXml(): DOMElement {
        $meta   = $this->res->getMetadata();
        $rdfxml = $meta->getGraph()->serialise('rdfxml');
        $doc    = new DOMDocument();
        $doc->loadXML($rdfxml);
        return $doc->documentElement;
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
