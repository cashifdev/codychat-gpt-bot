<?php
/* GPT Bot add-on for Codychat 10.1 | Author: M Kashif | MIT License */
 if(boomAllow($addons['addons_access'])){
	require_once(BOOM_PATH . '/addons/gpt_bot/system/addons_function.php');
	$gpb_c = gpbCfg($addons);
	if($gpb_c['on'] === 1 && $gpb_c['bot'] > 0 && (int)$data['user_rank'] >= $gpb_c['min']){
		gpbOnline($gpb_c['bot']);
		$gpb_v = @filemtime(BOOM_PATH . '/addons/gpt_bot/files/gpt_bot.js');
?>
<script data-cfasync="false">
window.GPB_CFG = <?php echo json_encode(array('bot' => $gpb_c['bot'], 'room' => $gpb_c['room'], 'dm' => $gpb_c['dm'], 're' => gpbPattern($gpb_c))); ?>;
</script>
<script data-cfasync="false" src="addons/gpt_bot/files/gpt_bot.js?v=<?php echo $gpb_v; ?>"></script>
<?php } } ?>
