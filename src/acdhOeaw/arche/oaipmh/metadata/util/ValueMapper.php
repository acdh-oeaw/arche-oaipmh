<?php

/**
 * The MIT License
 *
 * Copyright 2018 Austrian Centre for Digital Humanities.
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

namespace acdhOeaw\arche\oaipmh\metadata\util;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use rdfInterface\TermInterface;
use termTemplates\QuadTemplate as QT;
use quickRdf\DatasetNode;
use quickRdf\DataFactory;
use quickRdfIo\Util as QuickRdfIoUtil;

/**
 * Provides vocabulary mappings. Assumes a value is an URL which can be resolved
 * to the RDF. Then extracts given property values from the RDF.
 * 
 * Implements caching.
 *
 * @author zozlak
 */
class ValueMapper {

    /**
     * 
     * @var array<string, array<string, string>>
     */
    private array $staticMaps;
    private Client $client;

    /**
     * @var array<string, DatasetNode>
     */
    private array $cache = [];

    /**
     * @var array<string, bool>
     */
    private array $failed = [];

    /**
     * 
     * @param array<string, mixed> $guzzleOptions connection options to be used while fetching
     *   the data - see http://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function __construct(array | null $staticMaps = null,
                                array $guzzleOptions = []) {
        $options      = [
            'verify'          => false,
            'http_errors'     => false,
            'allow_redirects' => true,
            'headers'         => ['Accept' => ['application/n-triples, application/rdf+xml;q=0.8, text/turtle;q=0.6']],
        ];
        $this->client = new Client(array_merge($options, $guzzleOptions));
        if ($staticMaps !== null) {

            $this->staticMaps = $staticMaps;
        }
    }

    public function getStaticMapping(string $map, string $value): string | null {
        if (isset($this->staticMaps[$map])) {
            return $this->staticMaps[$map][$value] ?? null;
        }
        return null;
    }

    /**
     * Returns mapped values.
     * 
     * @param string $value value to be mapped
     * @param string $property RDF property which value should be returned
     * @return array<TermInterface> mapped values
     */
    public function getMapping(string $value, string $property): array {
        if (!isset($this->cache[$value]) && !isset($this->failed[$value])) {
            $this->fetch($value);
        }
        $values = [];
        if (isset($this->cache[$value])) {
            $graph = $this->cache[$value];
            $iter  = $graph->getDataset()->listObjects(new QT($graph->getNode(), DataFactory::namedNode($property)));
            return iterator_to_array($iter);
        }
        return $values;
    }

    /**
     * Fetches mappings from the value URL into the cache.
     * 
     * @param string $uri URI value to be resolved
     * @return void
     */
    private function fetch(string $uri): void {
        $resp = $this->client->send(new Request('GET', $uri));
        if ($resp->getStatusCode() === 200) {
            $graph             = new DatasetNode(DataFactory::namedNode($uri));
            $graph->add(QuickRdfIoUtil::parse($resp, new DataFactory()));
            $this->cache[$uri] = $graph;
        } else {
            $this->failed[$uri] = true;
        }
    }
}
