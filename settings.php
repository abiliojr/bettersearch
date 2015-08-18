<?php
/**
 * Better Search for DokuWiki (Settings Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Abilio Marques <https://github.com/abiliojr>
 */

/**
 *
 * Settings file
 *
 */

global $settings;

// sphinx search timeout (in seconds)
$settings['sphinx_timeout'] = 5;


// sphinx search connection string
// use something like 'host=127.0.0.1;port=9306' for using TCP/IP in UNIX or if you use Windows
// remember to change the sphinx config parameter 'listen' accordingly
$settings['sphinx_socket'] = 'unix_socket=/var/run/sphinxsearch/searchd.socket';

// sphinx index name, useful for sharing a Sphinx server ammong multiple instances of DokuWiki
$settings['sphinx_index']  = 'bettersearch';

// the snippet is the text shown for each of the results, comprised of the title and the description 
$settings['snippet_title_max_len'] = 60;
$settings['snippet_content_max_len'] = 180;
$settings['snippet_content_words_around'] = 40;

$settings['results_per_page'] = 10;


// importance of a match in the title compared to a match in the description
$settings['field_weights'] = '(name=10, data=1)';

// importance of each factor for the ranking (as gotten from AHP, multiplied by 10000)
$settings['r_weights']['views']		= 2326.142;
$settings['r_weights']['edits']		= 462.135;
$settings['r_weights']['searchs']	= 1691.568;
$settings['r_weights']['refs']		= 728.525;
$settings['r_weights']['freshness'] = 226.838;
$settings['r_weights']['string']	= 4564.792;
