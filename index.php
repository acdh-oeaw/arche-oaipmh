<?php

/* 
 * The MIT License
 *
 * Copyright 2017 zozlak.
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

require_once 'vendor/autoload.php';
require_once 'src/acdhOeaw/oai/Oai.php';

use zozlak\util\Config;
use zozlak\util\ClassLoader;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\oai\MetadataFormat;
use acdhOeaw\oai\RepositoryInfo;
use acdhOeaw\oai\Oai;

$loader = new ClassLoader('src');
$config = new Config('config.ini', true);

$formats = array(
    new MetadataFormat(array(
        'metadataPrefix' => 'oai_dc',
        'schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
        'metadataNamespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
        'rdfProperty' => '',
        'class' => '\\acdhOeaw\oai\DcMetadata'
    )),
    new MetadataFormat(array(
        'metadataPrefix' => 'cmdi_collection',
        'schema' => 'http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1345561703620/xsd',
        'metadataNamespace' => 'http://www.clarin.eu/cmd/',
        'rdfProperty' => 'https://vocabs.acdh.ac.at/#hasCMDIcollection',
        'class' => '\\acdhOeaw\oai\ResMetadata'
    )),
    new MetadataFormat(array(
        'metadataPrefix' => 'cmdi_lexRes',
        'schema' => 'http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1290431694579/xsd',
        'metadataNamespace' => 'http://www.clarin.eu/cmd/',
        'rdfProperty' => 'https://vocabs.acdh.ac.at/#hasCMDIlexRes',
        'class' => '\\acdhOeaw\oai\ResMetadata'
    )),
    new MetadataFormat(array(
        'metadataPrefix' => 'cmdi_teiHdr',
        'schema' => 'http://www.clarin.eu/cmd/ http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1380106710826/xsd',
        'metadataNamespace' => 'http://www.clarin.eu/cmd/',
        'rdfProperty' => 'https://vocabs.acdh.ac.at/#hasCMDIteiHdr',
        'class' => '\\acdhOeaw\oai\ResMetadata'
    )),
    new MetadataFormat(array(
        'metadataPrefix' => 'cmdi_textCorpus',
        'schema' => 'http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1290431694580/xsd',
        'metadataNamespace' => 'http://www.clarin.eu/cmd/',
        'rdfProperty' => 'https://vocabs.acdh.ac.at/#hasCMDItextCorpus',
        'class' => '\\acdhOeaw\oai\ResMetadata'
    ))
);
$info = new RepositoryInfo('CCV', 'https://oai.localhost');
$info->adminEmail[] = 'acdh-tech@oeaw.ac.at';

$fedora = new Fedora($config);
$oai = new Oai($info, $formats, $fedora);
$oai->handleRequest();
