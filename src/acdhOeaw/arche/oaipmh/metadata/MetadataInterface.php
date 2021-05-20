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

use DOMElement;
use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;

/**
 * Interface for different metadata providers.
 * 
 * OAI-PMH metadata can be generated in various ways. This interface provides
 * a common API enabling the \acdhOeaw\arche\oaipmh\Oai class to handle metadata no 
 * matter how they are generated.
 * 
 * @author zozlak
 */
interface MetadataInterface {

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param RepoResourceDb $resource a repository 
     *   resource object
     * @param object $searchResultRow SPARQL search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(RepoResourceDb $resource,
                                object $searchResultRow, MetadataFormat $format);

    /**
     * Returns resource's XML metadata
     */
    public function getXml(): DOMElement;

    /**
     * Allows to extend a search query with additional clauses specific to the
     * given metadata source.
     * 
     * Remark! PHP doesn't consider static methods as an interface part 
     * therefore existance of this method in classes implementing this interface
     * is not enforced.
     * 
     * @param MetadataFormat $format metadata format descriptor
     * @return QueryPart 
     */
    static public function extendSearchDataQuery(MetadataFormat $format): QueryPart;

    /**
     * Allows to extend a search query with additional clauses specific to the
     * given metadata source.
     * 
     * Remark! PHP doesn't consider static methods as an interface part 
     * therefore existance of this method in classes implementing this interface
     * is not enforced.
     * 
     * @param MetadataFormat $format metadata format descriptor
     * @return QueryPart 
     */
    static public function extendSearchFilterQuery(MetadataFormat $format): QueryPart;
}
