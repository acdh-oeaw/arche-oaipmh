<?php

/**
 * The MIT License
 *
 * Copyright 2024 Austrian Centre for Digital Humanities.
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

use DateTimeImmutable;
use DOMComment;
use DOMDocument;
use DOMElement;
use Exception;
use RuntimeException;
use Throwable;
use zozlak\RdfConstants as RDF;
use zozlak\queryPart\QueryPart;
use rdfInterface\NamedNodeInterface;
use rdfInterface\TermInterface;
use rdfInterface\DatasetInterface;
use quickRdf\DataFactory as DF;
use quickRdf\Dataset;
use termTemplates\QuadTemplate as QT;
use termTemplates\ValueTemplate;
use termTemplates\NumericTemplate;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\oaipmh\OaiException;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\data\HeaderData;
use acdhOeaw\arche\oaipmh\metadata\util\ParseTreeNode;
use acdhOeaw\arche\oaipmh\metadata\util\Value;
use acdhOeaw\arche\oaipmh\metadata\util\ValueMapper;

/**
 * Creates <metadata> element by filling in an XML template with values
 * read from the repository resource's metadata.
 * 
 * Required metadata format definitition properties:
 * - `templatePath` - path to the template
 * 
 * Optional metadata format definition properties:
 * -
 * 
 * XML tags in the template can be annotated with following attributes:
 * 
 * @author zozlak
 */
class TemplateMetadata implements MetadataInterface {

    const PREDICATE_REGEX  = '[-_a-zA-Z0-9]+:[^ )]*';
    const IF_VALUE_REGEX   = '(==|!=|starts|ends|contains|>|<|>=|<=|regex) +(?:"([^"]*)"|\'([^\']*)\'|([0-9]+[.]?[0-9]*)|(' . self::PREDICATE_REGEX . '))';
    const IF_PART_REGEX    = '`^ *(any|every|none)[(](' . self::PREDICATE_REGEX . ')(?: +' . self::IF_VALUE_REGEX . ')?[)]`u';
    const IF_LOGICAL_REGEX = '`^ *(OR|AND|NOT) *`';

    static private Dataset $dataset;
    static private ValueMapper $valueMapper;

    /**
     * Repository resource object
     */
    private RepoResourceDb $res;
    private HeaderData $headerData;
    private int $seqNo = 1;

    /**
     * Metadata format descriptor
     */
    private MetadataFormat $format;

    /**
     * Path to the XML template file
     */
    private string $template = '';
    private DOMDocument $xml;

    /**
     * 
     * @var array<string>
     */
    private array $xmlLocation;

    /**
     * 
     * @var array<TermInterface>
     */
    private array $nodesStack;

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param RepoResourceDb $resource a repository 
     *   resource object
     * @param HeaderData $searchResultRow search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(RepoResourceDb $resource,
                                HeaderData $searchResultRow,
                                MetadataFormat $format) {
        $this->res        = $resource;
        $this->format     = $format;
        $this->headerData = $searchResultRow;

        $this->template = $this->format->templatePath;
        if (!file_exists($this->template)) {
            throw new RuntimeException('No template matched');
        }

        if (!isset(self::$valueMapper)) {
            self::$valueMapper = new ValueMapper($this->format->valueMaps ?? null);
        }
    }

    /**
     * Creates resource's XML metadata
     *
     * If the template's root element has an `val` attribute a fake
     * root element is introduced to the template to assure it will be a valid
     * XML after the substitution (XML documents have to have a single root
     * element).
     *
     * @return DOMElement 
     */
    public function getXml(): DOMElement {
        //TODO - cache pruning - now we just cache everything
        if (!isset(self::$dataset)) {
            self::$dataset = new Dataset();
        }

        try {
            if (!isset($this->xml)) {
                $this->loadDocument();
            }

            $this->nodesStack  = [$this->res->getUri()];
            $this->xmlLocation = [];

            self::$dataset->add($this->res->getGraph()->getDataset());
            $this->processElement($this->xml->documentElement);
        } catch (Throwable $e) {
            if ($this->format->xmlErrors ?? false) {
                $doc = new DOMDocument('1.0', 'UTF-8');
                $err = $doc->createElement('error');
                $err->appendChild($doc->createElement('message', $e->getMessage()));
                $err->appendChild($doc->createElement('phpTrace', $e->getFile() . '(' . $e->getLine() . ")\n" . $e->getTraceAsString()));
                $err->appendChild($doc->createElement('templateLocation', $this->template . ':' . implode('/', $this->xmlLocation ?? [
                                ])));
                $doc->appendChild($err);
                return $err;
            } else {
                throw $e;
            }
        }

        return $this->xml->documentElement;
    }

