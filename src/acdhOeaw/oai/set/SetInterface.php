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
use zozlak\queryPart\QueryPart;
use acdhOeaw\oai\data\SetInfo;

/**
 * Interface for OAI-PMH sets implementations.
 * 
 * @author zozlak
 */
interface SetInterface {

    public function __construct(object $config);

    /**
     * Returns an SQL query returning a table with an `id` column providing
     * repository resource ids belonging to a given set.
     * 
     * @param string $set setSpec value to be matched
     * @return QueryPart
     */
    public function getSetFilter(string $set): QueryPart;

    /**
     * Returns an SQL query returning a table with two columns:
     * 
     * - `id` providing a repository resource id
     * - `set` providing a name of the set a resource belongs to
     * 
     * If a resource belongs to many sets, many rows should be returned.
     * 
     * @return QueryPart
     */
    public function getSetData(): QueryPart;

    /**
     * Handles the `ListSets` OAI-PMH request.
     * @param PDO $pdo repository database connection object
     * @return array<SetInfo>
     */
    public function listSets(PDO $pdo): array;
}
