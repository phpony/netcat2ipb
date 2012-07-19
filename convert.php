<?php

$cli = php_sapi_name() === 'cli';

define('PATH', dirname(__FILE__)."/");

if ( ! $cli ) {
      print "<html><head><title>Warning</title></head>\n";
      print "<body style='text-align:center'>\n";
      print "This script is meant to be run via command line<br />\n";
      print "More information:<br />\n";
      print "<a href=\"http://www.google.com/search?hl=en&q=php+cli+windows\" target=\"_blank\">http://www.google.com/search?hl=en&q=php+cli+windows</a><br />\n";
      print "This script will not run through a webserver.<br />\n";
      print "</body></html>\n";
      exit();
}

$greeting = <<<CLI
--------------------------------------------------------------
PHP CLI script for NetCat to IP.Board data conversion
(c) Ritsuka, 2012
--------------------------------------------------------------
CLI;

$io = new io();
$io->say($greeting);
if ( ! $io->ask("Proceed (Y/n)?", true) ) {
	exit;
}

$io->separate();
$io->say("Configuration for IP.Board:");
$ipb = new ipb($io);
$io->say("");
$io->say("Analysing IP.Board:");
$ipb->analyse();

$io->separate();
$io->say("Configuration for NetCat:");
$netcat = new netcat($io);
$io->say("");
$io->say("Analysing NetCat:");
$netcat->analyse();

$io->separate();
if ( ! $io->ask("All necessary data is collected. Proceed with conversion (Y/n)?", true) ) {
	exit;
}
$io->say("Converting");

$io->say("");
$io->say("Converting {$netcat->info['members']} NetCat members:");
$io->scale();
$io->progress(0,$netcat->info['members']);
$i = 0;
while($member = $netcat->get_member()) {
	$ipb->add_member($member);	
	$io->progress($i,$netcat->info['members']);
	$i++;
}

$io->say("");
$io->say("Converting {$netcat->info['forums']} NetCat forums:");
$io->scale();
$io->progress(0, $netcat->info['forums']);
$i = 0;
while ( $forum = $netcat->get_forum() ) {
	$ipb->add_forum($forum);	
	$io->progress($i,$netcat->info['forums']);
	$i++;
}


$io->say("");
$io->say("Converting {$netcat->info['topics']} NetCat topics");
$io->scale();
$io->progress(0, $netcat->info['topics']);
$i = 0;
while ( $topic = $netcat->get_topic() ) {
	$ipb->add_topic($topic);	
	$io->progress($i,$netcat->info['topics']);
	$i++;
}

$io->say("");
$io->say("Converting {$netcat->info['posts']} NetCat posts");
$io->scale();
$io->progress(0, $netcat->info['posts']);
$i = 0;
while ( $post = $netcat->get_post() ) {
	$ipb->add_post($post);	
	$io->progress($i,$netcat->info['posts']);
	$i++;
}


$io->say("");
$io->say("Converting {$netcat->info['pms']} NetCat PM's");
$io->scale();
$io->progress(0, $netcat->info['pms']);
$i = 0;
while ( $pm = $netcat->get_pms() ) {
	$ipb->add_pm($pm);	
	$io->progress($i,$netcat->info['pms']);
	$i++;
}

$ending = <<<CLI
--------------------------------------------------------------
The conversation is done. Now you need to log into your:
 ACP > Tools & Settings > Recount & Rebuild
and rebuild forums, topics, members and PM's stats.

Good luck!
--------------------------------------------------------------
CLI;
$io->say($ending);

