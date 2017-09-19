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

namespace acdhOeaw\oai\search;

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;
use acdhOeaw\oai\data\HeaderData;
use acdhOeaw\oai\data\MetadataFormat;
use acdhOeaw\oai\metadata\MetadataInterface;
use acdhOeaw\util\RepoConfig as RC;

/**
 * Implements basic OAI-PMH search. It is assumed that all OAI-PMH search
 * facets (id, date, set) and data required to create <header> nodes (again id,
 * date, set) are accessible as repository resource's metadata RDF properties.
 * 
 * Mappings between OAI-PMH terms (id, date, set) and RDF properties is provided
 * by the statically initialized `acdhOeaw\util\RepoConfig` class.
 *
 * Includes `metadataClass::extendSearchQuery()` SPARQL query part in the
 * performed search (where `metadataClass` is read from the metadata format
 * descriptor).
 * 
 * @author zozlak
 */
class BaseSearch implements SearchInterface {

    /**
     * Metadata format descriptor.
     * @var \acdhOeaw\oai\MetadataFormat
     */
    private $format;

    /**
     * Repository connection object
     * @var \acdhOeaw\fedora\Fedora
     */
    private $fedora;

    /**
     * Last search results
     * @var array
     */
    private $records;

    /**
     * Creates a search engine object.
     * @param MetadataFormat $format metadata format descriptor
     * @param Fedora $fedora repository connection object
     */
    public function __construct(MetadataFormat $format, Fedora $fedora) {
        $this->format = $format;
        $this->fedora = $fedora;
    }

    /**
     * Performs search using given filter values.
     * @param string $id id filter value
     * @param string $from date from filter value
     * @param string $until date to filter value
     * @param string $set set filter value
     */
    public function find(string $id, string $from, string $until, string $set) {
        $ext = '';
        if (method_exists($this->format->class, 'extendSearchQuery')) {
            $class = $this->format->class;
            $ext   = $class::extendSearchQuery($this->format, '?res');
        }

        $query = "
            SELECT *
            WHERE {
                ?res ?@ ?date .
                " . $this->getIdFilter($id) . "
                ?res ?@ ?id .
                " . $this->getSetFilter($set) . "
                OPTIONAL {" . $this->getSetClause() . "}
                " . $ext . "
                " . $this->getDateFilter($from, $until) . "
            }
        ";
        $param = array(RC::get('oaiDateProp'), RC::get('oaiIdProp'));
        $query = new SimpleQuery($query, $param);
        //echo $query->getQuery();
        $res   = $this->fedora->runQuery($query);

        // as a resource may be a part of many sets, aggregation is needed
        $this->records = array();
        foreach ($res as $i) {
            $uri = (string) $i->res;
            if (!isset($this->records[$uri])) {
                $this->records[$uri] = $i;
                $i->sets = array();
            }
            if (isset($i->set) && $i->set) {
                $this->records[$uri]->sets[] = (string) $i->set;
            }
        }
        $this->records = array_values($this->records);
    }

    /**
     * Returns number of resources matching last search (last call of the 
     * `find()` method).
     */
    public function getCount(): int {
        return count($this->records);
    }

    /**
     * Provides the `HeaderData` object for a given search result.
     * @param int $pos seach result resource index
     * @return \acdhOeaw\oai\data\HeaderData
     */
    public function getHeader(int $pos): HeaderData {
        return new HeaderData($this->records[$pos]);
    }

    /**
     * Provides the `MetadataInterface` object for a given search result.
     * @param int $pos seach result resource index
     * @return MetadataInterface
     */
    public function getMetadata(int $pos): MetadataInterface {
        $res = $this->fedora->getResourceByUri((string) $this->records[$pos]->res);
        $res = new $this->format->class($res, $this->records[$pos], $this->format);
        return $res;
    }

    /**
     * Creates SPARQL query clause implementing the id filter.
     * @param string $id id filter value
     * @return string
     */
    private function getIdFilter(string $id): string {
        $filter = '';
        if ($id) {
            $param  = array(RC::get('oaiIdProp'), $id);
            $filter = new SimpleQuery('?res ?@ ?@ .', $param);
            $filter = $filter->getQuery();
        }
        return $filter;
    }

    /**
     * Creates SPARQL clauses implementing the date filter.
     * @param string $from date from filter value
     * @param string $until date to filter value
     * @return string
     */
    private function getDateFilter(string $from, string $until): string {
        $filter = array();
        $param  = array();
        if ($from) {
            $filter[] = '?date >= ?#^^xsd:dateTime';
            $param[]  = $from;
        }
        if ($until) {
            $filter[] = '?date <= ?#^^xsd:dateTime';
            $param[]  = $until;
        }
        $filter = implode(' && ', $filter);
        $filter = $filter ? 'FILTER (' . $filter . ')' : '';
        $filter = new SimpleQuery($filter, $param);
        $filter = $filter->getQuery();
        return $filter;
    }

    /**
     * Creates SPARQL clause implementing the set filter.
     * @param string $set set filter value
     * @return string
     */
    private function getSetFilter(string $set): string {
        if (!$set) {
            return '';
        }
        $class = RC::get('oaiSetClass');
        /* @var $class \acdhOeaw\oai\set\SetInterface */
        return $class::getSetFilter('?res', $set);
    }

    /**
     * Creates SPARQL clause getting information on set membership
     * @return string
     */
    private function getSetClause(): string {
        $class = RC::get('oaiSetClass');
        /* @var $class \acdhOeaw\oai\set\SetInterface */
        return $class::getSetClause('?res', '?set');
    }

}
