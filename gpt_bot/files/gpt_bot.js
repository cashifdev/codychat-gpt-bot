/* GPT Bot add-on for Codychat 10.1 | Author: M Kashif | MIT License */
(function(){
	var C = window.GPB_CFG || {};
	if(!C.bot || typeof jQuery === 'undefined'){ return; }
	var re;
	try { re = new RegExp(C.re, 'iu'); } catch(e){ re = new RegExp(C.re.replace(/\\p\{L\}\\p\{N\}/g, 'A-Za-z0-9'), 'i'); }
	function read(d){
		var o = {};
		if(!d){ return o; }
		if(typeof d === 'object'){ return d; }
		String(d).split('&').forEach(function(p){
			var i = p.indexOf('=');
			if(i > 0){ try { o[decodeURIComponent(p.slice(0, i))] = decodeURIComponent(p.slice(i + 1).replace(/\+/g, ' ')); } catch(e){} }
		});
		return o;
	}
	function ask(mode){
		setTimeout(function(){
			jQuery.post('addons/gpt_bot/system/ask.php', { mode: mode }, function(r){
				if(r && r.msg && typeof callSaved === 'function'){ callSaved(r.msg, 3); }
			}, 'json');
		}, 250);
	}
	jQuery(document).ajaxSuccess(function(ev, xhr, s){
		if(!s || !s.url){ return; }
		var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
		if(!res){ try { res = JSON.parse(xhr.responseText); } catch(e){ res = null; } }
		if(!res || res.code != 1){ return; }
		var d = read(s.data);
		if(!d.content || /admin|addons\/gpt_bot/.test(s.url)){ return; }
		if(d.target !== undefined && d.target !== ''){
			if(C.dm && parseInt(d.target, 10) === C.bot){ ask('dm'); }
		}
		else if(C.room && re.test(String(d.content).replace(/<[^>]*>/g, ' '))){ ask('room'); }
	});
})();
