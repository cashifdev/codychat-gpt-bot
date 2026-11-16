<?php
/* GPT Bot add-on for Codychat 10.1 | Author: M Kashif | MIT License */
/* Admin endpoint: act=save | brain | brain_add | brain_set | brain_del | conv | conv_del | conv_clear | usage */
$load_addons = 'gpt_bot';
require(__DIR__ . '/../../../system/config_addons.php');
if(!canManageAddons()){ die(); }
require_once(__DIR__ . '/addons_function.php');

$act = isset($_POST['act']) ? (string)$_POST['act'] : '';
$c = gpbCfg($addons);
function gpbJson($a){ header('Content-Type: application/json'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function gpbE($t){ return htmlspecialchars((string)$t, ENT_QUOTES); }
function gpbAgo($t){
	$d = time() - (int)$t;
	if($d < 60){ return 'just now'; } if($d < 3600){ return floor($d / 60) . ' min ago'; } if($d < 86400){ return floor($d / 3600) . ' h ago'; }
	return date('Y-m-d H:i', (int)$t);
}

if($act === 'save'){
	$v = array();
	$v['custom1'] = ((int)($_POST['custom1'] ?? 1) === 1) ? 1 : 0;
	$v['custom3'] = (int)($_POST['custom3'] ?? 0);
	$v['custom4'] = ((int)($_POST['custom4'] ?? 1) === 1) ? 1 : 0;
	$v['custom5'] = ((int)($_POST['custom5'] ?? 1) === 1) ? 1 : 0;
	$v['custom6'] = max(1, min(30, (int)($_POST['custom6'] ?? 4)));
	$v['custom7'] = $mysqli->real_escape_string(mb_substr(trim(strip_tags((string)($_POST['custom7'] ?? ''))), 0, 60));
	$v['custom8'] = $mysqli->real_escape_string(mb_substr(trim(strip_tags((string)($_POST['custom8'] ?? ''))), 0, 200));
	$set = array(); foreach($v as $k => $x){ $set[] = "$k = '$x'"; }
	$mysqli->query("UPDATE boom_addons SET " . implode(', ', $set) . " WHERE addons = 'gpt_bot'");
	if(function_exists('redisUpdateAddons')){ redisUpdateAddons('gpt_bot'); }
	gpbJson(array('ok' => 1));
}

if($act === 'brain_add'){
	$t = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 100);
	$b = mb_substr(trim(strip_tags((string)($_POST['body'] ?? ''))), 0, 2000);
	if($b === ''){ gpbJson(array('ok' => 0, 'msg' => 'Write what the bot should know')); }
	$t = $mysqli->real_escape_string($t); $b = $mysqli->real_escape_string($b); $now = time();
	$mysqli->query("INSERT INTO gpt_bot_brain (title, body, active, created) VALUES ('$t', '$b', 1, '$now')");
	gpbJson(array('ok' => 1));
}
if($act === 'brain_set'){
	$id = (int)($_POST['id'] ?? 0); $a = ((int)($_POST['active'] ?? 0) === 1) ? 1 : 0;
	$mysqli->query("UPDATE gpt_bot_brain SET active = '$a' WHERE id = '$id'");
	gpbJson(array('ok' => 1));
}
if($act === 'brain_del'){
	$id = (int)($_POST['id'] ?? 0);
	$mysqli->query("DELETE FROM gpt_bot_brain WHERE id = '$id'");
	gpbJson(array('ok' => 1));
}
if($act === 'brain'){
	$q = $mysqli->query("SELECT * FROM gpt_bot_brain ORDER BY id DESC");
	if(!$q || !$q->num_rows){ echo '<p class="sub_text">The brain is empty. Add your first note above.</p>'; exit; }
	$len = 0;
	while($r = $q->fetch_assoc()){
		if((int)$r['active'] === 1){ $len += mb_strlen($r['title'] . $r['body']) + 3; }
		$on = (int)$r['active'] === 1;
		echo '<div class="gpb_item bback"><div class="gpb_meta"><span><b>' . gpbE($r['title'] !== '' ? $r['title'] : 'Note') . '</b> <span class="gpb_badge">' . ($on ? 'active' : 'paused') . '</span></span><span>' . gpbAgo($r['created']) . '</span></div>'
			. '<div class="gpb_a tmargin5">' . gpbE($r['body']) . '</div><div class="gpb_row tmargin10">'
			. '<button class="reg_button default_btn" onclick="gpbBrainSet(' . (int)$r['id'] . ',' . ($on ? 0 : 1) . ')">' . ($on ? 'Pause' : 'Activate') . '</button>'
			. '<button class="reg_button delete_btn" onclick="gpbBrainDel(' . (int)$r['id'] . ')">Delete</button></div></div>';
	}
	echo '<p class="sub_text text_xsmall">' . min($len, 4000) . ' of 4000 characters used by active notes.' . ($len > 4000 ? ' Notes past the limit are left out, so keep them short.' : '') . '</p>';
	exit;
}

if($act === 'conv_del'){
	$id = (int)($_POST['id'] ?? 0);
	$mysqli->query("DELETE FROM gpt_bot_log WHERE id = '$id'");
	gpbJson(array('ok' => 1));
}
if($act === 'conv_clear'){
	$mysqli->query("TRUNCATE TABLE gpt_bot_log");
	gpbJson(array('ok' => 1));
}
if($act === 'conv'){
	$per = 25;
	$page = max(0, (int)($_POST['page'] ?? 0));
	$kind = (int)($_POST['kind'] ?? 0);
	$s = trim((string)($_POST['s'] ?? ''));
	$w = array('1');
	if($kind === 1 || $kind === 2){ $w[] = "l.kind = '$kind'"; }
	if($s !== ''){ $e = $mysqli->real_escape_string(addcslashes($s, '%_')); $w[] = "(u.user_name LIKE '%$e%' OR l.question LIKE '%$e%' OR l.answer LIKE '%$e%')"; }
	$off = $page * $per;
	$q = $mysqli->query("SELECT l.*, u.user_name, r.room_name FROM gpt_bot_log l LEFT JOIN boom_users u ON u.user_id = l.user_id LEFT JOIN boom_rooms r ON r.room_id = l.room_id
		WHERE " . implode(' AND ', $w) . " ORDER BY l.id DESC LIMIT $off, " . ($per + 1));
	if(!$q || !$q->num_rows){ echo '<p class="sub_text">No conversations yet. They show up here as soon as members talk to ' . gpbE($c['name']) . '.</p>'; exit; }
	$n = 0; $more = false;
	while($r = $q->fetch_assoc()){
		if(++$n > $per){ $more = true; break; }
		$where = (int)$r['kind'] === 2 ? 'Private chat' : 'Room: ' . gpbE($r['room_name'] ?? ('#' . $r['room_id']));
		$state = (int)$r['status'] === 1 ? '' : ' <span class="gpb_badge">' . ((int)$r['status'] === 0 ? 'answering' : 'not answered: ' . gpbE($r['err'])) . '</span>';
		echo '<div class="gpb_item bback"><div class="gpb_meta"><span><b>' . gpbE($r['user_name'] ?? 'deleted member') . '</b> · ' . $where . $state . '</span><span>' . gpbAgo($r['created']) . '</span></div>'
			. '<div class="gpb_q">' . gpbE($r['question']) . '</div>'
			. ($r['answer'] !== '' ? '<div class="gpb_a"><b>' . gpbE($c['name']) . ':</b> ' . gpbE($r['answer']) . '</div>' : '')
			. '<div class="gpb_row tmargin5"><span class="sub_text text_xsmall" style="cursor:pointer" onclick="gpbConvDel(' . (int)$r['id'] . ')">Delete</span></div></div>';
	}
	echo '<div class="gpb_row">';
	if($page > 0){ echo '<button class="reg_button default_btn" onclick="gpbConv(' . ($page - 1) . ')">Newer</button>'; }
	if($more){ echo '<button class="reg_button default_btn" onclick="gpbConv(' . ($page + 1) . ')">Older</button>'; }
	echo '</div>';
	exit;
}

if($act === 'usage'){
	$key = gpbKey($c);
	$r = $key !== '' ? gpbApi('usage', array('key' => $key)) : array('ok' => 0);
	if(empty($r['ok'])){ echo '<p>Couldn\'t reach the GPT service right now. Check that your server can make outgoing HTTPS requests to chatnoregister.com.</p>'; exit; }
	$cap = max(1, (int)$r['cap']); $used = (int)$r['used']; $pct = min(100, round($used * 100 / $cap));
	echo '<div class="gpb_item bback"><p class="label">Today\'s answers</p><div class="gpb_bar"><span style="width:' . $pct . '%"></span></div>'
		. '<p><b>' . $used . '</b> of <b>' . $cap . '</b> used · <b>' . (int)$r['left'] . '</b> left. Resets at midnight UTC.</p></div>'
		. '<p class="sub_text">GPT Bot is completely free. The daily fair-use limit of ' . $cap . ' answers keeps the service fast for every chat. Your site key:</p>'
		. '<p><code style="word-break:break-all">' . gpbE($key) . '</code></p>';
	exit;
}
gpbJson(array('ok' => 0));
?>
