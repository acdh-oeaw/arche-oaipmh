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
use acdhOeaw\oai\OaiException;
use zozlak\queryPart\QueryPart;
use acdhOeaw\oai\data\SetInfo;

/**
 * Implements proper reporting of repository without sets.
 *
 * @author zozlak
 */
class NoSets implements SetInterface {

    private object $config;

    public function __construct(object $config) {
        $this->config = $config;
    }

    /**
     * Reports no support for sets
     * 
     * @param string $set
     * @return QueryPart
     * @throws OaiException
     */
    public function getSetFilter(string $set): QueryPart {
        throw new OaiException('noSetHierarchy');
    }

    /**
     * Returns empty set name
     * 
     * @return QueryPart
     * @throws OaiException
     */
    public function getSetData(): QueryPart {
        return new QueryPart("SELECT id, null::text AS set FROM resources");
    }

    /**
     * Reports no support for sets
     * 
     * @return array<SetInfo>
     * @throws OaiException
     */
    public function listSets(PDO $pdo): array {
        throw new OaiException('noSetHierarchy');
    }
}
