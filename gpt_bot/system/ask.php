<?php
/* GPT Bot add-on for Codychat 10.1 | Author: M Kashif | MIT License */
/* Called right after a member posts. It finds that member's newest message that calls the bot,
   asks the GPT service and posts the answer as the bot. mode = room | dm */
$load_addons = 'gpt_bot';
require(__DIR__ . '/../../../system/config_addons.php');
require_once(__DIR__ . '/addons_function.php');
header('Content-Type: application/json');
function gpbOut($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$c = gpbCfg($addons);
$me = (int)$data['user_id'];
$mode = (isset($_POST['mode']) && $_POST['mode'] === 'dm') ? 'dm' : 'room';
if($me <= 0 || $c['on'] !== 1 || $c['bot'] <= 0 || $me === $c['bot']){ gpbOut(array('ok' => 0)); }
if((int)$data['user_rank'] < $c['min']){ gpbOut(array('ok' => 0)); }
session_write_close();
@set_time_limit(70);
@ignore_user_abort(true);

$now = time();
$since = $now - 90;
$bot = $c['bot'];
$src = 0; $text = ''; $room = 0; $kind = 1;

if($mode === 'room'){
	if($c['room'] !== 1){ gpbOut(array('ok' => 0)); }
	$room = (int)$data['user_roomid'];
	$q = $mysqli->query("SELECT post_id, post_message FROM boom_chat WHERE user_id = '$me' AND post_roomid = '$room' AND post_date >= '$since' ORDER BY post_id DESC LIMIT 3");
	if($q){ while($r = $q->fetch_assoc()){ $t = gpbPlain($r['post_message']); if(gpbCalled($t, $c)){ $src = (int)$r['post_id']; $text = $t; break; } } }
}
else {
	if($c['dm'] !== 1){ gpbOut(array('ok' => 0)); }
	$kind = 2;
	$q = $mysqli->query("SELECT id, message FROM boom_private WHERE hunter = '$me' AND target = '$bot' AND time >= '$since' ORDER BY id DESC LIMIT 1");
	if($q && $q->num_rows){ $r = $q->fetch_assoc(); $src = (int)$r['id']; $text = gpbPlain($r['message']); }
}
if($src <= 0 || $text === ''){ gpbOut(array('ok' => 0)); }

/* one answer per message, even if the call arrives twice */
$qtext = $mysqli->real_escape_string(mb_substr($text, 0, 1000));
$mysqli->query("INSERT IGNORE INTO gpt_bot_log (kind, src_id, user_id, room_id, question, answer, status, created) VALUES ('$kind', '$src', '$me', '$room', '$qtext', '', 0, '$now')");
if($mysqli->affected_rows < 1){ gpbOut(array('ok' => 0, 'dup' => 1)); }
$lid = (int)$mysqli->insert_id;
function gpbDone($lid, $status, $answer, $err){
	global $mysqli;
	$a = $mysqli->real_escape_string($answer); $e = $mysqli->real_escape_string($err);
	$mysqli->query("UPDATE gpt_bot_log SET status = '$status', answer = '$a', err = '$e' WHERE id = '$lid'");
}

/* members can't flood the bot */
$minute = $now - 60;
$cq = $mysqli->query("SELECT COUNT(*) n FROM gpt_bot_log WHERE user_id = '$me' AND created > '$minute'");
$cnt = $cq ? (int)$cq->fetch_assoc()['n'] : 0;
if($cnt > $c['rate']){ gpbDone($lid, 2, '', 'rate'); gpbOut(array('ok' => 0, 'msg' => 'Slow down a little, ' . $c['name'] . ' can answer ' . $c['rate'] . ' questions a minute.')); }

/* a little recent chat so the bot follows the conversation */
$ctx = array();
if($kind === 1){
	$q = $mysqli->query("SELECT c.post_message, u.user_name FROM boom_chat c JOIN boom_users u ON u.user_id = c.user_id
		WHERE c.post_roomid = '$room' AND c.post_id < '$src' AND c.type = 'public__message' ORDER BY c.post_id DESC LIMIT 8");
	if($q){ while($r = $q->fetch_assoc()){ $t = gpbPlain($r['post_message']); if($t !== ''){ $ctx[] = $r['user_name'] . ': ' . mb_substr($t, 0, 250); } } }
}
else {
	$q = $mysqli->query("SELECT p.message, u.user_name FROM boom_private p JOIN boom_users u ON u.user_id = p.hunter
		WHERE ((p.hunter = '$me' AND p.target = '$bot') OR (p.hunter = '$bot' AND p.target = '$me')) AND p.id < '$src' ORDER BY p.id DESC LIMIT 6");
	if($q){ while($r = $q->fetch_assoc()){ $t = gpbPlain($r['message']); if($t !== ''){ $ctx[] = $r['user_name'] . ': ' . mb_substr($t, 0, 250); } } }
}
$ctx = array_reverse($ctx);

$payload = array('q' => $text, 'user' => $data['user_name'], 'bot' => $c['name'], 'mode' => $mode, 'ctx' => $ctx,
	'brain' => gpbBrain(), 'site_name' => $c['site']);
$payload['key'] = gpbKey($c);
$r = gpbApi('ask', $payload);
if(empty($r['ok']) && isset($r['error']) && $r['error'] === 'bad_key'){
	$payload['key'] = gpbKey($c, true);
	$r = gpbApi('ask', $payload);
}
if(empty($r['ok']) || empty($r['answer'])){
	$err = isset($r['error']) ? (string)$r['error'] : 'offline';
	gpbDone($lid, 2, '', $err);
	$msg = $err === 'limit' ? $c['name'] . ' has answered a lot today. Try again tomorrow.' : $c['name'] . ' is busy right now. Try again in a minute.';
	gpbOut(array('ok' => 0, 'msg' => $msg));
}
$answer = trim((string)$r['answer']);
$answer = trim(preg_replace('/^(@' . preg_quote($data['user_name'], '/') . '[\s,:]*)+/iu', '', $answer));
if($answer === ''){ $answer = (string)$r['answer']; }
if($kind === 1){
	gpbPostRoom($bot, $room, gpbHtml('@' . $data['user_name'] . ' ' . $answer));
}
else {
	botPostPrivate($bot, $me, gpbHtml($answer));
	if(function_exists('redisUpdatePrivate')){ redisUpdatePrivate($me); }
}
gpbDone($lid, 1, $answer, '');
gpbOut(array('ok' => 1));
?>
