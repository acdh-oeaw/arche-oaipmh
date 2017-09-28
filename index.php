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

require_once 'vendor/autoload.php';
require_once 'src/acdhOeaw/oai/Oai.php';

use zozlak\util\Config;
use zozlak\util\ClassLoader;
use acdhOeaw\oai\Oai;
use acdhOeaw\oai\data\MetadataFormat;
use acdhOeaw\oai\data\RepositoryInfo;
use acdhOeaw\util\RepoConfig as RC;

$loader = new ClassLoader('src');
$config = new Config('config.ini', true);
RC::init('config.ini');

$formats = array();
foreach ($config as $i) {
    if (is_array($i) && isset($i['metadataPrefix'])) {
        $formats[] = new MetadataFormat($i);
    }
}
$info = new RepositoryInfo(array(
    'repositoryName' => RC::GET('oaiRepositoryName'), 
    'baseUrl' => RC::get('oaiBaseUrl'),
    'earliestDatestamp' => RC::get('oaiEarliestDatestamp'),
    'adminEmail' => array(RC::get('oaiAdminEmail'))
));

$oai = new Oai($info, $formats);
$oai->handleRequest();
