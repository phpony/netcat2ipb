<?php

// Redirects for old NetCat urls

class netcat {
	public $ok = false, $board;
	private $conn, $prefix, $res;
	public function __construct($INFO) {
		if ( ! $this->conn = mysql_connect($INFO['sql_host'], $INFO['sql_user'], $INFO['sql_pass']) ) return;
		if ( ! mysql_select_db($INFO['sql_database'],  $this->conn) ) return;
		if ( ! $this->query("SET NAMES utf8") ) return;
		$this->prefix = $INFO['sql_tbl_prefix'];
		$this->board = $INFO['board_url'];
		$this->ok = true;
	}
    function query($sql) {
		$this->res = mysql_query($sql, $this->conn);
		if(!$this->res) return;
		return $this->res;
    }
    function fetch($res = '') {
		if(!empty($res)) $this->res = $res;
		return mysql_fetch_array($this->res, MYSQL_ASSOC);
    }
    function safe($string) {
        return mysql_real_escape_string($string, $this->conn);
    }
	function topic($id, $st) {
		$id = intval($id);
		$this->query("SELECT * FROM {$this->prefix}netcat_map WHERE type = 'topics' AND old_id = {$id}", $this->conn);
		if($row = $this->fetch()) {
			$this->redirect("index.php?showtopic=".$row['new_id'].'&st='.$st);
		}	
	}
	function member($id) {
		$id = intval($id);
		$this->query("SELECT * FROM {$this->prefix}netcat_map WHERE type = 'members' AND old_id = {$id}", $this->conn);
		if($row = $this->fetch()) {
			$this->redirect("index.php?showuser=".$row['new_id']);
		}	
	}
	function forum() {
		$u = explode("/", $_SERVER['REQUEST_URI']);
		if ( empty($u[2]) || empty($u[1]) ) return;
		$furl = $this->safe("/".$u[1]."/".$u[2]."/");
		$this->query("SELECT * FROM {$this->prefix}netcat_redirects WHERE app = 'forums' AND furl = '{$furl}'", $this->conn);
		if($row = $this->fetch()) {
			$this->redirect("index.php?showforum=".$row['new_id']);
		}	
	}
	function redirect($uri) {
		header("Location: {$this->board}/{$uri}");
		exit;
	}
	function adios() {
		mysql_close($this->conn);
	}
}

if(substr($_SERVER['REQUEST_URI'],0,3)=='/f/') {
	require_once( dirname( __FILE__ ) . '/conf_global.php' );	
	$netcat = new netcat($INFO);
	unset($INFO);
	if ( $netcat->ok ) {
			$st = !empty($_GET['Page_NUM']) ? intval($_GET['curPos'])*20 : 0;
			$st = !empty($_GET['curPos']) ? intval($_GET['curPos']) : $st;
			if ( !empty($_GET['Topic_ID']) ) {
				$netcat->topic($_GET['Topic_ID'], $st);
			}
			if ( preg_match("/topic_([0-9]{1,10})\./iu", $_SERVER['REQUEST_URI'], $match) ) {
				$netcat->topic($match[1], $st);
			}
			if ( preg_match("/profile_([0-9]{1,10})\./iu", $_SERVER['REQUEST_URI'], $match) ) {
				$netcat->member($match[1]);
			}
			$netcat->forum();
	}
	$netcat->adios();
	unset($netcat);
}