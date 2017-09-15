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

namespace acdhOeaw\oai;

use EasyRdf\Sparql\Result;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;
use acdhOeaw\oai\metadata\MetadataInterface;
use acdhOeaw\util\RepoConfig as RC;
use DOMDocument;
use DOMNode;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;
use StdClass;
use Throwable;

/**
 * Description of Oai
 *
 * @author zozlak
 */
class Oai {

    static private $respBegin = <<<TMPL
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
    <responseDate>%s</responseDate>
    <request %s>%s</request>

TMPL;
    static private $respEnd   = <<<TMPL
</OAI-PMH>     
TMPL;

    /**
     *
     * @var \acdhOeaw\fedora\Fedora 
     */
    private $fedora;

    /**
     *
     * @var \DOMDocument
     */
    private $response;

    /**
     *
     * @var array
     */
    private $metadataFormats = array();

    /**
     *
     * @var \acdhOeaw\oai\RepositoryInfo
     */
    private $info;

    /**
     * Initialized the OAI-PMH server object.
     * 
     * @param \acdhOeaw\oai\RepositoryInfo $info
     * @param array $metadataFormats
     * @param Fedora $fedora
     */
    public function __construct(RepositoryInfo $info, array $metadataFormats,
                                Fedora $fedora) {
        $this->info   = $info;
        $this->fedora = $fedora;

        foreach ($metadataFormats as $i) {
            $this->metadataFormats[$i->metadataPrefix] = $i;
        }

        // response initialization
        $this->response = new DOMDocument('1.0', 'UTF-8');
        $root           = $this->response->createElementNS('http://www.openarchives.org/OAI/2.0/', 'OAI-PMH');
        $this->response->appendChild($root);
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
    }

    /**
     * Handles OAI-PMH request.
     */
    public function handleRequest() {
        header('Content-Type: text/xml');

        $params = array();
        foreach ($_GET as $key => $value) {
            $params[] = preg_replace('/[^a-zA-Z]/', '', $key) . '="' . htmlentities($value) . '"';
        }
        printf(self::$respBegin, gmdate('Y-m-d\TH:i:s\Z'), implode(' ', $params), htmlentities($this->info->baseUrl));

        try {

            $token = filter_input(\INPUT_GET, 'resumptionToken');
            if ($token !== null) {
                // we do not implement partial responses
                throw new OaiException('badResumptionToken');
            } else {
                $verb = filter_input(\INPUT_GET, 'verb') . '';
                switch ($verb) {
                    case 'ListSets':
                        $this->oaiListSets();
                        break;
                    case 'ListRecords':
                        $this->oaiListRecords('ListRecords');
                        break;
                    case 'ListMetadataFormats':
                        $this->oaiListMetadataFormats();
                        break;
                    case 'ListIdentifiers':
                        $this->oaiListRecords('ListIdentifiers');
                        break;
                    case 'Identify':
                        $this->oaiIdentify();
                        break;
                    case 'GetRecord':
                        $id = filter_input(\INPUT_GET, 'identifier') . '';
                        $this->oaiListRecords('GetRecord', $id);
                        break;
                    default:
                        throw new OaiException('badVerb');
                }
            }
        } catch (Throwable $e) {
            if ($e instanceof OaiException) {
                $el = $this->createElement('error', $e->getMessage(), array('code' => $e->getMessage()));
            } else {
                $el = $this->createElement('error', $e->getMessage(), array('code' => 'Internal error'));
            }
            $this->response->documentElement->appendChild($el);
            echo "    " . $el->C14N() . "\n";
        } finally {
            echo self::$respEnd;
        }
    }

    /**
     * Implements the Identify OAI-PMH verb
     */
    public function oaiIdentify() {
        $parent = $this->response->createElement('Identify');
        foreach ($this->info as $key => $value) {
            if (!is_array($value)) {
                $value = array($value);
            }
            foreach ($value as $i) {
                $parent->appendChild($this->createElement($key, $i));
            }
        }
        $this->response->documentElement->appendChild($parent);
        echo $parent->C14N() . "\n";
    }

    /**
     * Implements the ListMetadataFormats OAI-PMH verb
     * @throws OaiException
     */
    public function oaiListMetadataFormats() {
        $id = filter_input(\INPUT_GET, 'identifier');

        if ($id != '') {
            try {
                $res  = $this->fedora->getResourceById($id);
                $meta = $res->getMetadata();

                $supFormats = array();
                foreach ($this->metadataFormats as $format) {
                    if ($format->rdfProperty == '') {
                        $supFormats[] = $format;
                    } elseif ($meta->getResource($format->rdfProperty) !== null) {
                        $supFormats[] = $format;
                    }
                }
            } catch (RuntimeException $e) {
                throw new OaiException('idDoesNotExist');
            }
        } else {
            $supFormats = $this->metadataFormats;
        }

        if (count($supFormats) == 0) {
            throw new OaiException('noMetadataFormats');
        }

        $parent = $this->response->createElement('ListMetadataFormats');
        foreach ($supFormats as $i) {
            $node = $this->response->createElement('metadataFormat');
            $node->appendChild($this->createElement('metadataPrefix', $i->metadataPrefix));
            $node->appendChild($this->createElement('schema', $i->schema));
            $node->appendChild($this->createElement('metadataNamespace', $i->metadataNamespace));
            $parent->appendChild($node);
        }
        $this->response->documentElement->appendChild($parent);
        echo $parent->C14N() . "\n";
    }

