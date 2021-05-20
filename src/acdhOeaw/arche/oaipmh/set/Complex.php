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

namespace acdhOeaw\arche\oaipmh\set;

use stdClass;
use PDO;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\data\SetInfo;
use acdhOeaw\arche\oaipmh\metadata\DcMetadata;
use zozlak\queryPart\QueryPart;

/**
 * Provides full sets support.
 * 
 * 
 *
 * @author zozlak
 */
class Complex implements SetInterface {

    /**
     * Configuration object
     * @var object
     */
    private $config;

    public function __construct(object $config) {
        $this->config = $config;
    }

    public function getSetFilter(string $set): QueryPart {
        $query = new QueryPart();
        if (!empty($set)) {
            $query->query = "
                SELECT r.id
                FROM
                    relations r
                    JOIN metadata m ON r.target_id = m.id
                WHERE
                    r.property = ?
                    AND m.property = ?
                    AND m.value = ?
            ";
            $query->param = [$this->config->setProp, $this->config->setSpecProp,
                $set];
        }
        return $query;
    }

    public function getSetData(): QueryPart {
        $query        = new QueryPart();
        $query->query = "
            SELECT r.id, m.value AS set 
            FROM
                relations r
                JOIN metadata m ON r.target_id = m.id
            WHERE
                r.property = ?
                AND m.property = ?
        ";
        $query->param = [$this->config->setProp, $this->config->setSpecProp];
        return $query;
    }

    /**
     * 
     * @param PDO $pdo
     * @return array<SetInfo>
     */
    public function listSets(PDO $pdo): array {
        $query = "
            SELECT 
                s.id,
                m1.value AS spec,
                m2.value AS name,
                json_agg(row_to_json(m.*)) AS meta
            FROM
                (
                    SELECT target_id AS id
                    FROM relations
                    WHERE property = ?
                ) s
                JOIN metadata_view m USING (id)
                JOIN metadata m1 ON s.id = m1.id AND m1.property = ?
                JOIN metadata m2 ON s.id = m2.id AND m2.property = ?
            GROUP BY 1, 2, 3
            ORDER BY 1
        ";
        $param = [$this->config->setProp, $this->config->setSpecProp, $this->config->setNameProp];
        $query = $pdo->prepare($query);
        $query->execute($param);

        $ret = [];
        while ($i   = $query->fetch(PDO::FETCH_OBJ)) {
            $meta  = new DcMetadata(json_decode($i->meta), new stdClass(), new MetadataFormat());
            $ret[] = new SetInfo($i->spec, $i->name, $meta->getXml());
        }
        return $ret;
    }

}
