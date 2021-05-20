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
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\data\HeaderData;
use acdhOeaw\arche\oaipmh\deleted\DeletedInterface;
use acdhOeaw\arche\oaipmh\metadata\MetadataInterface;
use acdhOeaw\arche\oaipmh\set\SetInterface;

/**
 * Interface for classes implementing OAI-PMH resources search.
 *
 * For performance reasons the search should be implemented as a single SPARQL
 * query gathering all data needed to generate the OAI-PMH <header> resource
 * descriptions.
 * 
 * Good implementation takes into account search query extensions provided by
 * requested metadata format class. Such extension can be gathered by calling
 * the static method `extendSearchQuery()` (see the `acdhOeaw\arche\oaipmh\metadataMetadataInterface`)
 * on the metadata class. The metadata class is provided by the `class` method
 * of the metadata format descriptor (see the `__construct()` method).
 * 
 * @author zozlak
 */
interface SearchInterface {

    /**
     * Creates a search engine object.
     * 
     * @param MetadataFormat $format metadata format descriptor
     * @param SetInterface $sets
     * @param DeletedInterface $deleted
     * @param object $config configuration object
     * @param PDO $pdo repository database connection object
     */
    public function __construct(MetadataFormat $format, SetInterface $sets,
                                DeletedInterface $deleted, object $config,
                                PDO $pdo);

    /**
     * Performs search using given filter values.
     * @param string $id id filter value
     * @param string $from date from filter value
     * @param string $until date to filter value
     * @param string $set set filter value
     */
    public function find(string $id, string $from, string $until, string $set): void;

    /**
     * Returns number of resources matching last search (last call of the 
     * `find()` method).
     */
    public function getCount(): int;

    /**
     * Provides the `HeaderData` object for a given search result.
     * @param int $pos seach result resource index
     */
    public function getHeader(int $pos): HeaderData;

    /**
     * Provides the `MetadataInterface` object for a given search result.
     * 
     * The exact class of the returned object is defined by the `class` field
     * of the metadata descriptor (see the `$format` parameter of the 
     * constructor method).
     * @param int $pos seach result resource index
     */
    public function getMetadata(int $pos): MetadataInterface;

    /**
     * Sets a logger for the search object
     * @param AbstractLogger $log
     * @return void
     */
    public function setLogger(AbstractLogger $log): void;
}
