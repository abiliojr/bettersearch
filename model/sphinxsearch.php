<?php
/**
* Better Search for DokuWiki (Search engine component)
  *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Abilio Marques <https://github.com/abiliojr>
 */

require (dirname(__FILE__) . '/../settings.php');

class SphinxSearch {
	private $db = null;
	public $isConnected;
	private $sphinxIndex;

	function handleError($e) {
		print "Error!: " . $e->getMessage() . "<br/>";
		die();
	}


	function __construct() {
		global $settings;
		try {
			$this->sphinxIndex = $settings['sphinx_index'];
			$socket = $settings['sphinx_socket'];
			$this->db = new PDO("mysql:$socket;charset=utf8", null, null, array(
		    		PDO::ATTR_TIMEOUT => 1,
        			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    			));
			$this->isConnected = true;
			$this->db->setAttribute(PDO::ATTR_TIMEOUT, $settings['sphinx_timeout']);
		} catch (PDOException $e) {
			$this->isConnected = false;
		}
	}


	function __destruct() {
		$this->db = null;
	}


	private function update($id, $data) {
		try {
			$counterstmt = $this->db->prepare("select views, edits, searchclicks from $this->sphinxIndex where title = ? and namespace = ?");
			$counterstmt->execute(array($data['title'], $data['namespace']));
			if ($counterstmt->rowCount() !== 0) {
				$counters = $counterstmt->fetch();
			} else {
				$counters['edits'] = 1;
				$counters['views'] = 0;
				$counters['searchclicks'] = 0;
			}

			$stmt = $this->db->prepare("replace into $this->sphinxIndex" . 
				"(id, title, namespace, content, filepath, timestamp, views, edits, searchclicks, references) values(?,?,?,?,?,?,?,?,?,?)");

			$stmt->execute(array($id, $data['title'], $data['namespace'], $data['content'], $data['filename'], $data['lastedit'], $counters['views'], $counters['edits'], $counters['searchclicks'], $data['references']));
		} catch (PDOException $e) {
			$this->handleError($e);
		}
	}


	private function insert($data) {
		try {
			$idstmt = $this->db->prepare("select max(id) as i from $this->sphinxIndex");
			$stmt = $this->db->prepare("insert into $this->sphinxIndex(" .
				"id, title, namespace, content, filepath, timestamp, views, edits, searchclicks, references) values(?,?,?,?,?,?,0,1,0,?)");

			for ($retries = 0; $retries < 20; $retries++) {
				// get the highest id, and try to save the new entry with id + 1
    			$idstmt->execute();
    			$id = $idstmt->fetch()['i'] + 1;
    			$stmt->execute(array($id, $data['title'], $data['namespace'], $data['content'], $data['filename'], $data['lastedit'], $data['references']));
    			if ($stmt->rowCount() != 0) break;

   				// someone else saved at the same time, try again in a few milliseconds
    			usleep(100000);
    		}
		} catch (PDOException $e) {
			$this->handleError($e);
		}
	}


	public function upsert($data) {
		try {
			$stmt = $this->db->prepare("select id from $this->sphinxIndex where title = ?");
			$stmt->execute(array($data['title']));
			$id = $stmt->fetch()['id'];
			if ($id) {
				$this->update($id, $data);
			} else {
				$this->insert($data);
			}
		} catch (PDOException $e) {
			$this->handleError($e);
		}
	}


	public function delete($namespace, $title) {
		try {
			$stmt = $this->db->prepare("delete from $this->sphinxIndex where title = ? and namespace = ?");
			$stmt->execute(array($title, $namespace));
		} catch (PDOException $e) {
			$this->handleError($e);
		}
	}

