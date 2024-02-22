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
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use quickRdf\DataFactory;
use termTemplates\QuadTemplate as QT;
use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\OaiException;
use acdhOeaw\arche\oaipmh\data\HeaderData;

/**
 * Creates &lt;metadata&gt; element by simply taking binary content of another
 * repository resource.
 * 
 * Of course it will work only if the target resource is an XML file satisfying 
 * requested OAI-PMH metadata schema (but checking it is out of scope of this 
 * class)
 *
 * Required metadata format definitition properties:
 * - `metaResProp` - RDF property pointing to the resource containing metadata
 * - `requestOptions` - Guzzle request options (http://docs.guzzlephp.org/en/stable/request-options.html)
 *   to be used while fetching the metadata resource
 * 
 * @author zozlak
 */
class ResMetadata implements MetadataInterface {

    /**
     * Repository resource object
     * @var RepoResourceDb
     */
    private $res;

    /**
     *
     * @var MetadataFormat
     */
    private $format;

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param RepoResourceDb $resource a repository 
     *   resource object
     * @param HeaderData $searchResultRow search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(RepoResourceDb $resource,
                                HeaderData $searchResultRow,
                                MetadataFormat $format) {
        $this->res    = $resource;
        $this->format = $format;
    }

    /**
     * Creates resource's XML metadata
     * 
     * @return DOMElement 
     * @throws \acdhOeaw\arche\oaipmh\OaiException
     */
    public function getXml(): DOMElement {
        $tmpl        = new QT($this->res->getUri(), DataFactory::namedNode($this->format->metaResProp));
        $metaRes     = (string) $this->res->getGraph()->getObject($tmpl);
        $clientClass = $this->format->httpClient ?? Client::class;
        $client      = new $clientClass(json_decode(json_encode($this->format->requestOptions), true));
        $request     = new Request('get', $metaRes);
        try {
            $response = $client->send($request);
            $meta     = new DOMDocument();
            $body     = (string) $response->getBody();
            $success  = $meta->loadXML($body);
            if (!$success) {
                throw new OaiException('failed to parse given resource content as XML');
            }
        } catch (RequestException $ex) {
            throw new OaiException("failed to fetch $metaRes");
        }
        return $meta->documentElement;
    }

    /**
     * Extends the search query - only resources having a resource-metadata are
     * matched.
     * 
     * @param MetadataFormat $format metadata format descriptor
     * @return QueryPart
     */
    static public function extendSearchFilterQuery(MetadataFormat $format): QueryPart {
        $query        = new QueryPart();
        $query->query = "SELECT DISTINCT id FROM relations WHERE property = ?";
        $query->param = [$format->metaResProp];
        return $query;
    }

    /**
     * This implementation has no fetch additional data trough the search query.
     * 
     * @param MetadataFormat $format metadata format descriptor
     * @return QueryPart
     */
    static public function extendSearchDataQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
    }
}