class db {
    private $conn, $res, $io, $db;
    function __construct($info, $io) {
		if ( ! $this->conn = mysql_connect($info['sql_host'], $info['sql_user'], $info['sql_pass']) ) $io->drop('Cant\'t connect to database server on '.$info['sql_host']);
		if ( ! mysql_select_db($info['sql_database'], $this->conn) ) $io->drop('Database '.$info['sql_database'].' not found');   
		$this->io = $io;
    }
    function query($sql) {
		$this->res = mysql_query($sql, $this->conn);
		if(!$this->res) $this->io->drop("Mysql error: ".mysql_error()."\nQuery: ".$sql);
		return $this->res;
    }
    function fetch($res = '') {
		if(!empty($res)) $this->res = $res;
		return mysql_fetch_array($this->res, MYSQL_ASSOC);
    }
    function safe($string) {
        return mysql_real_escape_string($string, $this->conn);
    }
    function test($table) {
		$this->res = mysql_query("SELECT * FROM {$table}", $this->conn);
		if(!$this->res) return false;		
        return true;
    }    
    function query_and_fetch($sql) {
        return $this->fetch($this->query($sql));
    }
    function insert_id() {
        return mysql_insert_id($this->conn);
    }
    function count($table) {
		$row = $this->query_and_fetch("SELECT COUNT(*) AS CNT FROM {$table}", $this->conn);
        return $row['CNT'];
    }
}

class io {
	private $progress = 0;
	public function say($word = '' , $nl = true) {
		$word = ($nl) ? $word."\n" : $word;
		fwrite(STDOUT, $word);
	}
	public function drop($word = '') {
		$this->say($word);
		exit;
	}
	public function ask($word, $bool = false) {
		$this->say($word.": ", false);
		$input = trim(fgets(STDIN));
		if ( $bool ) {
			return (mb_strtolower($input) == 'y' || empty($input));
		} else {
			return $input;
		}
	}
	public function separate() {
		$this->say("");
		$this->say("--------------------------------------------------------------");
		$this->say("");
	}
	public function scale() {
		$this->say("0%                          50%                           100%");
		$this->say("--------------------------------------------------------------");
		$this->progress = 0;
	}
	public function progress($step, $length) {
		if ( $step+1 == $length && $this->progress != 0 ) {
			$this->say();
			$this->progress = 0;
			return;
		}
		$progress = round(62*$step/$length);
		while ( $this->progress < $progress ) {
			$this->say("=", false);
			$this->progress++;
		}			
	}
}

