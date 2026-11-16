<?php
/* GPT Bot add-on for Codychat 10.1 | Author: M Kashif | MIT License */
if(!defined('BOOM')){ die(); }
/* Answers come from the free GPT Bot service at chatnoregister.com. No API key needed. */
define('GPB_API', 'https://chatnoregister.com/gptapi/?a=');
define('GPB_VERSION', '1.0.0');

function gpbCfg($a){
	$s = function($k, $d) use ($a){ return (isset($a[$k]) && $a[$k] !== '' && $a[$k] !== null) ? (string)$a[$k] : $d; };
	return array('on' => (int)$s('custom1', '1'), 'key' => $s('custom2', ''), 'min' => (int)$s('custom3', '0'),
		'room' => (int)$s('custom4', '1'), 'dm' => (int)$s('custom5', '1'), 'rate' => max(1, (int)$s('custom6', '4')),
		'site' => $s('custom7', ''), 'extra' => $s('custom8', ''), 'bot' => (int)$s('bot_id', '0'), 'name' => $s('bot_name', 'GPT'));
}
/* words that call the bot: its name plus the extra trigger words */
function gpbWords($c){
	$w = array($c['name']);
	foreach(explode(',', $c['extra']) as $x){ $x = trim($x); if($x !== ''){ $w[] = $x; } }
	$w = array_values(array_unique(array_filter(array_map(function($x){ return mb_substr(trim($x), 0, 30); }, $w))));
	return $w;
}
function gpbPattern($c){
	$q = array();
	foreach(gpbWords($c) as $w){ $q[] = preg_quote($w, '/'); }
	return '(^|[^\p{L}\p{N}_])@?(' . implode('|', $q) . ')(?=$|[^\p{L}\p{N}_])';
}
function gpbCalled($text, $c){ return (bool)preg_match('/' . gpbPattern($c) . '/iu', $text); }
function gpbPlain($html){
	$t = preg_replace('~<br\s*/?>~i', "\n", (string)$html);
	$t = preg_replace('~<img[^>]*\bdata="(:[^"]+:)"[^>]*>~i', '$1', $t);
	$t = strip_tags($t);
	$t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	return trim(preg_replace('/[ \t]+/u', ' ', $t));
}
/* keeps the bot shown as online in the user list */
function gpbOnline($bot){
	global $mysqli;
	$bot = (int)$bot; $now = time();
	$mysqli->query("UPDATE boom_users SET last_action = '" . ($now + 300) . "' WHERE user_id = '$bot' AND last_action < '" . ($now + 60) . "'");
}
function gpbApi($path, $payload){
	$ch = curl_init(GPB_API . $path);
	curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_POST => 1, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8,
		CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'User-Agent: CodychatGPTBot/' . GPB_VERSION),
		CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)));
	$r = curl_exec($ch); curl_close($ch);
	$d = json_decode((string)$r, true);
	return is_array($d) ? $d : array('ok' => 0, 'error' => 'offline');
}
function gpbSaveKey($k){
	global $mysqli;
	$k = $mysqli->real_escape_string($k);
	$mysqli->query("UPDATE boom_addons SET custom2 = '$k' WHERE addons = 'gpt_bot'");
	if(function_exists('redisUpdateAddons')){ redisUpdateAddons('gpt_bot'); }
}
/* the site key is created on the first question, so nothing needs to be set up */
function gpbKey(&$c, $fresh = false){
	if($c['key'] !== '' && !$fresh){ return $c['key']; }
	$host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'unknown';
	$r = gpbApi('register', array('site' => $host, 'version' => GPB_VERSION));
	if(!empty($r['ok']) && !empty($r['key'])){ $c['key'] = (string)$r['key']; gpbSaveKey($c['key']); }
	return $c['key'];
}
function gpbBrain(){
	global $mysqli;
	$out = '';
	$q = $mysqli->query("SELECT title, body FROM gpt_bot_brain WHERE active = 1 ORDER BY id ASC");
	if($q){ while($r = $q->fetch_assoc()){ $out .= ($r['title'] !== '' ? $r['title'] . ': ' : '') . $r['body'] . "\n"; } }
	return mb_substr(trim($out), 0, 4000);
}
/* post as the bot in a room, the same way Codychat posts bot messages, but without room checks some builds add */
function gpbPostRoom($bot, $room, $html){
	global $mysqli;
	$bot = (int)$bot; $room = (int)$room; $now = time();
	$mysqli->query("INSERT INTO `boom_chat` (post_date, user_id, post_message, post_roomid, type, log_rank) VALUES ('$now', '$bot', '$html', '$room', 'public__message', '999')");
	if(function_exists('chatAction')){ chatAction($room); }
	return (int)$mysqli->insert_id;
}
/* bot text to stored chat html */
function gpbHtml($t){
	global $mysqli;
	return $mysqli->real_escape_string(nl2br(htmlspecialchars(trim($t), ENT_QUOTES), false));
}
?>
