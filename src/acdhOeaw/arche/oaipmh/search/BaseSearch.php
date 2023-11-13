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

namespace acdhOeaw\arche\oaipmh\search;

use PDO;
use Psr\Log\AbstractLogger;
use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\oaipmh\OaiException;
use acdhOeaw\arche\oaipmh\data\HeaderData;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\data\ResumptionTokenData;
use acdhOeaw\arche\oaipmh\deleted\DeletedInterface;
use acdhOeaw\arche\oaipmh\metadata\MetadataInterface;
use acdhOeaw\arche\oaipmh\set\SetInterface;

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
     */
    private ?MetadataFormat $format;

    /**
     * Object handling sets
     */
    private SetInterface $sets;

    /**
     * Object handling deleted resources information
     */
    private DeletedInterface $deleted;

    /**
     * Configuration object
     */
    private object $config;

    /**
     * Repistory database connection object
     */
    private PDO $pdo;

    /**
     * High-level repository API handle object
     */
    private RepoDb $repo;

    /**
     * Last search results
     * @var array<HeaderData>
     */
    private array $records = [];

    /**
     * Resumption token value used
     * @var ?string
     */
    private ?string $resumptionToken;

    /**
     * Value of the resumptionCursor attribute read from the resumption dump file
     * @var int
     */
    private int $resumptionCursor = 0;

    /**
     * Total number of resources of the original search read from the resumption dump file
     * @var int
     */
    private int $resumptionCount;

    /**
     * Object creation timestamp. Used to determine resumption timeout.
     * @var int
     */
    private int $t0;

    /**
     * @param SetInterface $sets
     * @param DeletedInterface $deleted
     * @param object $config configuration object
     * @param PDO $pdo repository database connection object
     */
    public function __construct(SetInterface $sets, DeletedInterface $deleted,
                                object $config, PDO $pdo) {
        $this->sets    = $sets;
        $this->deleted = $deleted;
        $this->config  = $config;
        $this->pdo     = $pdo;
        $this->t0      = time();

        $baseUrl    = $this->config->repoBaseUrl;
        $schema     = new Schema($config);
        $headers    = new Schema((object) []);
        $this->repo = new RepoDb($baseUrl, $schema, $headers, $pdo, []);
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
     * @return HeaderData
     */
    public function getHeader(int $pos): HeaderData {
        return $this->records[$pos];
    }

    /**
     * Provides the `MetadataInterface` object for a given search result.
     * @param int $pos seach result resource index
     * @return MetadataInterface
     */
    public function getMetadata(int $pos): MetadataInterface {
        $resource = new RepoResourceDb((string) $this->records[$pos]->repoid, $this->repo);
        $result   = new $this->format->class($resource, $this->records[$pos], $this->format);
        return $result;
    }

    public function checkResumptionTimeout(): bool {
        return microtime(true) - $this->t0 >= $this->config->resumptionTimeout;
    }

    public function getResumptionToken(int $pos): ResumptionTokenData {
        $token     = $this->resumptionToken ?? bin2hex(random_bytes(4)) . bin2hex((string) time());
        $data      = [
            'metadataPrefix' => $this->format->metadataPrefix,
            'count'          => $this->resumptionCount ?? count($this->records),
            'cursor'         => isset($this->resumptionCount) ? $this->resumptionCount - count($this->records) : 0,
            'records'        => array_slice($this->records, $pos + 1),
        ];
        $expiresAt = date('Y-m-d\TH:i:s\Z', time() + $this->config->resumptionKeepAlive);
        if (!file_exists($this->config->resumptionDir)) {
            mkdir($this->config->resumptionDir, 0750, true);
        }
        $file = $this->config->resumptionDir . "/" . $token;
        if (count($data['records']) > 0) {
            file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
        } elseif (file_exists($file)) {
            unlink($file);
        }
        return new ResumptionTokenData($token, $this->format->metadataPrefix, $expiresAt, $data['count'], $data['cursor']);
    }

    public function findResumptionToken(string $token): string {
        // cleanup resumptionDir
        $dir = $this->config->resumptionDir;
        $t   = time() - $this->config->resumptionKeepAlive;
        foreach (scandir($dir) as $i) {
            if (filemtime("$dir/$i") < $t) {
                unlink("$dir/$i");
            }
        }

        if (!file_exists("$dir/$token")) {
            throw new OaiException('badResumptionToken');
        }

        $this->resumptionToken  = $token;
        $this->records          = [];
        $data                   = json_decode(file_get_contents("$dir/$token"));
        $this->resumptionCursor = $data->cursor;
        $this->resumptionCount  = $data->count;
        foreach ($data->records as $i) {
            $this->records[] = new HeaderData($i);
        }
        return $data->metadataPrefix;
    }

    public function find(string $id, string $from, string $until, string $set): void {
        $extFilterQP = new QueryPart();
        $extDataQP   = new QueryPart();
        if (isset($this->format)) {
            $class       = $this->format->class;
            $extFilterQP = $class::extendSearchFilterQuery($this->format);
            $extDataQP   = $class::extendSearchDataQuery($this->format);
        }

        $idFilterQP    = $this->getIdFilter($id);
        $setFilterQP   = $this->sets->getSetFilter($set);
        $dateFilterQP  = $this->getDateFilter($from, $until);
        $delDataQP     = $this->deleted->getDeletedData();
        $setDataQP     = $this->sets->getSetData();
        $query         = "
            WITH valid AS MATERIALIZED (
                SELECT id
                FROM
                    resources
                    " . $idFilterQP->join("JOIN", "USING (id)") . "
                    " . $setFilterQP->join("JOIN", "USING (id)") . "
                    " . $dateFilterQP->join("JOIN", "USING (id)") . "
                    " . $extFilterQP->join("JOIN", "USING (id)") . "
            )
            SELECT 
                v.id AS repoid, 
                i.ids AS id, 
                to_char(m1.value_t, 'YYYY-MM-DD') || 'T' || to_char(m1.value_t, 'HH24:MI:SS') || 'Z' AS date,
                deleted, json_agg(set) AS sets
            FROM 
                valid v
                JOIN identifiers i ON v.id = i.id AND ids LIKE ?
                JOIN metadata m1 ON v.id = m1.id AND m1.property = ?
                LEFT JOIN (" . $delDataQP->query . ") d ON v.id = d.id
                LEFT JOIN (" . $setDataQP->query . ") s ON v.id = s.id
            GROUP BY 1, 2, 3, 4
            ORDER BY 1
        ";
        $param         = array_merge(
            $idFilterQP->param,
            $setFilterQP->param,
            $dateFilterQP->param,
            $extFilterQP->param,
            [$this->config->idNmsp . '%', $this->config->dateProp],
            $extDataQP->param,
            $delDataQP->param,
            $setDataQP->param
        );
        $query         = $this->pdo->prepare($query);
        $query->execute($param);
        $query->setFetchMode(PDO::FETCH_CLASS, HeaderData::class);
        $this->records = $query->fetchAll();
        foreach ($this->records as $i) {
            $i->sets = array_filter(json_decode($i->sets));
        }
    }

    /**
     * Creates SPARQL query clause implementing the id filter.
     * @param string $id id filter value
     * @return QueryPart
     */
    private function getIdFilter(string $id): QueryPart {
        $filter = new QueryPart();
        if (!empty($id)) {
            $filter->query = "SELECT id FROM identifiers WHERE ids = ?";
            $filter->param = [$id];
        }
        return $filter;
    }

    /**
     * Creates SPARQL clauses implementing the date filter.
     * @param string $from date from filter value
     * @param string $until date to filter value
     * @return QueryPart
     */
    private function getDateFilter(string $from, string $until): QueryPart {
        $filter = new QueryPart();
        if (!empty($from) || !empty($until)) {
            $filter->query   = "SELECT id FROM metadata WHERE property = ?";
            $filter->param[] = $this->config->dateProp;
        }
        if (!empty($from)) {
            $filter->query   .= " AND date_trunc('second', value_t) >= ?::timestamp";
            $filter->param[] = $from;
        }
        if (!empty($until)) {
            $filter->query   .= " AND date_trunc('second', value_t) <= ?::timestamp";
            $filter->param[] = $until;
        }
        return $filter;
    }

    /**
     * Creates SPARQL clause implementing the set filter.
     * @param string $set set filter value
     * @return QueryPart
     */
    private function getSetFilter(string $set): QueryPart {
        if (empty($set)) {
            return new QueryPart();
        }
        $class = $this->config->setClass;
        /* @var $class SetInterface */
        return $class::getSetFilter($set);
    }

    /**
     * Sets a logger for the search object
     * @param AbstractLogger $log
     * @return void
     */
    public function setLogger(AbstractLogger $log): void {
        $this->repo->setQueryLog($log);
    }

    /**
     * Sets metadata format configuration
     * @param ?MetadataFormat $format metadata format descriptor
     * @return void
     */
    public function setMetadataFormat(?MetadataFormat $format): void {
        $this->format = $format;
    }
}
