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

use acdhOeaw\acdhRepoLib\QueryPart;

/**
 * Interface for OAI-PMH deleted records implementations.
 * @author zozlak
 */
interface DeletedInterface {

    public function __construct(object $config);

    /**
     * Returns the OAI-PMH `identify` response's `deletedRecord` value.
     * ("no", "transient" or "persistent")
     * 
     * @return string
     */
    public function getDeletedRecord(): string;

    /**
     * Returns an SQL query returning a table with two columns:
     * 
     * - `id` [bigint] providing a repository resource id
     * - `deleted` [bool] indication if the resource is deleted
     * 
     * Query may skip resources which are not deleted but it has to always return
     * above-mentioned columns (even with no rows).
     * 
     * @return \acdhOeaw\oai\QueryPart
     */
    public function getDeletedData(): QueryPart;
}
