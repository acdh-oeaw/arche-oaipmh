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

namespace acdhOeaw\oai\metadata;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use EasyRdf\Graph;
use EasyRdf\Resource;

/**
 * Provides vocabulary mappings. Assumes a value is an URL which can be resolved
 * to the RDF . Then extracts given property values from the RDF.
 * 
 * Implements caching.
 *
 * @author zozlak
 */
class ValueMapper {

    /**
     * 
     * @var Client
     */
    private $client;

    /**
     * 
     * @var array<string, Resource>
     */
    private $cache  = [];

    /**
     * 
     * @var array<string, bool>
     */
    private $failed = [];

    /**
     * 
     * @param array<string, mixed> $guzzleOptions connection options to be used while fetching
     *   the data - see http://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function __construct(array $guzzleOptions = []) {
        $options      = [
            'verify'          => false,
            'http_errors'     => false,
            'allow_redirects' => true,
            'headers'         => ['Accept' => ['text/turtle']],
        ];
        $this->client = new Client(array_merge($options, $guzzleOptions));
    }

    /**
     * Returns mapped values.
     * 
     * @param string $value value to be mapped
     * @param string $property RDF property which value should be returned
     * @return array<Resource> mapped values
     */
    public function getMapping(string $value, string $property): array {
        if (!isset($this->cache[$value]) && !isset($this->failed[$value])) {
            $this->fetch($value);
        }
        $values = [];
        if (isset($this->cache[$value])) {
            foreach ($this->cache[$value]->all($property) as $i) {
                $values[] = $i;
            }
        }
        return $values;
    }

    /**
     * Fetches mappings from the value URL into the cache.
     * 
     * @param string $value value URL to be resolved
     * @return void
     */
    private function fetch(string $value): void {
        $resp = $this->client->send(new Request('GET', $value));
        if ($resp->getStatusCode() === 200) {
            $mime                = $resp->getHeader('Content-Type')[0] ?? '';
            $mime                = explode(';', $mime)[0];
            $graph               = new Graph();
            $graph->parse((string) $resp->getBody(), $mime);
            $this->cache[$value] = $graph->resource($value);
        } else {
            $this->failed[$value] = true;
        }
    }
}
