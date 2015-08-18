<?php
/**
 * Better Search for DokuWiki (Action component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Abilio Marques <https://github.com/abiliojr>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require (dirname(__FILE__) . '/../model/sphinxsearch.php');
require (dirname(__FILE__) . '/../view/searchresults.php');

class action_plugin_bettersearch_handleWikiIndexer extends DokuWiki_Action_Plugin {
    private $sphinx;


    /**
     * Install hooks for interesting events
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $this->sphinx = new SphinxSearch();
        $this->searchResults = new searchResults();
        if ($this->sphinx->isConnected === false) {
            $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_no_connection');
        } else {
            $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, 'handle_headers_send');
            $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'handle_wikipage_write', 0);
            $controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER',  $this, 'handle_wikipage_write', 1);
            $controller->register_hook('INDEXER_PAGE_ADD', 'AFTER', $this, 'handle_indexer_page_add');
            $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_tpl_content_display');

        }
    }


    public function handle_headers_send(Doku_Event &$event, $param) {
        global $INFO;
        global $ACT;
        global $QUERY;
        global $INPUT;

        // must remove the search tracking parameter
        if ($ACT === 'show' && $INPUT->param('pragma') === 'tracksearch') {
            $ns = $INFO['namespace'];
            if (!$ns) $ns = "root";
            
            $this->sphinx->incSeachClicks($ns, $INFO['id']);
            array_push($event->data, 'Location: ' . wl($QUERY));
        }

    }

    private function modify_backlinks($page, $operation) {
        global $conf;
        $namespace = getNS($page);
        if (!$namespace) $namespace = "root";
        $title = str_replace($conf['sepchar'], ' ', noNS($page));
        file_put_contents('/tmp/dump.txt', "$operation: $namespace:$title\n", FILE_APPEND);
        $this->sphinx->modifyReferences($namespace, $title, $operation);
    }

    private function count_backlinks() {
        global $QUERY;
        static $ran, $prev;

        $ran++;
        if ($ran > 2) return;

        $refs = p_get_metadata($QUERY,'relation')['references'];
        $refs = array_keys($refs, 1);
        if (!$refs) $refs = [];

        if ($ran === 1) $prev = $refs;
       
        if ($ran === 2) {
            $removed = array_diff($prev, $refs);
            $added = array_diff($refs, $prev);
            foreach ($removed as $item) $this->modify_backlinks($item, '-');
            foreach ($added  as $item)  $this->modify_backlinks($item, '+');
        }
   }

    public function handle_wikipage_write(Doku_Event &$event, $param) {
        if ($event->data[3] !== false) return; // something older than the current version doesn't count

        $this->count_backlinks();
        if ($param === 0) return; // never go further from here on BEFORE

        /* this section takes care of deletions and editions */

        $ns = $event->data[1];
        if (!$ns) $ns = "root";

        $title = $event->data[2];

        if (!$event->data[0][1]) {
            // the wiki was deleted
            $this->sphinx->delete($ns, $title);
            return;
        }

        $this->sphinx->incEdits($ns, $title);
    }


    /**
     * Adds the page data to the index
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_indexer_page_add(Doku_Event &$event, $param) {
        global $conf;
        $page = $event->data['page'];

        $namespace = getNS($page);
        if (!$namespace) $namespace = "root";

        $title = str_replace($conf['sepchar'], ' ', noNS($page));
        $text = rawWiki($page);

        $meta = p_get_metadata($page);

        $data['title'] = $title;
        $data['namespace'] = $namespace;
        $data['content'] = $text;
        $data['references'] = count(ft_backlinks($page, true));
        $data['filename'] = wikiFN($page);
        $data['lastedit'] = $meta['date']['modified'];
        $this->sphinx->upsert($data);
    }


    /**
     * Generates the search results
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_tpl_content_display(Doku_Event &$event, $param) {
        global $INFO;
        global $ACT;
        global $QUERY;
        global $INPUT;
        global $settings;
        
        if ($ACT === 'show' && $INPUT->param('pragma') !== 'tracksearch') {
            $ns = $INFO['namespace'];
            if (!$ns) $ns = "root";
            $this->sphinx->incViews($ns, $INFO['id']);
            return;
        }

        if ($ACT !== 'search') return;

        $data = $event->data;
        $m = $_GET["m"];
        $n = $_GET["n"];
        if (empty($m)) $m = 1;
        if (empty($n)) $n = $settings['results_per_page'];

        $event->stopPropagation();
        $event->preventDefault();

        $this->search($QUERY, $m, $n);
    }    

    /**
     * Prints a no connection warning on each page
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_no_connection(Doku_Event &$event, $param) {
        global $ACT;
        if (in_array($ACT, array('show', 'search', 'edit', 'index'))) {
            $this->searchResults->noSphinxConnection();
        }
    }


    private function search($query, $m, $n) {
        global $conf;

        $pattern = '/\s*ns:([:' . $conf['sepchar'] . '\w]+)/';
        // first, lets separate the namespace from the text
        preg_match($pattern, $query, $namespace);
        if (!empty($namespace)) {
            $namespace = $namespace[1];
            $text = preg_filter($pattern, '', $query);
        } else {
            $namespace = '';
            $text = $query;
        }

        $results = $this->sphinx->search($text, $namespace, ($m - 1) * $n, $n);
        $meta = $this->sphinx->getMeta();

        $this->searchResults->renderResults($query, $results, $meta, $m, $n);
    }

}
