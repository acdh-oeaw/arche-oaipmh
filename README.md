An OAI-PMH service for the ACDH repository.

* Uses metadata from the Fedora repository triplestore
  (it means **you must have Fedora coupled with a triplestore**, e.g. using the [fcrepo-indexing-triplestore](https://github.com/fcrepo4-exts/fcrepo-camel-toolbox/tree/master/fcrepo-indexing-triplestore) Fedora plugin)
* Fetches OAI-PMH header data (identifier and modyfication date) from given RDF properties in the triplestore
  (e.g. dct:identifier and http://fedora.info/definitions/v4/repository#lastModified)
    * It is hardcoded at the moment but can be easily moved to the configuration file.
* Is able to fetch OAI-PMH metadata in two ways:
    * by creating a Dublin Core them from corresponding Dublin Core and Dublin Core Terms found in Fedora resource's RDF metadata
    * by taking content of other Fedora resource denoted by a chosen resource's RDF property (e.g. https://vocabs.acdh.ac.at/#hasCMDIcollection)
* Can be quite easily extended
* Depends only on the Fedora REST API and the triplestore, therefore is not affected by internal changes in Fedora.

# Installation

* clone the repo
* run `composer update`
    * of course make sure you have `composer` first
* rename `config.ini.inc` to `config.ini`
* update `config.ini` content
    * read the comments - information on describing metadata formats is provided there (as well as examples)
* deploy as a normal PHP website

# Unsupported features

* *resumptionTokens*  
  Implementing *resumptionTokens* in a right way is difficult and in my personal opinion it is better to avoid resumption queries at all (the main problem is Fedora does not provide transaction consistency and isolation - see [ACID](https://en.wikipedia.org/wiki/ACID) - and if I can not guarantee immutable repository state between following resumption queries I do not see a way to support such queries in a right way).  
  While at the moment the whole response is buffered in the server memory which can cause problems with large responses (bigger then memory PHP can allocate on your server) the first point on the TODO list is to rewrite the code to avoid buffering and output response gradualy. It will keep connection alive and allow handling as big responces as needed.
* *deleted records*  
  As in our software stack information about deleted resources is removed from the triplestore we did not bother about this feature.  
  Anyway if such data are available in the triplestore it will be easy to add this feature.