class ipb {
	private $db, $io, $prefix, $existing;	
	public function __construct($io) {
		$this->io = $io;
		if ( file_exists(PATH."conf_global.php") && $this->io->ask('Use configuration from conf_global.php file (Y/n)?', true) ) {
			include PATH."conf_global.php";
			$this->prefix = $INFO['sql_tbl_prefix'];
			$this->db = new db($INFO, $io);
			$this->db->query("SET NAMES utf8");
		} else {
			$this->io->say("Please provide database connection data for IP.Board:");
			$INFO = array();
			$INFO['sql_host']		= $this->io->ask('Host');
			$INFO['sql_database']	= $this->io->ask('Database name');
			$INFO['sql_user']		= $this->io->ask('User');
			$INFO['sql_pass']		= $this->io->ask('Pass');
			$INFO['sql_tbl_prefix']	= $this->io->ask('Table prefix');
			$this->db = new db($INFO, $io);	
			$this->db->query("SET NAMES utf8");
		}
		$this->io->say('Connection established.');
		$this->io->say('');
		$this->io->say('Testing tables:');
		foreach ( array('forums','members','profile_portal','topics','posts','message_posts', 'message_topics', 'message_topic_user_map') as $table ) {
			if ( ! $this->db->test("{$this->prefix}{$table}") ) $this->io->drop("- {$table}: NOT FOUND.\nAborting!"); else $this->io->say("- {$table}: OK");
		}
	}
	public function analyse() {
		$this->existing = array(
			'members'=> array(),
			'pms'	 => array(),
			'forums' => array(),
			'topics' => array(),
			'posts'  => array(),
		);
		if ( $this->db->test("{$this->prefix}netcat_map") && $this->db->test("{$this->prefix}netcat_redirects") ) {
			if ( $this->io->ask('There were previous convertation results. Would you like to use them (Y/n)?', true) ) {
				$result = $this->db->query("SELECT * FROM {$this->prefix}netcat_map");
				$i = 0;
				while( $row = $this->db->fetch($result) ) {
					$this->existing[$row['type']][$row['old_id']] = $row['new_id'];
					$i++;
				}
				$this->io->say("Loaded {$i} records!");
			} elseif ( $this->io->ask('Would you like to drop them (Y/n)?', true) ) {
				$this->db->query("TRUNCATE TABLE {$this->prefix}netcat_map");
				$this->db->query("TRUNCATE TABLE {$this->prefix}netcat_redirects");
				$this->io->say("Truncated tables!");
			}
		} else {
			$this->db->query("DROP TABLE IF EXISTS `{$this->prefix}netcat_map`");
			$this->db->query("CREATE TABLE `{$this->prefix}netcat_map` (`id` int(11) NOT NULL AUTO_INCREMENT, `type` varchar(255) NOT NULL, `old_id` int(11) NOT NULL, `new_id` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `type` (`type`) ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
			$this->db->query("DROP TABLE IF EXISTS `{$this->prefix}netcat_redirects`");
			$this->db->query("CREATE TABLE `netcat_redirects` ( `id` int(11) NOT NULL AUTO_INCREMENT, `app` varchar(255) NOT NULL, `furl` varchar(255) NOT NULL, `new_id` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `app` (`app`), KEY `furl` (`furl`)) ENGINE=MyISAM DEFAULT CHARSET=utf8");
			$this->io->say("Created temporary tables!");
		}
	}
	public function parse_post($code) {
		$code = str_replace("\r", "", $code);
		$code = str_replace("\n", "<br />", $code);
		$code = str_ireplace("[quote=", "[quote name=", $code);
		$code = str_ireplace("[color=", "[color=#", $code);		
		$code = preg_replace("/\[img='([^']*)'\]/si", "[img]$1[/img]", $code);
		return $code;
	}
	public function add_member($member) {
		if ( array_key_exists( $member['User_ID'], $this->existing['members'] ) ) return;
		if(empty($member['Password'])) $member['Password'] = md5(uniqid().$member['User_ID']);
		$is_member = $this->db->query_and_fetch("SELECT * FROM {$this->prefix}members WHERE name = '{$member['Login']}'");
		if(!empty($is_member)) {
			$member['Login'] = $member['Login'] . '_' . uniqid();
		}
		$member['ForumName'] = $member['Login']; 
		$_insert = array(
			'name' => $member['Login'],
			'member_group_id' => 3,
			'email' => $member['Email'],
			'joined' => strtotime($member['Created']),
			'name' => $member['Login'],
			'members_display_name' => $member['ForumName'],			
			'last_visit' => strtotime($member['LastUpdated']),
			'last_activity' => strtotime($member['LastUpdated']),
			'members_display_name' => $member['ForumName'],
			'members_l_display_name' => mb_strtolower($member['ForumName']),
			'members_l_username' => mb_strtolower($member['Login']),
			'members_pass_salt' => 'nWg+e',
			'members_pass_hash' => md5($member['Password'].md5('nWg+e')),
		);
		if(strtotime($member['Birthday']) > 0) {
			$_insert['bday_day'] = date('d', strtotime($member['Birthday']));
			$_insert['bday_month'] = date('m', strtotime($member['Birthday']));
			$_insert['bday_year'] = date('Y', strtotime($member['Birthday']));
		}
		foreach($_insert as $k=>$v) {
			$_insert[$k] = $this->db->safe($v);
		}
		$this->db->query("INSERT INTO {$this->prefix}members (".implode(",", array_keys($_insert)).") VALUES ('".implode("','", $_insert)."')");
		$id = $this->db->insert_id();
		$this->db->query("INSERT INTO {$this->prefix}profile_portal (pp_member_id, signature) VALUES ({$id}, '".$this->db->safe($member['ForumSignature'])."')");
		$this->db->query("INSERT INTO {$this->prefix}netcat_map (type, old_id, new_id) VALUES ('members', {$member['User_ID']}, {$id})");
		$this->existing['members'][$member['User_ID']] = $id;
	}
	public function add_forum($forum) {
		if ( array_key_exists( $forum['Subdivision_ID'], $this->existing['forums'] ) ) return;
		$parent = !empty($this->existing['forums'][$forum['Parent_Sub_ID']]) ? $this->existing['forums'][$forum['Parent_Sub_ID']] : 0;
		$_insert = array(
			'name' => $forum['Subdivision_Name'],
			'description' => $forum['Description'],
			'sort_key' => 'last_post',
			'sort_order' => 'Z-A',
			'prune' => '100',
			'topicfilter' => 'all',
			'parent_id' => $parent,
			'use_ibc' => 1,
			'use_html' => 0,
		);
		foreach($_insert as $k=>$v) {
			$_insert[$k] = $this->db->safe($v);
		}
		$this->db->query("INSERT INTO {$this->prefix}forums (".implode(",", array_keys($_insert)).") VALUES ('".implode("','", $_insert)."')");
		$id = $this->db->insert_id();
		$this->db->query("INSERT INTO {$this->prefix}permission_index (`app`, `perm_type`, `perm_type_id`, `perm_view`, `perm_2`, `perm_3`, `perm_4`, `perm_5`, `perm_6`, `perm_7`, `owner_only`, `friend_only`, `authorized_users`) VALUES ('forums', 'forum', {$id}, '*', '*', '*', '*', ',4,3,', ',4,3,', '', 0, 0, NULL);");
		$this->db->query("INSERT INTO {$this->prefix}netcat_map (type, old_id, new_id) VALUES ('forums', {$forum['Subdivision_ID']}, {$id})");
		$this->db->query("INSERT INTO {$this->prefix}netcat_redirects (app, furl, new_id) VALUES ('forums', '{$forum['Hidden_URL']}', {$id})");
		$this->existing['forums'][$forum['Subdivision_ID']] = $id;
	}
	public function add_topic($topic) {
		if ( array_key_exists( $topic['Message_ID'], $this->existing['topics'] ) ) return;
		$member = $this->db->query_and_fetch("SELECT * FROM {$this->prefix}members WHERE member_id = {$this->existing['members'][$topic['User_ID']]}");
		$_insert = array(
			'title' => $topic['Subject'],
			'state' => $topic['Closed'] ? 'closed' : 'open',
			'starter_id' => $this->existing['members'][$topic['User_ID']],
			'starter_name' => $member['members_display_name'],
			'approved' => 1,
			'posts' => 0,
			'pinned' => ($optic['Priority'] > 0) ? 1 : 0,
			'start_date' => strtotime($topic['Created']),
			'forum_id' => $this->existing['forums'][$topic['Subdivision_ID']],
			'last_real_post' => strtotime($topic['LastUpdated']),
			'poll_state' => 0,
			'last_vote' => 0,
			'views' => intval($topic['Views']),
			'author_mode' => 1,
		);
		foreach($_insert as $k=>$v) {
			$_insert[$k] = $this->db->safe($v);
		}
		$this->db->query("INSERT INTO {$this->prefix}topics (".implode(",", array_keys($_insert)).") VALUES ('".implode("','", $_insert)."')");
		$id = $this->db->insert_id();
		$_insert = array(
			'author_id' => $this->existing['members'][$topic['User_ID']],
			'author_name' => $member['members_display_name'],
			'ip_address' => $topic['IP'],
			'post_date' => strtotime($topic['Created']),
			'post' => $this->parse_post($topic['Message']),
			'topic_id' => $id,
			'new_topic' => 1,
		);
		foreach($_insert as $k=>$v) {
			$_insert[$k] = $this->db->safe($v);
		}
		$this->db->query("INSERT INTO {$this->prefix}posts (".implode(",", array_keys($_insert)).") VALUES ('".implode("','", $_insert)."')");
		$id2 = $this->db->insert_id();
		$this->db->query("UPDATE {$this->prefix}topics SET topic_firstpost = {$id2} WHERE tid = {$id}");
		$this->db->query("INSERT INTO {$this->prefix}netcat_map (type, old_id, new_id) VALUES ('topics', {$topic['Message_ID']}, {$id})");
		$this->existing['topics'][$topic['Message_ID']] = $id;
	}
	public function add_post($post) {
		if ( array_key_exists( $post['Message_ID'], $this->existing['posts'] ) ) return;
		$mid = !empty($this->existing['members'][$post['User_ID']]) ? intval($this->existing['members'][$post['User_ID']]) : 0;
		$member = $this->db->query_and_fetch("SELECT * FROM {$this->prefix}members WHERE member_id = {$mid}");
		$tid = !empty($this->existing['topics'][$post['Topic_ID']]) ? intval($this->existing['topics'][$post['Topic_ID']]) : 0;
		$_insert = array(
			'author_id' => $mid,
			'author_name' => $member['members_display_name'],				
			'ip_address' => $post['IP'],
			'post_date' => strtotime($post['Created']),
			'post' => $this->parse_post($post['Message']),
			'topic_id' => $tid,
			'new_topic' => 0,
			'use_sig' => 1,
			'use_emo' => 1,
		);
		foreach($_insert as $k=>$v) {
			$_insert[$k] = $this->db->safe($v);
		}
		$this->db->query("INSERT INTO {$this->prefix}posts (".implode(",", array_keys($_insert)).") VALUES ('".implode("','", $_insert)."')");
		$id = $this->db->insert_id();
		$this->db->query("UPDATE {$this->prefix}topics SET last_post = {$id} WHERE tid = {$_insert['topic_id']}");
		$this->db->query("INSERT INTO {$this->prefix}netcat_map (type, old_id, new_id) VALUES ('posts', {$post['Message_ID']}, {$id})");
		$this->existing['posts'][$post['Message_ID']] = $id;
	}
	public function add_pm($pm) {
		if ( array_key_exists( $pm['Message_ID'], $this->existing['pms'] ) ) return;
		$mid = !empty($this->existing['members'][$pm['User_ID']]) ? intval($this->existing['members'][$pm['User_ID']]) : 0;
		$member = $this->db->query_and_fetch("SELECT * FROM {$this->prefix}members WHERE member_id = {$mid}");
		$toid = !empty($this->existing['members'][$pm['ToUser']]) ? intval($this->existing['members'][$pm['ToUser']]) : 0;
		$_insert = array(
			'mt_date' => strtotime($pm['Created']),
			'mt_start_time' => strtotime($pm['Created']),
			'mt_last_post_time' => strtotime($pm['LastUpdated']),
			'mt_starter_id' => $mid,
			'mt_hasattach' => 0,
			'mt_title' => $pm['Subject'],
			'mt_invited_members' => 'a:0:{}',
			'mt_to_count' => 1,
			'mt_to_member_id' => $toid,
			'mt_replies' => 0,
		);
		foreach($_insert as $k=>$v) {
			$_insert[$k] = $this->db->safe($v);
		}
		$this->db->query("INSERT INTO {$this->prefix}message_topics (".implode(",", array_keys($_insert)).") VALUES ('".implode("','", $_insert)."')");
		$topic_id = $this->db->insert_id();
		$_insert = array(
			'msg_topic_id' => $topic_id,
			'msg_date' => strtotime($pm['Created']),
			'msg_post' => $this->parse_post($pm['Message']),
			'msg_post_key' => md5(microtime()),
			'msg_author_id' => $mid,
			'msg_ip_address' => $pm['IP'],
			'msg_is_first_post' => 1,
		);
		foreach($_insert as $k=>$v) {
			$_insert[$k] = $this->db->safe($v);
		}
		$this->db->query("INSERT INTO {$this->prefix}message_posts (".implode(",", array_keys($_insert)).") VALUES ('".implode("','", $_insert)."')");
		$message_id = $this->db->insert_id();
		$this->db->query("UPDATE {$this->prefix}message_topics SET mt_first_msg_id = {$message_id}, mt_last_msg_id = {$message_id} WHERE mt_id = {$topic_id}");
		$lt = strtotime($pm['LastUpdated']);
		$this->db->query("INSERT INTO `message_topic_user_map` (`map_user_id`, `map_topic_id`, `map_folder_id`, `map_read_time`, `map_user_active`, `map_user_banned`, `map_has_unread`, `map_is_system`, `map_is_starter`, `map_left_time`, `map_ignore_notification`, `map_last_topic_reply`) VALUES ({$mid}, {$topic_id}, 'myconvo', 0, 1, 0, 1, 0, 1, 0, 0, {$lt}), ({$toid}, {$topic_id}, 'myconvo', 1342419007, 1, 0, 0, 0, 0, 0, 0, {$lt})");
		$this->db->query("INSERT INTO {$this->prefix}netcat_map (type, old_id, new_id) VALUES ('pms', {$pm['Message_ID']}, {$topic_id})");
		$this->existing['pms'][$pm['Message_ID']] = $topic_id;
	}
}

class netcat {
	private $db, $io, $redirects, 
			$tables = array('users'=>'User','forums'=>'Subdivision','personal messages'=>'Message64','topics'=>'Message68','posts'=>'Message70','aliases'=>'Message80');
	public	$info = array();	
	public function __construct($io) {
		$this->io = $io;	
		$this->io->say("Please provide database connection data for NetCat:");
		$INFO = array();
		$INFO['sql_host']		= $this->io->ask('Host');
		$INFO['sql_database']	= $this->io->ask('Database name');
		$INFO['sql_user']		= $this->io->ask('User');
		$INFO['sql_pass']		= $this->io->ask('Pass');
		$this->db = new db($INFO, $io);	
		$this->db->query("SET NAMES cp1251");
		$this->io->say('Connection established.');
		$this->io->say('');
		$this->io->say('Setting tables:');
		foreach($this->tables as $title=>$table) {
			$tmp = $this->io->ask("Table for {$title} is ({$table})");
			$this->tables[$title] = !empty($tmp) ? $tmp : $table;
			if ( ! $this->db->test("{$this->tables[$title]}") ) $this->io->drop("- {$this->tables[$title]}: NOT FOUND.\nAborting!"); else $this->io->say("- {$this->tables[$title]}: OK");			
		}		
	}
	public function analyse() {
		$this->redirects = array();
		$result = $this->db->query("SELECT * FROM {$this->tables['aliases']}");
		$i = 0;
		while($row = $this->db->fetch()) {
			$this->redirects[$row['old_sub']] = $row['new_sub'];
			$i++;
		}
		$this->io->say("Loaded {$i} aliases!");	
		$this->info['members'] = $this->db->count($this->tables['users']);
		$this->info['forums'] = $this->db->count($this->tables['forums']);
		$this->info['topics'] = $this->db->count($this->tables['topics']);
		$this->info['posts'] = $this->db->count($this->tables['posts']);
		$this->info['pms'] = $this->db->count($this->tables['personal messages']);		
	}
	private $res_members;
	public function get_member() {
		if(empty($this->res_members)) {
			$this->res_members = $this->db->query("SELECT * FROM {$this->tables['users']}");		
		}
		$row = $this->db->fetch($this->res_members);
		if(!empty($row)) foreach($row as $k=>$v) {
			$row[$k] = mb_convert_encoding($v, "UTF-8", "CP1251");
		}
		return $row;
	}
	private $res_forums;
	public function get_forum() {
		if(empty($this->res_forums)) {
			$this->res_forums = $this->db->query("SELECT * FROM {$this->tables['forums']} ORDER BY Parent_Sub_ID ASC");		
		}
		$row = $this->db->fetch($this->res_forums);
		if(!empty($row)) foreach($row as $k=>$v) {
			$row[$k] = mb_convert_encoding($v, "UTF-8", "CP1251");
		}
		return $row;
	}
	private $res_topics;
	public function get_topic() {
		if(empty($this->res_topics)) {
			$this->res_topics = $this->db->query("SELECT * FROM {$this->tables['topics']} ORDER BY Message_ID ASC");		
		}
		$row = $this->db->fetch($this->res_topics);
		if(!empty($row)) foreach($row as $k=>$v) {
			$row[$k] = mb_convert_encoding($v, "UTF-8", "CP1251");
		}
		return $row;
	}
	private $res_posts;
	public function get_post() {
		if(empty($this->res_posts)) {
			$this->res_posts = $this->db->query("SELECT * FROM {$this->tables['posts']} ORDER BY Message_ID ASC");		
		}
		$row = $this->db->fetch($this->res_posts);
		if(!empty($row)) foreach($row as $k=>$v) {
			$row[$k] = mb_convert_encoding($v, "UTF-8", "CP1251");
		}
		return $row;
	}
	private $res_pms;
	public function get_pms() {
		if(empty($this->res_pms)) {
			$this->res_pms = $this->db->query("SELECT * FROM {$this->tables['personal messages']} ORDER BY Message_ID ASC");		
		}
		$row = $this->db->fetch($this->res_pms);
		if(!empty($row)) foreach($row as $k=>$v) {
			$row[$k] = mb_convert_encoding($v, "UTF-8", "CP1251");
		}
		return $row;
	}
}