    static public
        function extendSearchFilterQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
    }

    static public
        function extendSearchDataQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
    }

    private function processElement(DOMDocument | DOMElement $el): void {
        $this->xmlLocation[] = $el->nodeName;
        $this->removeUnneededNodes($el);

        $if = trim($el->getAttribute('if'));
        if (!$this->evaluateIf($if)) {
            $el->parentNode->removeChild($el);
            return;
        }
        $el->removeAttribute('if');
        if (!empty($if) && $el->hasAttribute('remove')) {
            $this->removePreservingChildren($el);
        }

        $foreach = $el->getAttribute('foreach');
        $el->removeAttribute('foreach');
        if (empty($foreach)) {
            $this->processValue($el);

            $child = $el->firstChild;
            while ($child) {
                $nextChild = $child->nextSibling;
                if ($child instanceof DOMElement) {
                    $this->processElement($child);
                }
                $child = $nextChild;
            }
        } else {
            $remove = $el->hasAttribute('remove');
            $val    = Value::fromPath($foreach);
            foreach ($this->fetchValues($val) as $sbj) {
                $localEl            = $el->cloneNode(true);
                $el->before($localEl);
                $this->nodesStack[] = $sbj;
                $this->processElement($localEl);
                array_pop($this->nodesStack);
                if ($remove) {
                    $this->removePreservingChildren($localEl);
                }
            }
            $el->parentNode->removeChild($el);
        }

        array_pop($this->xmlLocation);
    }

    private function removeUnneededNodes(DOMDocument | DOMElement $el): void {
        $child = $el->firstChild;
        while ($child) {
            $nextChild = $child->nextSibling;
            if (!($child instanceof DOMElement) && !($child instanceof DOMComment && $this->format->keepComments ?? false)) {
                $el->removeChild($child);
            }
            $child = $nextChild;
        }
    }

    private function loadDocument(): void {
        $this->xml                     = new DOMDocument();
        $this->xml->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $options                       = LIBXML_COMPACT | LIBXML_BIGLINES | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_PARSEHUGE | LIBXML_PEDANTIC;
        $status                        = $this->xml->load($this->template, $options);
        $warning                       = libxml_get_last_error();
        if ($status === false || $warning !== false) {
            if ($warning) {
                $warning = "($warning->message in file $warning->file:$warning->line)";
            }
            throw new Exception("Failed to parse $this->template template $warning");
        }
    }

    private function evaluateIf(string $if): bool {
        if (empty($if)) {
            return true;
        }
        $curNode = end($this->nodesStack);
        if (self::$dataset->none(new QT($curNode))) {
            $this->loadMetadata([$curNode], null, false, false);
        }
        // trick assuring we don't need to deal with some corner cases
        $result  = ParseTreeNode::fromOperator('AND', ParseTreeNode::fromValue(true));
        $current = $result;
        $matches = null;
        // parse using an implicit state-machine
        while (!empty($if)) {
            // STATE 1: righ-side operators: NOT and (
            while (preg_match('`^ *([(]|NOT) *`', $if, $matches)) {
                $current = $current->push(ParseTreeNode::fromOperator(trim($matches[1]), null));
                $if      = substr($if, strlen($matches[0]));
            }

            // STATE 2: logical term
            $valid = preg_match(self::IF_PART_REGEX, $if, $matches);
            if (!$valid) {
                throw new OaiException("Invalid if attribute value: '$if'");
            }
            $func  = $matches[1];
            $value = null;
            if (!empty($matches[3])) {
                $value = !empty($matches[7]) ? $this->expand($matches[7]) : $matches[4] . ($matches[5] ?? '') . ($matches[6] ?? '');
                if ($matches[3] === ValueTemplate::REGEX) {
                    $value = "`$value`u";
                }
                $value = empty($matches[6]) ? new ValueTemplate($value, $matches[3]) : new NumericTemplate((float) $value, $matches[3]);
            }
            $predicate = $this->expand($matches[2]);
            $tmpl      = new QT($curNode, $predicate);
            $value     = self::$dataset->copy($tmpl)->$func(new QT(object: $value));
            $current   = $current->push(ParseTreeNode::fromValue($value, $matches[0]));
            $if        = substr($if, strlen($matches[0]));

            // STATE 3: closing parenthesis
            while (preg_match('`^ *[)]`', $if, $matches)) {
                $if      = substr($if, strlen($matches[0]));
                $current = $current->matchParenthesis();
            }

            // STATE 4: double-sided operators: AND and OR
            if (preg_match(self::IF_LOGICAL_REGEX, $if, $matches)) {
                $current = ParseTreeNode::fromOperator($matches[1], $current);
                if (empty($current->parent)) {
                    $result = $current;
                }
                $if = substr($if, strlen($matches[0]));
            }
        }
        $result = $result->evaluate();
        return $result;
    }

    private function removePreservingChildren(DOMElement $el): void {
        $child = $el->firstChild;
        while ($child) {
            $el->before($child);
            $child = $child->nextSibling;
        }
        $el->parentNode->removeChild($el);
    }

    private function expand(string $prefixed): NamedNodeInterface {
        static $cache = [];
        if (!isset($cache[$prefixed])) {
            list($prefix, $localName) = explode(':', $prefixed);
            $cache[$prefixed] = DF::namedNode($this->format->rdfNamespaces[$prefix] . $localName);
        }
        return $cache[$prefixed];
    }

    private function processValue(DOMDocument | DOMElement $el): void {
        $remove = $el->getAttribute('remove') === 'remove';
        $el->removeAttribute('remove');

        $valSuffixes = [];
        foreach ($el->attributes as $attr => $i) {
            if (preg_match('`^val[0-9]?$`', $attr)) {
                $valSuffixes[] = substr($attr, 3);
            }
        }
        sort($valSuffixes);
        if (count($valSuffixes) === 0) {
            return;
        }
        $vals     = [];
        $maxCount = 0;
        $iterOver = null;
        $valid    = true;
        foreach ($valSuffixes as $i) {
            // expand map attribute when needed as Value object can't do it
            $map = $el->getAttribute('map' . $i);
            if (str_starts_with($map, '/')) {
                $el->setAttribute('map' . $i, '/' . $this->expand(substr($map, 1)));
            }

            $val   = Value::fromDomElement($el, $i);
            $val->setValues($this->fetchValues($val), self::$valueMapper);
            $count = count($val);
            if ($count > 1) {
                if ($maxCount > 1) {
                    throw new OaiException("More than one val attribute matching more than one value. Consider using the foreach attribute.");
                } else {
                    $iterOver = $val;
                }
            }
            $vals[]   = $val;
            $maxCount = max($maxCount, $count);
            $valid    &= $count > 0 || !$val->isRequired();
        }
        if ($valid) {
            $iterOver ??= $vals[0];
            for ($i = 0; $i < $iterOver->count(); $i++) {
                /* @var $valEl DOMElement */
                $valEl = $el->cloneNode(true);
                foreach ($vals as $v) {
                    $v->insert($valEl, $v === $iterOver ? $i : 0);
                }
                $el->before($valEl);

                $child = $valEl->firstChild;
                while ($child) {
                    $nextChild = $child->nextSibling;
                    if ($child instanceof DOMElement) {
                        $this->processElement($child);
                    }
                    $child = $nextChild;
                }
            }
            $el->parentNode->removeChild($el);
        } elseif ($remove) {
            $el->parentNode->removeChild($el);
        }
    }

    private function fetchValues(Value $val): array {
        $result = match ($val->path) {
            'NOW' => (new DateTimeImmutable())->format(DateTimeImmutable::ISO8601),
            'URI', 'URL' => $this->res->getRepo()->getBaseUrl() . $this->headerData->repoid,
            'METAURL' => $this->res->getRepo()->getBaseUrl() . $this->headerData->repoid . '/metadata',
            'OAIID' => $this->headerData->id,
            'OAIURL' => $this->format->info->baseURL . '?verb=GetRecord&metadataPrefix=' . rawurlencode($this->format->metadataPrefix) . '&identifier=' . rawurldecode($this->headerData->id),
            'RANDOM' => rand(),
            'SEQ' => $this->seqNo++,
            'CURNODE' => end($this->nodesStack),
            default => null,
        };
        if ($result !== null) {
            return [$result];
        }
        if (!preg_match('`^(/[\\^]?' . self::PREDICATE_REGEX . '[*]?|=.+)+$`', $val->path)) {
            throw new OaiException("Wrong value path: " . $val->path);
        }
        if (str_starts_with($val->path, '=')) {
            return [substr($val->path, 1)];
        }

        $path = explode('/', substr($val->path, 1));
        $sbjs = [end($this->nodesStack)];
        foreach ($path as $i) {
            $inverse   = str_starts_with($i, '^');
            $recursive = str_ends_with($i, '*');
            $i         = $inverse || $recursive ? substr($i, (int) $inverse, $recursive ? -1 : null) : $i;
            $i         = $this->expand($i);
            $tmpl      = new QT(null, $i);
            $deepness  = 0;
            while ($deepness === 0 || $recursive) {
                $deepness++;
                $objs = [];
                $this->loadMetadata($sbjs, $i, $inverse, $recursive);
                if ($inverse) {
                    foreach ($sbjs as $j) {
                        $objs[] = self::$dataset->listSubjects($tmpl->withObject($j));
                    }
                } else {
                    foreach ($sbjs as $j) {
                        $objs[] = self::$dataset->listObjects($tmpl->withSubject($j));
                    }
                }
                $objs = array_merge(...array_map(fn($x) => iterator_to_array($x), $objs));
                if ($recursive && count($objs) === 0 && $deepness > 1) {
                    break;
                }
                $sbjs = $objs;
            }
        }
        usort($sbjs, fn($x, $y) => ((string) $x) <=> ((string) $y));
        return $sbjs;
    }

    /**
     * 
     * @param array<TermInterface> $resource
     */
    private function loadMetadata(array $resources,
                                  TermInterface | null $predicate,
                                  bool $inverse, bool $recursive): void {
        $repo                 = $this->res->getRepo();
        $baseUrlLen           = strlen($repo->getBaseUrl());
        $query                = match (($recursive ? 'r' : '') . ($inverse ? 'i' : '')) {
            'ri' => "
                WITH t AS (SELECT * FROM get_relatives(?, ?, 999999, 0))
                SELECT id FROM t WHERE n > 0",
            'r' => "
                WITH t AS (SELECT * FROM get_relatives(?, ?, 0, -999999))
                SELECT id FROM t",
            'i' => "SELECT id FROM relations WHERE target_id = ? AND property = ?",
            '' => null,
        };
        $config               = new SearchConfig();
        $config->class        = get_class($this->res);
        $config->metadataMode = RepoResourceDb::META_RESOURCE;

        if ($inverse) {
            $resources = array_filter($resources, fn($x) => self::$dataset->none(new QT(null, $predicate, $x)));
        } else {
            $resources = array_filter($resources, fn($x) => self::$dataset->none(new QT($x)));
        }

        if ($query === null) {
            foreach ($resources as $i) {
                $res = new RepoResourceDb($i, $repo);
                self::$dataset->add($res->getGraph()->getDataset());
            }
        } else {
            foreach ($resources as $i) {
                $param = [substr($i, $baseUrlLen), $predicate];
                self::$dataset->add($repo->getGraphBySqlQuery($query, $param, $config));
            }
        }
    }
}
