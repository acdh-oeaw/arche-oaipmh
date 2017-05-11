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

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\metadataQuery\QueryParameter;
use acdhOeaw\util\RepoConfig as RC;
use DOMDocument;
use DOMNode;
use DOMElement;
use Exception;
use RuntimeException;
use InvalidArgumentException;
use StdClass;

/**
 * Description of Oai
 *
 * @author zozlak
 */
class Oai {

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

    public function __construct(RepositoryInfo $info, array $metadataFormats, Fedora $fedora) {
        $this->info = $info;
        $this->fedora = $fedora;

        foreach ($metadataFormats as $i) {
            $this->metadataFormats[$i->metadataPrefix] = $i;
        }

        // response initialization
        $this->response = new DOMDocument('1.0', 'UTF-8');
        $root = $this->response->createElementNS('http://www.openarchives.org/OAI/2.0/', 'OAI-PMH');
        $this->response->appendChild($root);
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');

        $root->appendChild($this->createElement('responseDate', gmdate('Y-m-d\TH:i:s\Z')));

        $node = $this->createElement('request', $this->info->baseUrl);
        foreach ($_GET as $key => $value) {
            $key = preg_replace('/[^a-zA-Z]/', '', $key);
            $node->setAttribute($key, $value);
        }
        $root->appendChild($node);
    }

    public function handleRequest() {
        $token = filter_input(\INPUT_GET, 'resumptionToken');
        if ($token !== null) {
            // we do not implement partial responses
            $this->reportError('badResumptionToken');
        } else {
            $verb = filter_input(\INPUT_GET, 'verb') . '';
            switch ($verb) {
                case 'ListSets':
                    $this->listSets();
                    break;
                case 'ListRecords':
                    $this->listRecords('ListRecords');
                    break;
                case 'ListMetadataFormats':
                    $this->listMetadataFormats();
                    break;
                case 'ListIdentifiers':
                    $this->listRecords('ListIdentifiers');
                    break;
                case 'Identify':
                    $this->identify();
                    break;
                case 'GetRecord':
                    $id = filter_input(\INPUT_GET, 'identifier') . '';
                    $this->listRecords('GetRecord', $id);
                    break;
                default:
                    $this->reportError('badVerb');
            }
        }
        header('Content-Type: text/xml');
        echo $this->response->saveXML();
    }

    public function identify() {
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
    }

    public function listMetadataFormats() {
        $id = filter_input(\INPUT_GET, 'identifier');

        if ($id != '') {
            try {
                $res = $this->fedora->getResourceById($id);
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
                $this->reportError('idDoesNotExist');
                return;
            }
        } else {
            $supFormats = $this->metadataFormats;
        }

        if (count($supFormats) == 0) {
            $this->reportError('noMetadataFormats');
            return;
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
    }

    public function listRecords(string $verb, string $id = '') {
        $from = filter_input(\INPUT_GET, 'from') . '';
        $until = filter_input(\INPUT_GET, 'until') . '';
        $set = filter_input(\INPUT_GET, 'set');
        $metadataPrefix = filter_input(\INPUT_GET, 'metadataPrefix') . '';

        if ($set !== null) {
            $this->reportError('noSetHierarchy');
            return;
        }
        if ($verb == 'GetRecord' && $id == '' || $metadataPrefix == '') {
            $this->reportError('badArgument');
            return;
        }

        try {
            $records = $this->getRecords($metadataPrefix, $from, $until, $id);
        } catch (InvalidArgumentException $e) {
            $this->reportError($e->getMessage());
            return;
        }

        if (count($records) == 0) {
            $this->reportError($verb == 'GetRecord' ? 'idDoesNotExist' : 'noRecordsMatch');
            return;
        }

        $container = $this->response->createElement($verb);
        foreach ($records as $i) {
            try {
                $header = $this->addHeader($i);
                if ($verb != 'ListIdentifiers') {
                    $meta = $this->addMetadata($i, $this->metadataFormats[$metadataPrefix]);
                }

                $parent = $container;
                if ($verb != 'ListIdentifiers') {
                    $parent = $this->createElement('record');
                    $container->appendChild($parent);
                }

                $parent->appendChild($header);
                if ($verb != 'ListIdentifiers') {
                    $parent->appendChild($meta);
                }
            } catch (Exception $e) {
                
            }
        }
        $this->response->documentElement->appendChild($container);
    }

    public function listSets() {
        $this->reportError('noSetHierarchy');
    }

    private function reportError($code) {
        $this->response->documentElement->appendChild($this->createElement('error', $code, array('code' => $code)));
    }

    private function addMetadata(StdClass $res, MetadataFormat $format): DOMElement {
        $node = $this->createElement('metadata');
        $meta = new $format->class($this->fedora->getResourceByUri(isset($res->metaRes) ? $res->metaRes : $res->res));
        $meta->appendTo($node);
        return $node;
    }

    private function addHeader(StdClass $res): DOMElement {
        $node = $this->createElement('header');
        $node->appendChild($this->createElement('identifier', $res->id));
        $node->appendChild($this->createElement('datestamp', $res->date));
        return $node;
    }

    private function getRecords(string $metadataPrefix, string $from = '', string $until = '', string $id = '') {
        $dateRexExp = '|^[0-9]{4}-[0-1][0-9]-[0-3][0-9](T[0-2][0-9]:[0-5][0-9]:[0-5][0-9]Z)?$|';
        $idProp = QueryParameter::escapeUri(RC::idProp());
        $idNmsp = QueryParameter::escapeLiteral(RC::idNmsp());
        $query = "
            SELECT ?id ?res ?metaRes ?date
            WHERE {
                ?res <http://fedora.info/definitions/v4/repository#lastModified> ?date .
                ?res %s ?id .
                %s
                %s
                FILTER (
                    regex(str(?id), %s)
                    %s
                )
            }
        ";

        // date filters
        $filter = '';
        if ($from) {
            if (!preg_match($dateRexExp, $from)) {
                throw new InvalidArgumentException('badArgument');
            }
            $filter .= sprintf(' && ?date >= %s^^xsd:dateTime', QueryParameter::escapeLiteral($from));
        }
        if ($until) {
            if (!preg_match($dateRexExp, $until)) {
                throw new InvalidArgumentException('badArgument');
            }
            $filter .= sprintf(' && ?date <= %s^^xsd:dateTime', QueryParameter::escapeLiteral($until));
        }

        // metadata format clause
        if (!isset($this->metadataFormats[$metadataPrefix])) {
            throw new InvalidArgumentException('cannotDisseminateFormat');
        }
        $format = $this->metadataFormats[$metadataPrefix];
        $metaRes = '';
        if ($format->rdfProperty != '') {
            $formatProp = QueryParameter::escapeUri($format->rdfProperty);
            $metaRes = sprintf('?res %s / ^%s ?metaRes . ', $formatProp, $idProp);
        }

        // resource id clause
        $idFilter = '';
        if ($id) {
            $idFilter = sprintf('?res %s %s', $idProp, QueryParameter::escapeUri($id));
        }

        // put all together
        $query = sprintf($query, $idProp, $idFilter, $metaRes, $idNmsp, $filter);

        return $this->fedora->runSparql($query);
    }

    private function createElement(string $element, string $value = '', array $attributes = array()): DOMNode {
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
