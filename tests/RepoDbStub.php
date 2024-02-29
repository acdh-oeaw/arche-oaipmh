<?php

/*
 * The MIT License
 *
 * Copyright 2024 zozlak.
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

namespace acdhOeaw\arche\oaipmh\tests;

use PDO;
use PDOStatement;
use rdfInterface\LiteralInterface;
use quickRdf\Dataset;
use quickRdf\DataFactory;
use quickRdfIo\Util as QuickRdfIoUtil;
use termTemplates\QuadTemplate;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\AuthInterface;
use acdhOeaw\arche\lib\SearchConfig;

/**
 * Simulates the acdhOeaw\arche\lib\RepoDb object.
 * Performs searches based on a data in a given RDF file instead of a real repository.
 *
 * @author zozlak
 */
class RepoDbStub extends \acdhOeaw\arche\lib\RepoDb {

    static function factoryTest(string $baseUrl, Schema $schema,
                                string $metaFile) {
        $repo          = new self($baseUrl, $schema, new Schema([]), new PDO('sqlite::memory:'));
        $repo->dataset = new Dataset();
        $repo->dataset->add(QuickRdfIoUtil::parse($metaFile, new DataFactory()));
        return $repo;
    }

    private Dataset $dataset;

    public function __construct(string $baseUrl, Schema $schema,
                                Schema $headers, PDO $pdo) {
        parent::__construct($baseUrl, $schema, $headers, $pdo);
    }

    public function getGraphBySqlQuery(string $query, array $parameters,
                                       SearchConfig $config): Dataset {
        $baseUrl        = $this->getBaseUrl();
        static $riQuery = "
                WITH t AS (SELECT * FROM get_relatives(:id, :predicate, 999999, 0))
                SELECT id FROM t WHERE n > 0 AND n = (SELECT max(n) FROM t)";
        static $rQuery  = "
                WITH t AS (SELECT * FROM get_relatives(:id, :predicate, 0, -999999))
                SELECT id FROM t WHERE n < 0 AND n = (SELECT min(n) FROM t)";
        static $iQuery  = "SELECT id FROM relations WHERE property = :predicate AND target_id = :id";
        if ($query === $riQuery || $query === $rQuery) {
            $inverse  = $query === $riQuery;
            $data     = new Dataset();
            $tmpl     = new QuadTemplate(null, $parameters['predicate']);
            $prevSbjs = [];
            $sbjs     = [DataFactory::namedNode($baseUrl . $parameters['id'])];
            while (count($sbjs) > 0) {
                $objs = [];
                if ($inverse) {
                    foreach ($sbjs as $j) {
                        foreach ($this->dataset->getIterator($tmpl->withObject($j)) as $k) {
                            $objs[] = $k->getSubject();
                            $data->add($k);
                        }
                    }
                } else {
                    foreach ($sbjs as $j) {
                        foreach ($this->dataset->getIterator($tmpl->withSubject($j)) as $k) {
                            $objs[] = $k->getObject();
                            $data->add($k);
                        }
                    }
                }
                $prevSbjs = $sbjs;
                $sbjs     = $objs;
            }
            return $data;
        } elseif ($query === $iQuery) {
            $tmpl = new QuadTemplate(null, $parameters['predicate'], DataFactory::namedNode($baseUrl . $parameters['id']));
            $data = new Dataset();
            foreach ($this->dataset->listSubjects($tmpl) as $sbj) {
                $data->add($this->dataset->getIterator(new QuadTemplate($sbj)));
            }
            return $data;
        } else {
            print_r([$query, $parameters]);
            throw new \RuntimeException('Unsupported scenario');
        }
    }

    public function getPdoStatementBySearchTerms(array $searchTerms,
                                                 SearchConfig $config): PDOStatement {
        $baseUrl    = $this->getBaseUrl();
        $baseUrlLen = strlen($baseUrl);
        $idPred     = (string) $this->getSchema()->id;
        $pdo        = new PDO('sqlite::memory:');
        $pdo->query("CREATE TABLE meta (id int, property text, type text, lang text, value text);");
        $query      = $pdo->prepare("INSERT INTO meta VALUES (?, ?, ?, ?, ?)");
        if (count($searchTerms) === 1 && $searchTerms[0]->type === 'id') {
            // resource fetching its own metadata
            $id   = $searchTerms[0]->value;
            $tmpl = new QuadTemplate(DataFactory::namedNode($baseUrl . $id));
            $query->execute([$id, $idPred, 'ID', '', $baseUrl . $id]);
            foreach ($this->dataset->getIterator($tmpl) as $quad) {
                $pred = (string) $quad->getPredicate();
                $obj  = $quad->getObject();
                $type = $obj instanceof LiteralInterface ? $obj->getDatatype() : ($pred === $idPred ? 'ID' : 'REL');
                $lang = $obj instanceof LiteralInterface ? $obj->getLang() : '';
                $val  = (string) $obj;
                $val  = $type === 'REL' ? substr($val, $baseUrlLen) : $obj;
                $query->execute([$id, $pred, $type, $lang, $val]);
            }
        } else {
            print_r($searchTerms);
            throw new \RuntimeException('Unsupported scenario');
        }
        //print_r($pdo->query("SELECT * FROM meta")->fetchAll(PDO::FETCH_OBJ));
        return $pdo->query("SELECT * FROM meta");
    }
}
