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

namespace acdhOeaw\oai\metadata;

use acdhOeaw\fedora\metadataQuery\SimpleQuery;
use acdhOeaw\oai\data\MetadataFormat;

/**
 * Specialization of ResMetadata class checking if the CMDI schema matches
 * metadata format requested by the user.
 * 
 * Required metadata format definitition properties:
 * - `cmdiResProp` 
 * - `cmdiSchemaProp`
 * - `idProp` 
 * so that SPARQL path `?res cmdiResProp / ^idProp ?metaRes` will fetch right
 * metadata resources assuming that `?metaRes cmdiSchemaProp 'cmdiResSchema'` 
 * matches the requested metadata format schema.
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
     * @param string $resVar name of the SPARQL variable holding the repository
     *   resource URI
     * @return string
     * @see __construct()
     */
    static public function extendSearchQuery(MetadataFormat $format,
                                             string $resVar): string {
        $param = array(
            $format->cmdiResProp,
            $format->idProp,
            $format->cmdiSchemaProp,
            $format->schema
        );
        $query = new SimpleQuery($resVar . " ?@ / ^?@ ?metaRes . \n ?metaRes ?@ ?@ .", $param);
        return $query->getQuery();
    }

}
