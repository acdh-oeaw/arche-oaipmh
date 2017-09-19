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

namespace acdhOeaw\oai\data;

/**
 * Container for OAI-PMH repository information
 *
 * @author zozlak
 */
class RepositoryInfo {

    /**
     * Repository name to be reported
     * @var type 
     */
    public $repositoryName;

    /**
     * OAI-PMH service location
     * @var type 
     */
    public $baseUrl;

    /**
     * OAI-PMH protocol version.
     * @var string
     */
    public $protocolVersion   = '2.0';

    /**
     * Earliest date which can be reported.
     * @var string
     */
    public $earliestDatestamp = '1900-01-01T00:00:00Z';

    /**
     * If repository supports information on deleted records (no by default).
     * @var string
     */
    public $deletedRecord     = 'no';

    /**
     * Date granularity (defaults to Fedora dates granularity)
     * @var string
     */
    public $granularity       = 'YYYY-MM-DDThh:mm:ssZ';

    /**
     * List of repository admin emails
     * @var array
     */
    public $adminEmail        = array();

    /**
     * Creates a RepositoryInfo object by setting up values without defaults.
     * @param string $repositoryName repository name to report
     * @param string $baseUrl OAI-PMH location
     */
    public function __construct(string $repositoryName, string $baseUrl) {
        $this->repositoryName = $repositoryName;
        $this->baseUrl        = $baseUrl;
    }

}
