# TemplateMetadata documentation

The `TemplateMetadata` class generates the metatada from an XML templates using special annotations.

## Referece

### val, val0, val1, ...

`(/^?{propertyPrefix}:{propertySuffix}[*]?)+` or `=any constant` or `(NOW|URI|URL|METAURL|OAIID|OAIURL|RANDOM|SEQ|CURNODE)`

Extracts values to be placed in the template.

#### `(/^?{propertyPrefix}:{propertySuffix}[*]?)+`

Extracts an RDF property values according to a given path, e.g. `/acdh:hasTitle`.

Prefixes are defined in the YAML configuration of a given metadata format.

The direction of the property can be reversed by prefixing it with a `^`,
e.g. `/^acdh:isPartOf` fetches all children of a given resource.

Paths can be of arbitrary length, e.g. `/acdh:isPartOf/acdh:hasContributor/acdh:hasTitle`.

A `*` after the property name indicates it should be followed recursively until
it is possible, e.g. `/acdh:isPartOf*/acdh:hasTitle` extracts the title of a highest-level parent.

Extracted values are then (all annotations below are optional):

* filtered according to the corresponding `match` annonation
* modified according to the corresponding `replace` annonation
* formatted according to the corresponding `format` annonation
* mapped according to the corresponding `map` annonation
* aggregated according to the corresponding `aggregate` annonation

#### `={any constant}`

Used a given constact value, e.g. `=foo` fetches a value of `foo`.

A typical use case is a conjunction of multiple metatada properties, e.g.

```xml
<period val1="acdh:hasCoverageStartDate" format1="D:Y"
        val2="=-"
        val3="acdh:hasCoverageEndDate"   format3="D:Y"/>
```

See also the `action` annotation description.

#### `(NOW|URI|URL|METAURL|OAIID|OAIURL|RANDOM|SEQ|CURNODE)`

Returns one of special values:

* `NOW` the current date and time
* `URI`, `URL` the repository-native resource identifier
* `METAURL` the URL fetching resource's metadata
* `OAIID` the resource id used as its OAI-PMH id
* `OAIURL` an URL of an OAI-PMH request fetching a given resource metadata in the current format
* `RANDOM` a random number
* `SEQ` a sequential unique number
* `CURNODE` an URI of the current metadata node - see the `foreach` annotation description

### match, match0, match1, ...

`regular expression`

Skips values which do not match a given regular expression,
e.g. `^foo` skips all values which do not begin with `foo`.

The regular expression is evaluted with the `umsD` [modifiers](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php).

