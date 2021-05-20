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

namespace acdhOeaw\oai\deleted;

use zozlak\queryPart\QueryPart;

/**
 * Implementation of the `acdhOeaw\oai\deleted\DeletedInterface` deriving
 * information on a resource deletion from existence of a given RDF triple
 * in the resource metadata.
 *
 * Required configuration properties:
 * - oaiDeletedRecord value to be reported in the `deletedRecord` field of the
 *   OAI-PMH `identify` response ("transient" or "persistent" - see
 *   https://www.openarchives.org/OAI/openarchivesprotocol.html#DeletedRecords)
 * - oaiDeletedProp - RDF property which existence indicates a resource is 
 *   deleted
 * - oaiDeletedPropValue
 * 
 * @author zozlak
 */
class RdfProperty implements DeletedInterface {

    /**
     * Configuration object
     * @var object
     */
    private $config;

    public function __construct(object $config) {
        $this->config = $config;
    }

    public function getDeletedRecord(): string {
        return $this->config->deletedRecord;
    }

    public function getDeletedData(): QueryPart {
        $query        = new QueryPart();
        $query->query = "SELECT id, true AS deleted FROM metadata WHERE property = ?";
        $query->param = [$this->config->deletedProp];
        if (!empty($this->config->deletedPropValue)) {
            $query->query   .= " AND value = ?";
            $query->param[] = $this->config->deletedPropValue;
        }
        return $query;
    }

}
