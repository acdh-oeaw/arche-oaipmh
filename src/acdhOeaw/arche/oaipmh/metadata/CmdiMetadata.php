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

namespace acdhOeaw\arche\oaipmh\metadata;

use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;

/**
 * Specialization of ResMetadata class checking if the CMDI schema matches
 * metadata format requested by the user.
 * 
 * Required metadata format definitition properties:
 * - `metaResProp` 
 * - `cmdiSchemaProp`
 * - `schema`
 * - `requestOptions` - Guzzle request options (http://docs.guzzlephp.org/en/stable/request-options.html)
 *   to be used while fetching the metadata resource
 *
 * @author zozlak
 */
class CmdiMetadata extends ResMetadata {

    /**
     * Returns a SPARQL search query part:
     * - fetching additional data required by the `__construct()` method (implemented in parent class)
     * - assuring that the linked CMDI resource has the right schema
     * 
     * @param MetadataFormat $format metadata format descriptor
     * @return QueryPart
     * @see __construct()
     */
    static public function extendSearchFilterQuery(MetadataFormat $format): QueryPart {
        $query        = new QueryPart();
        $query->query = "
            SELECT DISTINCT r.id 
            FROM 
                relations r
                LEFT JOIN metadata m ON r.target_id = m.id AND m.property = ? AND m.value = ?
                LEFT JOIN relations rr ON r.target_id = rr.id AND rr.property = ?
                LEFT JOIN identifiers ii ON rr.id = ii.id AND ii.ids = ?
            WHERE r.property = ?
        ";
        $query->param = [
            $format->cmdiSchemaProp, $format->schema, // m
            $format->cmdiSchemaProp, $format->schema, // rr, ii
            $format->metaResProp, // r. property
        ];
        return $query;
    }

    /**
     * This implementation has no fetch additional data trough the search query.
     * 
     * @param MetadataFormat $format metadata format descriptor
     * @return QueryPart
     */
    static public function extendSearchDataQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
    }

}