	public function search($text, $namespace, $M, $N) {
		global $settings;
		try {
			$factorsQuery = "SELECT min(timestamp) as lt, " .
							"		max(timestamp) as ht, " . 
							"		max(views) as hv, " .
							"		max(edits) as he, " .
							"		max(searchclicks) as hc, " .
							"		max(references) as hr, " .
							"		max(weight()) as hw " .
							"from $this->sphinxIndex " . 
							"where match(:text)";

			if ($namespace) $factorsQuery .= " and namespace = :namespace";
			$factorsQuery .= " OPTION field_weights = " . $settings['field_weights'];

			$stmt = $this->db->prepare($factorsQuery);
			$stmt->bindValue(':text', $text);
			if ($namespace) $stmt->bindValue(':namespace', $namespace);
			$stmt->execute();
			if ($stmt->rowCount() === 0) return FALSE;

			$factorVariables = $stmt->fetch();

			// we don't want division by 0
			if ($factorVariables['hv'] == 0) $factorVariables['hv'] = 1000;
			if ($factorVariables['he'] == 0) $factorVariables['he'] = 1000;
			if ($factorVariables['hc'] == 0) $factorVariables['hc'] = 1000;
			if ($factorVariables['hr'] == 0) $factorVariables['hr'] = 1000;
			if ($factorVariables['hw'] == 0) $factorVariables['hw'] = 1000;
			if ($factorVariables['ht'] == $factorVariables['lt']) $factorVariables['lt']--;

			// this is an experimental ranker, made by combining the sph04 ranker,
			// as described bysphinx documentation
			// see: http://sphinxsearch.com/docs/current.html#formulas-for-builtin-rankers
			// and a series of custom factors
			// The weights were calculated using AHP (see README)

			// you may want to improve it, or remove it altogether, replacing it with '$ranker = sph04'

			$ranker = 	sprintf(
							"expr('%s * (sum((4*lcs+2*(min_hit_pos==1)+exact_hit)*user_weight)*1000+bm25) / %d + " .
							"%s * (%d - timestamp) / (%d - %d) + " .
							"%s * views / %d + " .
							"%s * edits / %d + " .
							"%s * searchclicks / %d + " .
							"%s * references / %d')",

							$settings['r_weights']['string'],
							$factorVariables['hw'],

							$settings['r_weights']['freshness'],
							$factorVariables['ht'],
							$factorVariables['ht'],
							$factorVariables['lt'],


							$settings['r_weights']['views'],
							$factorVariables['hv'],

							$settings['r_weights']['edits'],
							$factorVariables['he'],

							$settings['r_weights']['searchs'],
							$factorVariables['hc'],

							$settings['r_weights']['refs'],
							$factorVariables['hr']
						);

			$query = "SELECT 	SNIPPET(title, :text, 'limit=" . $settings['snippet_title_max_len'] . "', 'query_mode = 1') as titlesnippet, "  .
					 "			SNIPPET(filepath, :text, 'load_files=1', " .
					 "					'around = " . $settings['snippet_content_words_around'] . "', " . 
					 "					'limit = "  . $settings['snippet_content_max_len'] . "', 'query_mode = 1') as contentsnippet, " .
					 "			title, " .
					 "			namespace " .
					 "from $this->sphinxIndex " .
					 "where match(:text)";

			if ($namespace) $query .= " and namespace = :namespace";
			if ($M !== null && $N !== null) $query .= " limit :M,:N";

			$query .= " OPTION ranker = " . $ranker;
			$query .= ", field_weights = " . $settings['field_weights'];

			$stmt = $this->db->prepare($query);

			$stmt->bindValue(':text', $text);
			if ($namespace) $stmt->bindValue(':namespace', $namespace);
			$stmt->bindValue(':M', (int) $M, PDO::PARAM_INT);
			$stmt->bindValue(':N', (int) $N, PDO::PARAM_INT);

			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$this->handleError($e);
		}
	}


	private function modifyCounter($counter, $namespace, $title, $operation) {
		try {
			$idstmt = $this->db->prepare("select id, max($counter) as c from $this->sphinxIndex where namespace = ? and title = ?");
			$stmt = $this->db->prepare("update $this->sphinxIndex set $counter = :futurecounter where id = :id and $counter = :counter");

			for ($retries = 0; $retries < 20; $retries++) {
				$idstmt->execute(array($namespace, $title));

				if ($idstmt->rowCount() === 0) break;
				$result = $idstmt->fetch();

				$newValue = $result['c'] + 1;
				if ($operation === '-') $newValue = $result['c'] - 1;
				$stmt->bindValue(':futurecounter', (int) $newValue, PDO::PARAM_INT);

				$stmt->bindValue(':counter', (int) $result['c'], PDO::PARAM_INT);
				$stmt->bindValue(':id', (int) $result['id'], PDO::PARAM_INT);

				$stmt->execute();
				if ($stmt->rowCount() != 0) break;

				// someone else saved at the same time, try again in a few milliseconds
				usleep(100000);
			}
		} catch (PDOException $e) {
			$this->handleError($e);
		}

	}


	public function incViews($namespace, $title) {
		$this->modifyCounter('views', $namespace, $title, '+');
	}


	public function incEdits($namespace, $title) {
		$this->modifyCounter('edits', $namespace, $title, '+');
	}


	public function incSeachClicks($namespace, $title) {
		$this->modifyCounter('searchclicks', $namespace, $title, '+');
	}


	public function modifyReferences($namespace, $title, $operation) {
		$this->modifyCounter('references', $namespace, $title, $operation);
	}


	public function updateReferences($namespace, $title, $references) {
		$stmt = $this->db->prepare("update $this->sphinxIndex set references = :references where title = :title and namespace = :namespace");
		$stmt->bindValue(':references', (int) $references, PDO::PARAM_INT);
		$stmt->bindValue(':title', $title);
		$stmt->bindValue(':namespace', $namespace);
		$stmt->execute();
	}


	public function getMeta() {
		//  only fetch the variables that begin with t, as no other ones are needed
		$result = $this->db->query("show meta like 't%'")->fetchAll(PDO::FETCH_ASSOC);
		$ret = [];

		foreach ($result as $item) {
			$ret[$item['Variable_name']] = $item['Value'];
		}

		return $ret;
	}
}
