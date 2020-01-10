<?php

/*
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

namespace acdhOeaw\oai\set;

use acdhOeaw\acdhRepoLib\QueryPart;

/**
 * Implements sets by simply taking the &llt;setSpec&gt; from a given resource's 
 * metadataRDF property. 
 * 
 * What makes it different from the `Simple` implementation is the `Acdh`
 * searches also in resource's parents
 *
 * @author zozlak
 */
class Acdh extends Simple {

    public function getSetFilter(string $set): QueryPart {
        //TODO implement inheritance
        $query        = new QueryPart();
        if (!empty($set)) {
            $query->query = "
                SELECT id
                FROM metadata
                WHERE property = ? AND value = ?
            ";
            $query->param = [$this->config->setProp, $set];
        }
        return $query;
    }

    public function getSetData(): QueryPart {
        //TODO implement inheritance
        $query        = new QueryPart();
        $query->query = "
            SELECT id, value AS set
            FROM metadata
            WHERE property = ?
        ";
        $query->param = [$this->config->setProp];
        return $query;
    }

}
