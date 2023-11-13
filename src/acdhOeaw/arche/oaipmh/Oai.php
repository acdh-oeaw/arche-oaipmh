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

namespace acdhOeaw\arche\oaipmh;

use acdhOeaw\arche\oaipmh\data\HeaderData;
use acdhOeaw\arche\oaipmh\data\RepositoryInfo;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\data\SetInfo;
use acdhOeaw\arche\oaipmh\set\SetInterface;
use acdhOeaw\arche\oaipmh\deleted\DeletedInterface;
use acdhOeaw\arche\oaipmh\search\SearchInterface;
use DOMDocument;
use DOMNode;
use DOMElement;
use PDO;
use RuntimeException;
use Throwable;
use zozlak\logging\Log;

/**
 * Implements controller for the OAI-PMH service:
 * - checks OAI-PMH requests correctness, 
 * - handles OAI-PMH `identify` and `ListMetadataFormats` commands
 * - delegates OAI-PMH `GetRecord`, `ListIdentifiers` and `ListRecords` commands 
 *   to a chosen class implementing the `acdhOeaw\arche\oaipmh\search\SearchInterface`
 * - delegates OAI-PMH `ListSets` command to a chosen class extending the
 *   `acdhOeaw\arche\oaipmh\set\SetInterface` class.
 * - generates OAI-PMH compliant output from results of above mentioned actions
 * - catches errors and generates OAI-PMH compliant error responses
 *
 * @author zozlak
 */
class Oai {

    /**
     * OAI-PMH response beginning template
     */
    static private string $respBegin = <<<TMPL
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
    <responseDate>%s</responseDate>
    <request %s>%s</request>

TMPL;

    /**
     * OAI-PMH response ending template
     */
    static private string $respEnd = <<<TMPL
</OAI-PMH>     
TMPL;

    /**
     * OAI-PMH date format regexp
     * @var string
     */
    static private $dateRegExp = '|^[0-9]{4}-[0-1][0-9]-[0-3][0-9](T[0-2][0-9]:[0-5][0-9]:[0-5][0-9]Z)?$|';

    /**
     * Configuration options
     */
    private object $config;

    /**
     * Repository database connection object
     */
    private PDO $pdo;

    /**
     * XML object used to serialize OAI-PMH response parts
     */
    private DOMDocument $response;

    /**
     * Repository info object used to serve OAI-PMH `Identify` requests
     */
    private RepositoryInfo $info;

    /**
     * List of metadata descriptors
     * @var array<MetadataFormat>
     */
    private array $metadataFormats = [];

    /**
     * Object handling sets
     */
    private SetInterface $sets;

    /**
     * Object handling deleted resources information
     */
    private DeletedInterface $deleted;
    private SearchInterface $search;

    /**
     * Cache object
     */
    private Cache $cache;
    private Log $log;
    private string $reqId;

    /**
     * Initialized the OAI-PMH server object.
     * 
     * @param object $config
     */
    public function __construct(object $config) {
        $this->config = $config;

        $this->pdo = new PDO($this->config->dbConnStr);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->info = new RepositoryInfo($this->config->info);

        $this->deleted             = new $this->config->deleted->deletedClass($this->config->deleted);
        $this->info->deletedRecord = $this->deleted->getDeletedRecord();

        foreach ($config->formats as $i) {
            $i->info                                   = $this->info;
            $this->metadataFormats[$i->metadataPrefix] = new MetadataFormat($i);
        }

        $this->sets = new $this->config->sets->setClass($this->config->sets);

        $searchClass  = $this->config->search->searchClass;
        $this->search = new $searchClass($this->sets, $this->deleted, $this->config->search, $this->pdo);

        if (!empty($this->config->cacheDir)) {
            $this->cache = new Cache($this->config->cacheDir);
        }

        $this->log = new Log($this->config->logging->file, $this->config->logging->level);
        $this->search->setLogger($this->log);

        // response initialization
        $this->response = new DOMDocument('1.0', 'UTF-8');
        $root           = $this->response->createElementNS('http://www.openarchives.org/OAI/2.0/', 'OAI-PMH');
        $this->response->appendChild($root);
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
    }

