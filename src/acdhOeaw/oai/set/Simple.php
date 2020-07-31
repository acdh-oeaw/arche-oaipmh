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

namespace acdhOeaw\oai\set;

use PDO;
use acdhOeaw\oai\data\SetInfo;
use acdhOeaw\acdhRepoLib\QueryPart;

/**
 * Provides very simple and straightforward implementation of sets.
 * 
 * &llt;setSpec&gt; are simply taken from a given RDF property of the resource's
 * metadata.
 * 
 * It allows hierarchical sets but there is no support for set names and
 * set metadata (it would require to make some assumptions on how sets are
 * described in the repository - see the `acdhOeaw\oai\set\Complex` class).
 *
 * @author zozlak
 */
class Simple implements SetInterface {

    /**
     * Configuration object
     * @var object
     */
    protected $config;

    public function __construct(object $config) {
        $this->config = $config;
    }

    public function getSetFilter(string $set): QueryPart {
        $query        = new QueryPart();
        if (!empty($set)) {
            $query->query = "
                SELECT id
                FROM metadata
                WHERE property = ? AND value = ?
              UNION
                SELECT r.id
                FROM relations r JOIN identifiers i ON r.target_id = i.id
                WHERE property = ? AND ids = ?
            ";
            $query->param = [$this->config->setProp, $set, $this->config->setProp, $this->config->setNameNamespace . $set];
        }
        return $query;
    }

    public function getSetData(): QueryPart {
        $query        = new QueryPart();
        $query->query = "
            SELECT id, value AS set
            FROM metadata
            WHERE property = ?
          UNION
            SELECT r.id, substring(ids, ?::int) AS set
            FROM relations r JOIN identifiers i ON r.target_id = i.id
            WHERE property = ? AND ids LIKE ?
        ";
        $query->param = [$this->config->setProp, strlen($this->config->setNameNamespace) + 1, $this->config->setProp, $this->config->setNameNamespace . '%'];
        return $query;
    }

    public function listSets(PDO $pdo): array {
        $query = "
            SELECT DISTINCT * FROM (
                SELECT value AS set FROM metadata WHERE property = ?
              UNION
                SELECT substring(ids, ?::int) AS set
                FROM (
                    SELECT DISTINCT ids
                    FROM relations r JOIN identifiers i ON r.target_id = i.id 
                    WHERE property = ? AND ids LIKE ?
                ) t1
            ) t2
        ";
        $param = [$this->config->setProp, strlen($this->config->setNameNamespace) + 1, $this->config->setProp, $this->config->setNameNamespace . '%'];
        $query = $pdo->prepare($query);
        $query->execute($param);
        $ret = [];
        while($i = $query->fetch(PDO::FETCH_OBJ)) {
            $ret[] = new SetInfo($i->set, $i->set, null);
        }
        return $ret;
    }

}
