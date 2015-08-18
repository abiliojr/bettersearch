# Better Search for DokuWiki

Improved search engine for DokuWiki, based on Sphinx Search.

You can find the latest version of this plugin at:

	https://github.com/abiliojr/bettersearch

This is yet another attempt at trying to improve DokuWiki search results.

Features:
* Ranks the results based on several factors:
    - Number of views.
    - Number of edits.
    - Number of references from other articles.
    - Number of clicks from previous searches.
    - Freshness of the article.
* Uses Sphinx, an efficient engine that is used by big ones, like Craigslist,
  to find needles in huge haystacks.
* Better text matches, specially useful with non-English languages.


# Installation

This plugin requires a Sphinx Search server up and running.

Sphinx supports English, German and Russian out of the box. In case your wiki is 
using a different language, be sure Sphinx was compiled with libstemmer.
Check http://sphinxsearch.com/docs/latest/conf-morphology.html for more
information.

Please check settings.php and use the provided sphinx.conf as a reference
to edit your sphinx configuration.

If you're installing this on an existing wiki, copying a lot of files directly
into the wiki data directory, or any reason that needs a reindexing, please run:

    php reindex.php

# Usage

The basic usage is self explanatory. The plugin includes the following sintax:

## Restrict search within namespace
In the text field, include the namespace preceeded by `ns:` For example, 
the next line will restrict the search within the root namespace:

    this is the search phrase ns:root

This is a departure from the original DokuWiki's @ sign convention, but the change 
was needed to include full compatibility with Sphinx's advanced syntax capabilities 
(see next section).

## Advanced syntax
The plugin supports the Sphinx's extended query syntax. For more information 
please visit: http://sphinxsearch.com/docs/latest/extended-syntax.html

# Notes
The ranking formula is still considered experimental.
The weights for each factor were determined using AHP (Analytic hierarchy 
process). See extras/ahp.php for more information.

Ideas, suggestions, bug reports and help (specially on the ranker formula and settings) 
are welcomed! Please go to:

	https://github.com/abiliojr/bettersearch

And contact me through the issues section.

If you install this plugin manually, make sure it is installed in
lib/plugins/bettersearch/ - if the directory is called different it
will not work!

Please refer to http://www.dokuwiki.org/plugins for additional info
on how to install plugins in DokuWiki.

----
Copyright (C) Abilio Marques <https://github.com/abiliojr>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

See the LICENSE file for details