    /**
     * Implements the ListIdentifiers, ListRecords and GetRecord OAI-PMH verbs
     * @param string $verb
     * @param string $id
     * @throws OaiException
     */
    public function oaiListRecords(string $verb, string $id = '') {
        $from           = filter_input(\INPUT_GET, 'from') . '';
        $until          = filter_input(\INPUT_GET, 'until') . '';
        $set            = filter_input(\INPUT_GET, 'set');
        $metadataPrefix = filter_input(\INPUT_GET, 'metadataPrefix') . '';

        if ($set !== null) {
            throw new OaiException('noSetHierarchy');
        }
        if ($verb == 'GetRecord' && $id == '' || $metadataPrefix == '') {
            throw new OaiException('badArgument');
        }

        try {
            $records = $this->findRecords($metadataPrefix, $from, $until, $id);
        } catch (InvalidArgumentException $e) {
            throw new OaiException($e->getMessage());
        }

        if (count($records) == 0) {
            throw new OaiException($verb == 'GetRecord' ? 'idDoesNotExist' : 'noRecordsMatch');
        }

        $format = $this->metadataFormats[$metadataPrefix];

        echo "    <" . $verb . ">\n";
        foreach ($records as $i) {
            try {
                $uri = isset($i->metaRes) ? $i->metaRes : $i->res;
                $meta = new $format->class($this->fedora->getResourceByUri($uri), $format);
                
                $header = $this->createHeader($i);
                if ($verb === 'ListIdentifiers') {
                    $record = $header;
                } else {
                    $record = $this->createElement('record');

                    $record->appendChild($header);
                    
                    $metaNode = $this->createElement('metadata');
                    $meta->appendTo($metaNode);
                    $record->appendChild($metaNode);
                }
                $this->response->documentElement->appendChild($record);
                echo $record->C14N() . "\n";
                $this->response->documentElement->appendChild($record);
            } catch (Throwable $e) {
                //echo $e;
            }
        }
        echo "    </" . $verb . ">\n";
    }

    /**
     * Implements the ListSets OAI-PMH verb
     */
    public function oaiListSets() {
        throw new OaiException('noSetHierarchy');
    }

    /**
     * Creates a resource's <header> element as defined by OAI-PMH standard.
     * 
     * @param StdClass $res
     * @return DOMElement
     */
    private function createHeader(StdClass $res): DOMElement {
        $node = $this->createElement('header');
        $node->appendChild($this->createElement('identifier', $res->id));
        $node->appendChild($this->createElement('datestamp', $res->date));
        return $node;
    }

    /**
     * Searches in the triplestore for repository resources matching given
     * criteria.
     * 
     * @param string $metadataPrefix
     * @param string $from
     * @param string $until
     * @param string $id
     * @return \EasyRdf\Sparql\Result
     * @throws InvalidArgumentException
     */
    private function findRecords(string $metadataPrefix, string $from = '',
                                 string $until = '', string $id = ''): Result {
        $dateRexExp = '|^[0-9]{4}-[0-1][0-9]-[0-3][0-9](T[0-2][0-9]:[0-5][0-9]:[0-5][0-9]Z)?$|';
        $param      = array();

        // metadata format clause
        if (!isset($this->metadataFormats[$metadataPrefix])) {
            throw new InvalidArgumentException('cannotDisseminateFormat');
        }
        $format  = $this->metadataFormats[$metadataPrefix];
        $metaRes = '';
        if ($format->rdfProperty != '') {
            $metaRes = '?res ?@ / ^?@ ?metaRes . ';
            $param[] = $format->rdfProperty;
            $param[] = RC::idProp();
        }

        // resource id clause
        $idFilter = '';
        if ($id) {
            $idFilter = '?res ?@ ?@ .';
            $param[]  = RC::idProp();
            $param[]  = $id;
        }

        
        $query   = "
            SELECT ?id ?res ?metaRes ?date
            WHERE {
                ?res <http://fedora.info/definitions/v4/repository#lastModified> ?date .
                " . $metaRes . "
                " . $idFilter . "
                ?res ?@ ?id .
                FILTER (
                    regex(str(?id), ?#)
                    {{OTHER_FILTERS}}
                )
            }
        ";
        $param[] = RC::idProp();
        $param[] = RC::idNmsp();

        // date filters
        $filter = '';
        if ($from) {
            if (!preg_match($dateRexExp, $from)) {
                throw new InvalidArgumentException('badArgument');
            }
            $filter  .= ' && ?date >= @#^^xsd:dateTime';
            $param[] = $from;
        }
        if ($until) {
            if (!preg_match($dateRexExp, $until)) {
                throw new InvalidArgumentException('badArgument');
            }
            $filter  .= ' && ?date <= @#^^xsd:dateTime';
            $param[] = $until;
        }

        // inject filters
        $query = str_replace('{{OTHER_FILTERS}}', $filter, $query);

        return $this->fedora->runQuery(new SimpleQuery($query, $param));
    }

    /**
     * Returns a PHP reresentation of an XML node.
     * 
     * @param string $element
     * @param string $value
     * @param array $attributes
     * @return DOMNode
     */
    private function createElement(string $element, string $value = '',
                                   array $attributes = array()): DOMNode {
        $node = $this->response->createElement($element);
        if ($value != '') {
            $node->appendChild($this->response->createTextNode($value));
        }
        foreach ($attributes as $k => $v) {
            $node->setAttribute($k, $v);
        }
        return $node;
    }

}