For the complete reference of the regular expressions syntax supported please look [here](https://www.php.net/manual/en/reference.pcre.pattern.syntax.php).

### replace, replace0, replace1, ...

`regular expression replace expression`

Requires a corresponding `match` annotation.

Performs a regular expressions search and replace operation, e.g.

* `match=".*bar.*" replace="BAR"` on `foo bar baz` will result in `foo BAR baz`
* `match="foo(.*)baz" replace="\\1" on `foo bar baz` will result in `bar`

For the complete reference of the regular expressions syntax supported please look [here](https://www.php.net/manual/en/reference.pcre.pattern.syntax.php).

For the complete reference of the replace expression syntax please look [here](https://www.php.net/manual/en/function.preg-replace.php).
The `match` annotation is passed as the `$pattern`, the `replace` annotation as the `$replacement`
and the RDF property value as the `$subject`.

### format, format0, format1, ...

`[DUbcdeEfFgGhHosuxX]:{format-specific parameters}`

Formats value according to a given rule:

* `D:{format}` formats as date where the `format` is described [here](https://www.php.net/manual/en/datetime.format.php),
  e.g. `D:Y-m` formats the value as a 4-digit year and month separated by a `-`.
* `U:` URL-encodes the value
* `[bcdeEfFgGhHosuxX]:{flags}{width}[.]{precision}` formats the value using the [sprintf syntax](https://www.php.net/manual/en/function.sprintf.php), e.g.
   `d:04` prepends a decimal number with `0` until it is at least four characters long (turning `45` into `0045` but leaving `12345` intact),
   `f:.2` rounds a floating point number to two decimal digits precision
   and `x:` formats a number in a hex notation (turning `31` into `1f`)

### map, map0, map1, ...

`/{propertyPrefix}:{propertySuffix}` or `mapName`

* `/{propertyPrefix}:{propertySuffix}` treats value as a resolvable IRI.
  Tries to download an RDF data from it and then extracts a given RDF property values.
  If the original value can not be resolved or does not point to an RDF dataset,
  it is skipped.
  Similarly if a downloaded dataset does not contain the indicated RDF property,
  the value is skipped.
  Typically used for values being SKOS concept IRIs.
* `mapName` applies a static map defined in the YAML config.
  If a value does not exist in the map, it is skipped.

### aggregate, aggregate0, aggregate1, ...

`(min|max)(,{langCode})?`

Aggregates values into a single one.

If the language code is provided, the aggregation is performed only on values in this
language. If there are no values in the requested language, aggregation is performed on all of them.

E.g. `min,en` on `"bar"@de`, `"baz"@en` and `"foo"@en` will result with `"baz"@en`
while `min` will result in `"bar"@de`.

### as, as0, as1, ... and action, action0, action1, ...

as = `xml|text|@{propertyName}`, action = `append|overwrite`

Defines the place and way where the value should be stored.

| as              | action    | description |
|-----------------|-----------|-------------|
| xml             | append    | appends the value to the template tag content without XML entities escaping |
| xml             | overwrite | overwrites the template tag content with the value without XML entities escaping |
| text            | append    | appends the value to the template tag content applying XML entities escaping |
| text            | overwrite | overwrites the template tag content with the value applying XML entities escaping |
| @{propertyName} | append    | appends the value to the given tag's attribute value |
| @{propertyName} | overwrite | overwrites the given tag's attribute with the value |

The default values are `as="text" action="append"`.

Examples:

* ```xml
  <a val1="/acdh:foo" action1="overwrite"
     val2="/acdh:bar" action2="overwrite"
     val3="/acdh:baz" action3="overwrite"/>
  ```
  sets the tag value to the first existing property value out of `acdh:baz`, `acdh:bar` and `acdh:foo`
  (checked exactly in this order).
* ```xml
  <a val="=<b>foo</b>" as="xml"/>
  <a val="=<b>foo</b>"/>
  ```
  results in
  ```xml
  <a><b>foo></b></a>
  <a>&lt;b&gt;foo&lt;/b&gt;</a>
  ```
* ```xml
  <a val1="foo" as1="@someAttr"
     val2="bar" as2="@someAttr"/>
  ```
  results in
  ```xml
  <a someAttr="foobar"/>
  ```

### lang, lang0, lang1, ...

`if empty|overwrite`

Defines if `xml:lang` attribute based on the value's lang tag should be added to the tag.

Please note there can be multiple `val` attributes per tag and only one final `xml:lang` attribute value.

`if empty` sets the `xml:lang` only for a non-empty lang tag while `overwrite`
creates an empty `xml:lang` attribute if the value has no lang tag.

e.g.

```xml
<a val="acdh:hasTitle" lang="if empty"/>
<b val="acdh:hasTitle" lang="overwrite"/>
```

applied to metadata like `<someSubject> acdh:hasTitle "foo", "bar"@de .`

results in

```xml
<a>foo</a>
<a xml:lang="de">bar</a>
<b xml:lang="">foo</b>
<b xml:lang="de">bar</b>
```

while
```xml
<a val1="acdh:hasTitle"    lang="if empty"
   val2="acdh:hasAltTitle" lang="if empty"/>
<b val1="acdh:hasTitle"    lang="if empty"
   val2="acdh:hasAltTitle" lang="overwrite"/>
```

applied to metadata like

```
<someSubject> acdh:hasTitle "foo"@en ;
              acdh:hasAlTitle "bar"@de .
```

results in

```xml
<a xml:lang="en">foobar</a>
<b xml:lang="de">foobar</b>
```

### required, required0, required1, ...

`required|optional` default `required`

Controls the behaviour when some `val` attributes of the same tag fetch values while other not.

If any of `val` attributes fetching no values is required, then all values of all `val` attributes are discarded, e.g.

```xml
<a val1="acdh:foo" required1="required"
   val2="acdh:bar"/>
<b val1="acdh:foo" required1="optional"
   val2="acdh:bar"/>
```

applied to metadata like

```
<someSubject> acdh:bar "first value", "second value" .
```

ends up with (note the `<a>` tag skipped in the output because the non-existent `acdh:foo` RDF property was marked as `required`)

```xml
<b>first value</b>
<b>second value</b>
```

### remove

`remove`

* On tags without the `if` and `foreach` annotation.  
  By default if there are no values to display, the original tag from the template is preserved.
  Use `remove="remove"` to drop it.
* On tags with the `foreach` annotation.  
  Removes the tag from the output, e.g.
  ```xml
  <a foreach="/acdh:hasTitle><b val="CURNODE"/></a>
  ```
  will result in output like
  ```xml
  <a><b>foo</b></a>
  <a><b>bar</b></a>
  ```
  while 
  ```xml
  <a foreach="/acdh:hasTitle remove="remove"><b val="CURNODE"/></a>
  ```
  will result in output like
  ```xml
  <b>foo</b>
  <b>bar</b>
  ```
* On tags with the `if` annotation
  Removes the tag from the output, e.g.
  ```xml
  <a if="any(acdh:hasTitle)"><b val="/acdh:hasTitle"/></a>
  ```
  will result in output like
  ```xml
  <a>
    <b>foo</b>
    <b>bar</b>
  </a>
  ```
  while 
  ```xml
  <a if="any(acdh:hasTitle)" remove="remove"><b val="CURNODE"/></a>
  ```
  will result in output like
  ```xml
  <b>foo</b>
  <b>bar</b>
  ```

### foreach

`(/^?{propertyPrefix}:{propertySuffix}[*]?)+`

Creates a copy of the tag children for every value extracted according to a given path.

For the path syntax description please see at the `val` annotation description.

Note this annotation changes the subject (active node) in the RDF graph to the
currently processed value.

E.g. display a compound descritpion of all authors:

```xml
<a foreach="/acdh:hasAuthor">
    <uri val="CURNODE"/>
    <!-- note this path is relative to the author resource and not /acdh:hasAuthor/acdh:hasTitle -->
    <title val="/acdh:hasTitle"/>
    <memberOf val="/acdh:isMemberOf/acdh:hasTitle"/>
<a>
```

Please note it is possible to refer back to the previous node, e.g. if we want to
display only authors having a homepage:

```xml
<a foreach="/acdh:hasAuthor/acdh:hasHomepage">
    <uri val="/^acdh:hasHomepage"/>
    <title val="/^acdh:hasHomepage/acdh:hasAuthor"/>
    <homepage val="CURNODE"/>
<a>
```

### if

Skips evaluation of child tags if a given condition is not fulfilled.

It is a logical expression constructed from terms joined with `AND`, `OR`, `NOT` and parenthesis.

A term consists of: 

* an aggregation (`any`, `none`, `every`)
* an RDF property using the `prefix:suffix` syntax
* optional operator and value
  * operator is one of `==`, `!=`, `starts`, `ends`, `contains`, `>`, `<`, `>=`, `<=`, `regex`
  * value is single- or double-quited literal value or an RDF property in the `prefix:suffix` format

E.g.

* `if="any(rdf:type == acdh:Collection)"` if resource of type `acdh:Collection`
* `if="none(arche:id starts acdhi:cmdi)"` if no identifier in the `acdhi:cmdi` namespace
* `if="any(rdf:hasRawBinarySize) OR none(acdh:hasLicenseSummary)"` if there is
   any `acdh:hasRowBinarySize` RDF property value or no `acdh:hasLicenseSummary` property


### XML entities

Using XML entities it is possible to include subtemplates, e.g.

* main template:
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <!DOCTYPE doc [
    <!ENTITY Agent SYSTEM "agent.xml">
  ]>
  <root>
    <authors foreach="acdh:hasAuthor" remove="true">&Agent;</authors>
    <contributors foreach="acdh:hasAuthor" remove="true">&Agent;</contributors>
  </root>
  ```
* `agent.xml`
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <agent>
    <title val="acdh:hasTitle"/>
    <memberOf val="acdh:isMemberOf/acdh:hasTitle"/>
  </agent>
  ```