    /**
     * Handles OAI-PMH request.
     */
    public function handleRequest(): void {
        $t0          = microtime(true);
        $this->reqId = sprintf('%06d', rand(0, 999999));
        $this->log->info("$this->reqId\tHandling request: " . json_encode($_GET));

        header('Content-Type: text/xml');
        // an ugly workaround allowing to serve raw CMDI records
        $verb = $this->getParam('verb') . '';
        if ($verb === 'GetRecordRaw') {
            $id = $this->getParam('identifier') . '';
            $this->oaiListRecordRaw($id);
            return;
        }

        $params = [];
        foreach ($_GET as $key => $value) {
            $params[] = preg_replace('/[^a-zA-Z]/', '', $key) . '="' . htmlentities($value) . '"';
        }
        foreach ($_POST as $key => $value) {
            $params[] = preg_replace('/[^a-zA-Z]/', '', $key) . '="' . htmlentities($value) . '"';
        }
        printf(self::$respBegin, gmdate('Y-m-d\TH:i:s\Z'), implode(' ', $params), htmlentities($this->info->baseURL));
        // try to send some output so the client knows something's going on
        ob_flush();
        flush();

        try {
            $verb = $this->getParam('verb') . '';
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
                    $id = $this->getParam('identifier') . '';
                    $this->oaiListRecords('GetRecord', $id);
                    break;
                default:
                    throw new OaiException('badVerb');
            }
        } catch (Throwable $e) {
            $this->log->error("$this->reqId\t$e");
            if ($e instanceof OaiException) {
                $el = $this->createElement('error', $e->getMessage(), ['code' => $e->getMessage()]);
            } else {
                $el = $this->createElement('error', $e->getMessage(), ['code' => 'Internal error']);
            }
            $this->response->documentElement->appendChild($el);
            echo $this->response->saveXML($el);
        } finally {
            echo "\n" . self::$respEnd;
            $this->log->info("$this->reqId\tExecution time: " . (microtime(true) - $t0));
        }
    }

    /**
     * Implements the Identify OAI-PMH verb
     */
    public function oaiIdentify(): void {
        $this->checkRequestParam([]);
        $parent = $this->response->createElement('Identify');
        foreach ((array) $this->info as $key => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            foreach ($value as $i) {
                $parent->appendChild($this->createElement($key, $i));
            }
        }
        $this->response->documentElement->appendChild($parent);
        echo $this->response->saveXML($parent);
    }

    /**
     * Implements the ListMetadataFormats OAI-PMH verb
     * @throws OaiException
     */
    public function oaiListMetadataFormats(): void {
        $this->checkRequestParam(['identifier']);
        $id = $this->getParam('identifier');

        if ($id != '') {
            $supFormats = [];
            foreach ($this->metadataFormats as $format) {
                $this->search->setMetadataFormat($format);
                $this->search->find($id, '', '', '');
                if ($this->search->getCount() === 1) {
                    $supFormats[] = $format;
                }
            }
            if (count($supFormats) === 0) {
                $this->search->setMetadataFormat(null);
                $this->search->find($id, '', '', '');
                if ($this->search->getCount() === 0) {
                    throw new OaiException('idDoesNotExist');
                } else {
                    throw new OaiException('noMetadataFormats');
                }
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
        echo $this->response->saveXML($parent);
    }

    /**
     * Implements the ListIdentifiers, ListRecords and GetRecord OAI-PMH verbs
     * @param string $verb
     * @param string $id
     * @throws OaiException
     */
    public function oaiListRecords(string $verb, string $id = ''): void {
        $from           = (string) $this->getParam('from');
        $until          = (string) $this->getParam('until');
        $set            = (string) $this->getParam('set');
        $metadataPrefix = (string) $this->getParam('metadataPrefix');
        $token          = $this->getParam('resumptionToken');
        $reloadCache    = $this->getParam('reloadCache') !== null;

        if ($verb == 'GetRecord') {
            $this->checkRequestParam(['identifier', 'metadataPrefix', 'reloadCache']);
            if ($id == '') {
                throw new OaiException('badArgument');
            }
        } elseif ($token !== null) {
            $this->checkRequestParam(['resumptionToken']);
            $metadataPrefix = $this->search->findResumptionToken($token);
        } else {
            $this->checkRequestParam(['from', 'until', 'metadataPrefix', 'set', 'reloadCache']);
            if ($from && !preg_match(self::$dateRegExp, $from)) {
                throw new OaiException('badArgument');
            }
            if ($until && !preg_match(self::$dateRegExp, $until)) {
                throw new OaiException('badArgument');
            }
            if ($from && $until && strlen($from) !== strlen($until)) {
                throw new OaiException('badArgument');
            }
        }

        if (!isset($this->metadataFormats[$metadataPrefix])) {
            throw new OaiException('badArgument');
        }
        $format = $this->metadataFormats[$metadataPrefix];
        $this->search->setMetadataFormat($format);
        if ($token === null) {
            $this->search->find($id, $from, $until, $set);
        }
        if ($this->search->getCount() == 0) {
            throw new OaiException($verb == 'GetRecord' ? 'idDoesNotExist' : 'noRecordsMatch');
        }

        $tokenData = null;
        echo "    <" . $verb . ">\n";
        try {
            for ($i = 0; $i < $this->search->getCount(); $i++) {
                $recordFlag   = $metadataFlag = false;
                try {
                    $headerData = $this->search->getHeader($i);
                    $header     = $this->createHeader($headerData);
                    $this->response->documentElement->appendChild($header);
                    if ($verb === 'ListIdentifiers') {
                        echo $this->response->saveXML($header) . "\n";
                    } else {
                        echo "<record>\n";
                        $recordFlag   = true;
                        echo $this->response->saveXML($header) . "\n";
                        echo "<metadata>";
                        $metadataFlag = true;
                        if (isset($this->cache)) {
                            if ($reloadCache || !$this->cache->check($headerData, $format)) {
                                $xml = $this->search->getMetadata($i)->getXml();
                                $this->cache->put($headerData, $format, $xml->ownerDocument);
                            }
                            echo $this->cache->get($headerData, $format);
                        } else {
                            $xml = $this->search->getMetadata($i)->getXml();
                            echo $xml->ownerDocument->saveXML($xml);
                        }
                    }
                } catch (OaiException $e) {
                    //echo $e;
                } finally {
                    echo ($metadataFlag ? '</metadata>' : '') . ($recordFlag ? '</record>' : '');
                }
                if ($this->search->checkResumptionTimeout()) {
                    $tokenData = $this->search->getResumptionToken($i);
                    break;
                }
            }
            if ($tokenData !== null) {
                echo "\n" . $tokenData->asXml() . "\n";
            } elseif (!empty($token)) {
                // sanitize the token data
                $this->search->getResumptionToken($this->search->getCount());
            }
        } finally {
            echo "    </" . $verb . ">\n";
        }
    }

    /**
     * Returns a single metadata record without any OAI structures
     * @param string $id
     */
    public function oaiListRecordRaw(string $id = ''): void {
        $t0 = microtime(true);
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        try {
            $this->checkRequestParam(['identifier', 'metadataPrefix', 'reloadCache']);
            if ($id == '') {
                throw new OaiException('badArgument');
            }

            $metadataPrefix = (string) $this->getParam('metadataPrefix') . '';
            if (!isset($this->metadataFormats[$metadataPrefix])) {
                throw new OaiException('badArgument');
            }
            $format = $this->metadataFormats[$metadataPrefix];
            $this->search->setMetadataFormat($format);
            $this->search->find($id, '', '', '');
            if ($this->search->getCount() == 0) {
                throw new OaiException('idDoesNotExist');
            }

            $xml = $this->search->getMetadata(0)->getXml();
            echo $xml->ownerDocument->saveXML($xml);
        } catch (Throwable $e) {
            $this->log->error("$this->reqId\t$e");
            http_response_code($e instanceof OaiException ? 400 : 500);
            $doc = new DOMDocument('1.0', 'UTF-8');
            $el  = $doc->createElement('error', $e->getMessage());
            $doc->appendChild($el);
            echo $doc->saveXML($el);
        }
        $this->log->info("$this->reqId\tExecution time: " . (microtime(true) - $t0));
    }

    /**
     * Implements the ListSets OAI-PMH verb.
     * 
     * Fetches set description using a chosen (config:oaiSetClass) class and
     * formats its output as an OAI-PMH XML.
     */
    public function oaiListSets(): void {
        $this->checkRequestParam([]);
        $sets = $this->sets->listSets($this->pdo);
        echo "    <ListSets>\n";
        foreach ($sets as $i) {
            /* @var $i SetInfo */
            $node = $this->createElement('set');
            $node->appendChild($this->createElement('setSpec', $i->spec));
            $node->appendChild($this->createElement('setName', $i->name));
            if ($i->description) {
                $tmp = $this->createElement('setDescription');
                $tmp->appendChild($tmp->ownerDocument->importNode($i->description, true));
                $node->appendChild($tmp);
            }
            $this->response->appendChild($node);
            echo $this->response->saveXML($node);
            echo "\n";
            $this->response->removeChild($node);
        }
        echo "    </ListSets>\n";
    }

    /**
     * Creates a resource's <header> element as defined by OAI-PMH standard.
     * 
     * @param HeaderData $res
     * @return DOMElement
     */
    private function createHeader(HeaderData $res): DOMElement {
        $attr = [];
        if ($res->deleted) {
            $attr['status'] = 'deleted';
        }
        $node = $this->createElement('header', '', $attr);
        $node->appendChild($this->createElement('identifier', $res->id));
        $node->appendChild($this->createElement('datestamp', $res->date));
        foreach ($res->sets as $i) {
            $node->appendChild($this->createElement('setSpec', $i));
        }
        return $node;
    }

    /**
     * Returns a PHP reresentation of an XML node.
     * 
     * @param string $element
     * @param string $value
     * @param array<string, string> $attributes
     * @return DOMNode
     */
    private function createElement(string $element, string $value = '',
                                   array $attributes = []): DOMNode {
        $node = $this->response->createElement($element);
        if ($value != '') {
            $node->appendChild($this->response->createTextNode($value));
        }
        foreach ($attributes as $k => $v) {
            $node->setAttribute($k, $v);
        }
        return $node;
    }

    /**
     * Validates request parameters.
     *
     * @param array<string> $allowed allowed parameter names list
     * @throws OaiException
     */
    private function checkRequestParam(array $allowed): void {
        $seen  = [];
        $param = filter_input(\INPUT_SERVER, 'QUERY_STRING');
        $param = explode('&', $param ? $param : '');
        foreach ($param as $i) {
            $i = explode('=', $i);
            if (isset($seen[$i[0]])) {
                throw new OaiException('badArgument');
            }
            $seen[$i[0]] = 1;
        }

        $allowed[] = 'verb';
        foreach ($_GET as $k => $v) {
            if (!in_array($k, $allowed)) {
                throw new OaiException('badArgument');
            }
        }
    }

    private function getParam(string $name): ?string {
        return filter_input(\INPUT_GET, $name) ?? filter_input(\INPUT_POST, $name);
    }
}
