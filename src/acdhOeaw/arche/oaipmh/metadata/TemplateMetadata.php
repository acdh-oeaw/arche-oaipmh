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
use quickRdf\DataFactory as DF;
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
     * @var array<DatasetNodeInterface>
     */
    private array $metaStack;

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
        //TODO - output something to avoid timeout
        //TODO - cache

        try {
            if (!isset($this->xml)) {
                $this->loadDocument();
            }

            $this->metaStack = [$this->res->getGraph()];

            $this->processElement($this->xml->documentElement);
        } catch (Throwable $e) {
            if ($this->format->xmlErrors ?? false) {
                $msg = $e->getMessage() . " in " . $e->getFile() . "(" . $e->getLine() . ")\n\n" . $e->getTraceAsString();
                $doc = new DOMDocument('1.0', 'UTF-8');
                $err = $doc->createElement('error', $msg);
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
        $this->removeUnneededNodes($el);

        $if = trim($el->getAttribute('if'));
        if (!$this->evaluateIf($if)) {
            $el->parentNode->removeChild($el);
            return;
        }
        $el->removeAttribute('if');
        if (!empty($if) && !empty($el->getAttribute('remove'))) {
            $this->removePreservingChildren($el);
        }

        $foreach = $el->getAttribute('foreach');
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
            $remove = $el->getAttribute('remove');
            $el->removeAttribute('foreach');
            $meta   = end($this->metaStack);
            $tmpl   = new QT($meta->getNode(), $this->expand($foreach));
            foreach ($meta->getDataset()->getIterator($tmpl) as $val) {
                $localEl           = $el->cloneNode(true);
                $this->metaStack[] = $meta->copyExcept($val, true);
                $this->processElement($localEl);
                array_pop($this->metaStack);
                $el->after($localEl);
                if (!empty($remove)) {
                    $this->removePreservingChildren($localEl);
                }
            }
            $el->parentNode->removeChild($el);
        }
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

    private function processValue(DOMDocument | DOMElement $el): void {
        $val = Value::fromDomElement($el);
        if (empty($val->path)) {
            return;
        }
        $val->insert($el, $this->fetchValues($val));
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
            $meta    = end($this->metaStack);
            $tmpl    = new QT($meta->getNode(), $this->expand($matches[2]));
            $value   = $meta->getDataset()->copy($tmpl)->$func(new QT(object: $value));
            $current = $current->push(ParseTreeNode::fromValue($value, $matches[0]));
            $if      = substr($if, strlen($matches[0]));

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

    private function fetchValues(Value $val): array {
        $result = match ($val->path) {
            'NOW' => (new DateTimeImmutable())->format(DateTimeImmutable::ISO8601),
            'URI', 'URL' => $this->headerData->repoid,
            'METAURL' => $this->headerData->repoid . '/metadata',
            'OAIID' => $this->headerData->id,
            'OAIURL' => $this->format->info->baseURL . '?verb=GetRecord&metadataPrefix=' . rawurlencode($this->format->metadataPrefix) . '&identifier=' . rawurldecode($this->headerData->id),
            'RANDOM' => rand(),
            'SEQ' => $this->seqNo++,
            default => null,
        };
        if ($result !== null) {
            return [$result];
        }
        $tmpl = new QT($this->res->getUri(), DF::namedNode($this->expand($val->path)));
        return iterator_to_array($this->res->getGraph()->getDataset()->listObjects($tmpl)->getValues());
    }
}
