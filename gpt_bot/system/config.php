<?php
/* GPT Bot add-on for Codychat 10.1 | Author: M Kashif | MIT License */
$load_addons = 'gpt_bot';
require(__DIR__ . '/../../../system/config_addons.php');
if(!canManageAddons()){ die(); }
require_once(__DIR__ . '/addons_function.php');

$c = gpbCfg($addons);
$st = array('all' => 0, 'day' => 0, 'users' => 0);
$day = time() - 86400;
$q = $mysqli->query("SELECT COUNT(*) a, SUM(created > '$day') b, COUNT(DISTINCT user_id) u FROM gpt_bot_log WHERE status = 1");
if($q && $q->num_rows){ $r = $q->fetch_assoc(); $st = array('all' => (int)$r['a'], 'day' => (int)$r['b'], 'users' => (int)$r['u']); }
function gpbRanks($cur){ $o = ''; foreach(rankList() as $v){ if($v != botRank()){ $o .= rankOption($cur, $v); } } return $o; }
function gpbOnOff($id, $v){ return '<select id="' . $id . '" class="full_input"><option value="1" ' . ($v === 1 ? 'selected' : '') . '>On</option><option value="0" ' . ($v !== 1 ? 'selected' : '') . '>Off</option></select>'; }
echo elementTitle('GPT Bot', "loadLob('admin/setting_addons.php');");
?>
<style>
.gpb_tabs{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0 14px}
.gpb_tab{padding:8px 14px;border-radius:20px;cursor:pointer;font-size:14px;opacity:.75}
.gpb_tab.on{opacity:1;font-weight:bold}
.gpb_stats{border-radius:12px;margin:12px 0;display:flex;gap:22px;flex-wrap:wrap}
.gpb_item{border-radius:12px;padding:12px;margin-bottom:10px}
.gpb_q{font-weight:bold;margin:4px 0;white-space:pre-wrap;word-break:break-word}
.gpb_a{white-space:pre-wrap;word-break:break-word;opacity:.9}
.gpb_meta{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;font-size:12px;opacity:.7}
.gpb_row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.gpb_bar{height:10px;border-radius:6px;overflow:hidden;background:rgba(127,127,127,.25);margin:8px 0}
.gpb_bar span{display:block;height:100%;background:#10a37f}
.gpb_badge{font-size:11px;padding:2px 8px;border-radius:10px;background:rgba(127,127,127,.2)}
</style>
<div class="page_full"><div class="page_element"><div class="tpad15">
	<p class="sub_text">An AI assistant that lives in your chat as <b><?php echo htmlspecialchars($c['name']); ?></b>. Members say its name in a room, or send it a private message, and it answers in their own language.</p>
	<div class="bback pad15 gpb_stats">
		<div><b><?php echo $st['all']; ?></b><div class="sub_text text_xsmall">answers given</div></div>
		<div><b><?php echo $st['day']; ?></b><div class="sub_text text_xsmall">in the last 24 hours</div></div>
		<div><b><?php echo $st['users']; ?></b><div class="sub_text text_xsmall">members helped</div></div>
	</div>
	<div class="gpb_tabs">
		<div class="gpb_tab bback on" data-t="set" onclick="gpbTab('set')">Settings</div>
		<div class="gpb_tab bback" data-t="brain" onclick="gpbTab('brain')">Brain</div>
		<div class="gpb_tab bback" data-t="conv" onclick="gpbTab('conv')">Conversations</div>
		<div class="gpb_tab bback" data-t="use" onclick="gpbTab('use')">Usage</div>
	</div>

	<div id="gpb_set" class="gpb_pane">
		<div class="setting_element"><p class="label">GPT Bot on this site</p><?php echo gpbOnOff('gpb_c1', $c['on']); ?></div>
		<div class="setting_element"><p class="label">Answer in rooms</p><p class="sub_text text_xsmall">Members call it by writing its name in a message, for example "<?php echo htmlspecialchars($c['name']); ?> what is PHP?"</p><?php echo gpbOnOff('gpb_c4', $c['room']); ?></div>
		<div class="setting_element"><p class="label">Answer private messages</p><?php echo gpbOnOff('gpb_c5', $c['dm']); ?></div>
		<div class="setting_element"><p class="label">Lowest rank that can ask</p><select id="gpb_c3" class="full_input"><?php echo gpbRanks($c['min']); ?></select></div>
		<div class="setting_element"><p class="label">Questions per member per minute</p><input id="gpb_c6" class="full_input" type="number" min="1" max="30" value="<?php echo (int)$c['rate']; ?>"/></div>
		<div class="setting_element"><p class="label">Your chat's name</p><p class="sub_text text_xsmall">The bot uses it when people ask where they are.</p><input id="gpb_c7" class="full_input" maxlength="60" value="<?php echo htmlspecialchars($c['site']); ?>"/></div>
		<div class="setting_element"><p class="label">Extra words that call the bot</p><p class="sub_text text_xsmall">Comma separated, for example: ai, chatgpt. Its name always works.</p><input id="gpb_c8" class="full_input" maxlength="200" value="<?php echo htmlspecialchars($c['extra']); ?>"/></div>
		<button class="reg_button theme_btn tmargin10" onclick="gpbSave()">Save</button>
	</div>

	<div id="gpb_brain" class="gpb_pane" style="display:none">
		<p class="sub_text">Teach the bot about your chat: rules, rooms, staff, events, how things work. Active notes are given to the bot with every question.</p>
		<div class="setting_element"><p class="label">Title</p><input id="gpb_bt" class="full_input" maxlength="100" placeholder="Room rules"/></div>
		<div class="setting_element"><p class="label">What the bot should know</p><textarea id="gpb_bb" class="full_input" rows="4" maxlength="2000" placeholder="No spam or links in the main room. Moderators: Ali and Sara."></textarea></div>
		<button class="reg_button theme_btn tmargin10" onclick="gpbBrainAdd()">Add to brain</button>
		<div id="gpb_brain_list" class="tmargin15"></div>
	</div>

	<div id="gpb_conv" class="gpb_pane" style="display:none">
		<div class="gpb_row">
			<select id="gpb_cf" class="full_input" style="max-width:200px" onchange="gpbConv(0)"><option value="0">Rooms and private</option><option value="1">Rooms only</option><option value="2">Private only</option></select>
			<input id="gpb_cs" class="full_input" style="max-width:220px" placeholder="Search member or text" onkeyup="if(event.keyCode==13){gpbConv(0);}"/>
			<button class="reg_button default_btn" onclick="gpbConv(0)">Search</button>
			<button class="reg_button delete_btn" onclick="gpbConvClear()">Clear all</button>
		</div>
		<div id="gpb_conv_list" class="tmargin15"></div>
	</div>

	<div id="gpb_use" class="gpb_pane" style="display:none"><div id="gpb_use_box" class="sub_text">Loading...</div></div>

	<p class="sub_text text_xsmall tmargin10">GPT Bot add-on by <a href="https://github.com/cashifdev" target="_blank">M Kashif</a> · free service by <a href="https://chatnoregister.com" target="_blank">chatnoregister.com</a></p>
</div></div></div>
<script>
var gpbPage = 0;
function gpbTab(t){
	$('.gpb_pane').hide(); $('#gpb_' + t).show();
	$('.gpb_tab').removeClass('on'); $('.gpb_tab[data-t="' + t + '"]').addClass('on');
	if(t === 'brain'){ gpbBrain(); } if(t === 'conv'){ gpbConv(0); } if(t === 'use'){ gpbUsage(); }
}
function gpbPost(d, cb){ $.post('addons/gpt_bot/system/admin.php', d, cb, 'json'); }
function gpbSave(){
	gpbPost({ act: 'save', custom1: $('#gpb_c1').val(), custom3: $('#gpb_c3').val(), custom4: $('#gpb_c4').val(), custom5: $('#gpb_c5').val(), custom6: $('#gpb_c6').val(), custom7: $('#gpb_c7').val(), custom8: $('#gpb_c8').val() }, function(r){
		callSaved(r && r.ok ? 'Saved' : 'Not saved', r && r.ok ? 1 : 3);
	});
}
function gpbBrain(){ $('#gpb_brain_list').load('addons/gpt_bot/system/admin.php', { act: 'brain' }); }
function gpbBrainAdd(){
	gpbPost({ act: 'brain_add', title: $('#gpb_bt').val(), body: $('#gpb_bb').val() }, function(r){
		if(r && r.ok){ $('#gpb_bt').val(''); $('#gpb_bb').val(''); gpbBrain(); callSaved('Added to brain', 1); } else { callSaved(r && r.msg ? r.msg : 'Not saved', 3); }
	});
}
function gpbBrainSet(id, a){ gpbPost({ act: 'brain_set', id: id, active: a }, function(){ gpbBrain(); }); }
function gpbBrainDel(id){ if(!confirm('Delete this note?')){ return; } gpbPost({ act: 'brain_del', id: id }, function(){ gpbBrain(); }); }
function gpbConv(p){ gpbPage = p; $('#gpb_conv_list').load('addons/gpt_bot/system/admin.php', { act: 'conv', page: p, kind: $('#gpb_cf').val(), s: $('#gpb_cs').val() }); }
function gpbConvDel(id){ gpbPost({ act: 'conv_del', id: id }, function(){ gpbConv(gpbPage); }); }
function gpbConvClear(){ if(!confirm('Delete every conversation in the log?')){ return; } gpbPost({ act: 'conv_clear' }, function(){ gpbConv(0); }); }
function gpbUsage(){ $('#gpb_use_box').load('addons/gpt_bot/system/admin.php', { act: 'usage' }); }
</script>
