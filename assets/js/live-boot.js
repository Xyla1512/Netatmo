/**
 * Boot routine for the [naws_live] dashboard.
 *
 * Until 1.9.5 this code was printed into the page as an inline <script>
 * block on wp_footer. That was a workaround for wp_add_inline_script()
 * being silently dropped on some installations, which left every chart
 * blank. Shipping it as a registered file removes the inline block the
 * plugin guidelines ask about and fixes the original problem properly:
 * an enqueued file cannot be dropped the way an inline fragment can.
 *
 * The per-widget payload still travels in the non-executable
 * <script type="application/json" data-naws="live"> element the template
 * prints next to the widget. This file finds every such element on the
 * page, so any number of [naws_live] shortcodes boot from one copy.
 */
(function () {
  var _nodes = document.querySelectorAll('script[type="application/json"][data-naws="live"]');
  for (var _i = 0; _i < _nodes.length; _i++) { nawsLiveBoot(_nodes[_i]); }

  function nawsLiveBoot(_d) {
    var NAWS_LIVE = JSON.parse(_d.textContent || '{}');
    var WID = NAWS_LIVE.WID;
    if (!WID || window['_nawsBoot_' + WID]) return;
    if (!document.getElementById(WID)) return;
    window['_nawsBoot_' + WID] = true;
// See history-boot.js: read the widget's own element, not <html>, which most
// themes never give a font — that gave every chart the browser default.
var NAWS_FONT=getComputedStyle(document.getElementById(WID)).fontFamily
            ||getComputedStyle(document.body).fontFamily
            ||'sans-serif';
var TIME_SUFFIX=NAWS_LIVE.TIME_SUFFIX;
var AJAX=NAWS_LIVE.AJAX;
var NONCE=document.getElementById(WID).dataset.nonce;
var RFSH=(parseInt(document.getElementById(WID).dataset.refresh,10)||60)*1000;
var HIDE=document.getElementById(WID).dataset.hidden?document.getElementById(WID).dataset.hidden.split(',').filter(Boolean):[];
var MODULE4_SLUGS=JSON.parse(document.getElementById(WID).dataset.module4||'{}');
var MODULE4_INFO=NAWS_LIVE.MODULE4_INFO;
var NAWS_I18N=NAWS_LIVE.I18N;
var CARD_ORDER=NAWS_LIVE.CARD_ORDER||[];
var PRESS_TREND_HTML=NAWS_LIVE.PRESS_TREND_HTML;
var liveEl=document.getElementById(WID+'-live');
var chartsEl=document.getElementById(WID+'-charts');
var built=false;
var charts={};
var chartData={};
var CHART_CONFIGS=NAWS_LIVE.CHART_CONFIGS;

/* ── HELPERS ─────────────────────────── */
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmt(v){
  if(!v||v==='0000-00-00 00:00:00') return '—';
  var d=/^\d+$/.test(String(v))?new Date(+v*1000):new Date(String(v).replace(' ','T'));
  if(isNaN(d)) return String(v);
  var p=function(n){return String(n).padStart(2,'0');};
  return p(d.getDate())+'.'+p(d.getMonth()+1)+'.'+d.getFullYear()+' · '+p(d.getHours())+':'+p(d.getMinutes())+(TIME_SUFFIX?' '+TIME_SUFFIX:'');
}
function sfmt(v){
  if(!v) return '';
  var d=/^\d+$/.test(String(v))?new Date(+v*1000):new Date(String(v).replace(' ','T'));
  if(isNaN(d)) return '';
  var p=function(n){return String(n).padStart(2,'0');};
  return p(d.getHours())+':'+p(d.getMinutes())+(TIME_SUFFIX?' '+TIME_SUFFIX:'');
}
function hhmm(ms){
  var d=new Date(ms); var p=function(n){return String(n).padStart(2,'0');};
  return p(d.getHours())+':'+p(d.getMinutes());
}
function cdir(deg){
  var d=['N','NNO','NO','ONO','O','OSO','SO','SSO','S','SSW','SW','WSW','W','WNW','NW','NNW'];
  return d[Math.round(((+deg%360)+360)%360/22.5)%16];
}
function post(params,cb){
  var xhr=new XMLHttpRequest();
  xhr.open('POST',AJAX);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.onload=function(){if(xhr.status===200){try{cb(JSON.parse(xhr.responseText));}catch(e){cb(null);}}else cb(null);};
  var body='nonce='+encodeURIComponent(NONCE);
  Object.keys(params).forEach(function(k){
    var v=params[k];
    if(Array.isArray(v)) v.forEach(function(vi){body+='&'+encodeURIComponent(k)+'[]='+encodeURIComponent(vi);});
    else body+='&'+encodeURIComponent(k)+'='+encodeURIComponent(v);
  });
  xhr.send(body);
}

/* ── ICONS ───────────────────────────── */
var ICO=NAWS_LIVE.ICO;
var NAWS_ICON_SET=NAWS_LIVE.ICON_SET;

/* ── COMPASS ─────────────────────────── */
var ROSE='<svg style="position:absolute;top:0;left:0;width:100%;height:100%" viewBox="-4 -4 168 168" xmlns="http://www.w3.org/2000/svg">'
  +'<circle cx="80" cy="80" r="72" fill="#f4fafa" stroke="#c0d4d4" stroke-width="1.5"/>'
  +'<circle cx="80" cy="80" r="54" fill="none" stroke="#daeaea" stroke-width="1"/>'
  +'<circle cx="80" cy="80" r="34" fill="none" stroke="#e5f0f0" stroke-width="1" stroke-dasharray="3 4"/>'
  +'<polygon points="80,8 88,80 80,92 72,80" fill="#427272"/>'
  +'<polygon points="80,8 80,92 88,80" fill="#c0d8d8"/>'
  +'<polygon points="80,152 72,80 80,68 88,80" fill="#427272"/>'
  +'<polygon points="80,152 80,68 72,80" fill="#c0d8d8"/>'
  +'<polygon points="152,80 80,72 68,80 80,88" fill="#427272"/>'
  +'<polygon points="152,80 68,80 80,88" fill="#c0d8d8"/>'
  +'<polygon points="8,80 80,88 92,80 80,72" fill="#427272"/>'
  +'<polygon points="8,80 92,80 80,72" fill="#c0d8d8"/>'
  +'<polygon points="129,31 76,76 80,80" fill="#7aa0a0"/>'
  +'<polygon points="129,31 84,84 80,80" fill="#c0d8d8"/>'
  +'<polygon points="129,129 84,76 80,80" fill="#7aa0a0"/>'
  +'<polygon points="129,129 76,84 80,80" fill="#c0d8d8"/>'
  +'<polygon points="31,129 84,84 80,80" fill="#7aa0a0"/>'
  +'<polygon points="31,129 76,76 80,80" fill="#c0d8d8"/>'
  +'<polygon points="31,31 76,84 80,80" fill="#7aa0a0"/>'
  +'<polygon points="31,31 84,76 80,80" fill="#c0d8d8"/>'
  +'<circle cx="80" cy="80" r="9" fill="#427272" stroke="#fff" stroke-width="2.5"/>'
  +'<text x="80" y="9" text-anchor="middle" dominant-baseline="middle" font-size="13" font-weight="800" fill="#2d5252">N</text>'
  +'<text x="80" y="153" text-anchor="middle" dominant-baseline="middle" font-size="13" font-weight="800" fill="#2d5252">S</text>'
  +'<text x="153" y="80" text-anchor="middle" dominant-baseline="middle" font-size="13" font-weight="800" fill="#2d5252">E</text>'
  +'<text x="7" y="80" text-anchor="middle" dominant-baseline="middle" font-size="13" font-weight="800" fill="#2d5252">W</text>'
  +'<text x="133" y="27" text-anchor="middle" font-size="10" font-weight="600" fill="#7aa0a0">NE</text>'
  +'<text x="133" y="136" text-anchor="middle" font-size="10" font-weight="600" fill="#7aa0a0">SE</text>'
  +'<text x="27" y="136" text-anchor="middle" font-size="10" font-weight="600" fill="#7aa0a0">SW</text>'
  +'<text x="27" y="27" text-anchor="middle" font-size="10" font-weight="600" fill="#7aa0a0">NW</text>'
  +'</svg>';
function arrowSVG(deg){
  return '<svg id="'+WID+'-arr" style="position:absolute;top:0;left:0;width:100%;height:100%;transform:rotate('+deg+'deg);transform-origin:50% 50%;transition:transform 1.2s ease" viewBox="-4 -4 168 168" xmlns="http://www.w3.org/2000/svg">'
    +'<polygon points="80,18 87,38 80,32 73,38" fill="#c0392b"/>'
    +'<line x1="80" y1="32" x2="80" y2="88" stroke="#c0392b" stroke-width="5" stroke-linecap="round"/>'
    +'<line x1="80" y1="88" x2="80" y2="106" stroke="#7aa0a0" stroke-width="3" stroke-linecap="round" opacity=".4"/>'
    +'</svg>';
}

/* ── GAUGE ───────────────────────────── */
function gaugeSVG(wv,gv){
  // Dynamic scale based on actual wind/gust values
  var rawMax=Math.max(+wv||0,+gv||0);
  var steps=[10,15,20,30,40,60,80,100,120,150];
  var maxVal=steps[0];
  for(var i=0;i<steps.length;i++){if(steps[i]>=rawMax){maxVal=steps[i];break;}}
  if(rawMax>150) maxVal=Math.ceil(rawMax/10)*10;

  wv=Math.max(0,Math.min(maxVal,+wv||0));
  gv=Math.max(0,Math.min(maxVal,+gv||0));

  var numTicks=(maxVal<=20)?5:(maxVal<=60)?6:5;
  var tickStep=maxVal/numTicks;

  var CX=100,CY=98,R=78;
  function pt(v){
    var a=Math.PI+(v/maxVal)*Math.PI;
    return{x:(CX+R*Math.cos(a)).toFixed(1),y:(CY+R*Math.sin(a)).toFixed(1)};
  }
  var w=pt(wv),g=pt(gv);
  var s='<svg class="naws-gauge-svg" viewBox="14 12 172 86" xmlns="http://www.w3.org/2000/svg">';
  s+='<path d="M'+(CX-R)+','+CY+' A'+R+','+R+',0,0,1,'+(CX+R)+','+CY+'" fill="none" stroke="#e0eeee" stroke-width="9" stroke-linecap="round"/>';
  if(gv>0) s+='<path d="M'+(CX-R)+','+CY+' A'+R+','+R+',0,0,1,'+g.x+','+g.y+'" fill="none" stroke="#7aa0a0" stroke-width="5" stroke-linecap="round" opacity=".45" stroke-dasharray="5 3"/>';
  if(wv>0) s+='<path d="M'+(CX-R)+','+CY+' A'+R+','+R+',0,0,1,'+w.x+','+w.y+'" fill="none" stroke="#427272" stroke-width="9" stroke-linecap="round" opacity=".7"/>';
  for(var i=0;i<=numTicks;i++){
    var val=i*tickStep;
    var a=Math.PI+(val/maxVal)*Math.PI;
    var r1=R-10,r2=R-20;
    s+='<line x1="'+(CX+r1*Math.cos(a)).toFixed(1)+'" y1="'+(CY+r1*Math.sin(a)).toFixed(1)+'"'
      +' x2="'+(CX+r2*Math.cos(a)).toFixed(1)+'" y2="'+(CY+r2*Math.sin(a)).toFixed(1)+'"'
      +' stroke="#7aa0a0" stroke-width="1.8"/>';
    var lx=(CX+(R-29)*Math.cos(a)).toFixed(1),ly=(CY+(R-29)*Math.sin(a)).toFixed(1);
    s+='<text x="'+lx+'" y="'+ly+'" text-anchor="middle" dominant-baseline="middle"'
      +' font-size="9" font-weight="700" fill="#7aa0a0">'+Math.round(val)+'</text>';
  }
  s+='<line x1="'+CX+'" y1="'+CY+'" x2="'+w.x+'" y2="'+w.y+'" stroke="#2d5252" stroke-width="3.5" stroke-linecap="round"/>';
  if(gv>0) s+='<line x1="'+CX+'" y1="'+CY+'" x2="'+g.x+'" y2="'+g.y+'" stroke="#7aa0a0" stroke-width="2.5" stroke-linecap="round" opacity=".55" stroke-dasharray="4 3"/>';
  s+='<circle cx="'+CX+'" cy="'+CY+'" r="7" fill="#427272" stroke="#fff" stroke-width="2.5"/>';
  s+='</svg>';
  return s;
}

/* ── INDEX READINGS ──────────────────── */
function indexReadings(rows){
  var p={};
  var isOutdoor=function(r){return r.module_type==='NAModule1';};
  var isNAMain =function(r){return r.module_type==='NAMain'||r.module_type==='NAOldModule';};
  var isModule4=function(r){return r.module_type==='NAModule4';};
  rows.forEach(function(r){
    var key=r.parameter;
    if(isNAMain(r)){
      // NAMain: prefix shared param names so they never overwrite outdoor readings
      if(r.parameter==='Temperature') key='Temperature_indoor';
      if(r.parameter==='Humidity')    key='Humidity_indoor';
      if(r.parameter==='min_temp')    key='min_temp_indoor';
      if(r.parameter==='max_temp')    key='max_temp_indoor';
    } else if(isModule4(r)){
      // NAModule4: append slug so Gast/Sleeping params are unique
      var slug=MODULE4_SLUGS[r.module_id]||('m4_'+String(r.module_id).replace(/:/g,'').slice(-4));
      key=r.parameter+'_'+slug;
    }
    if(!p[key]) p[key]=Object.assign({},r,{_key:key});
    else if(isNAMain(p[key])&&isOutdoor(r)) p[key]=Object.assign({},r,{_key:key});
  });
  return p;
}

/* ── CARD HTML ───────────────────────── */
/* cardId defaults to param. It differs only where a card can be fed by more
   than one reading: the pressure card falls back to AbsolutePressure, but it
   is still the Pressure card, and the sort order knows it under that name. */
function mkCard(cls,icoKey,lbl,param,val,unit,ts,subs,extra,cardId){
  var h='<div class="naws-card '+cls+'" data-card="'+esc(cardId||param)+'">'
    +'<div class="naws-ico">'+ICO[icoKey]+'</div>'
    +'<div class="naws-lbl">'+lbl+'</div>'
    +'<div class="naws-val" data-param="'+esc(param)+'">'+esc(String(val??'—'))+'</div>'
    +'<div class="naws-unit">'+esc(unit)+'</div>'
    +(extra||'');
  if(subs&&subs.length){
    h+='<div class="naws-subs">';
    subs.forEach(function(s){
      h+='<div class="naws-sub"><div class="naws-sub-lbl">'+esc(s.l)+'</div>'
        +'<div class="naws-sub-val">'+esc(String(s.v??'—'))+'<span class="naws-sub-u"> '+esc(s.u||'')+'</span></div>';
      if(s.t) h+='<div class="naws-sub-time">'+sfmt(s.t)+'</div>';
      h+='</div>';
    });
    h+='</div>';
  }
  h+='<div class="naws-time">'+fmt(ts)+'</div></div>';
  return h;
}

/* ── BUILD LIVE ──────────────────────── */
function buildLive(rows){
  var p=indexReadings(rows);
  var wv=p.WindStrength?parseFloat(p.WindStrength.value)||0:0;
  var gv=p.GustStrength?parseFloat(p.GustStrength.value)||0:0;
  var wDeg=p.WindAngle?parseFloat(p.WindAngle.value)||0:0;
  var wu=esc((p.WindStrength||{}).unit||'km/h');
  var gu=esc((p.GustStrength||{}).unit||'km/h');
  var h='<div class="naws-grid">';

  // ── Außentemperatur (NAModule1) ─────────────────────────────────────────
  if(HIDE.indexOf('Temperature')<0&&p.Temperature){
    var r=p.Temperature,subs=[];
    if(p.min_temp) subs.push({l:'Min',v:p.min_temp.value,u:p.min_temp.unit||'°C'});
    if(p.max_temp) subs.push({l:'Max',v:p.max_temp.value,u:p.max_temp.unit||'°C'});
    h+=mkCard('c-temp','temp',NAWS_I18N.card_temperature+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_outdoor+'</span>','Temperature',r.value,r.unit||'°C',r.recorded_at,subs);
  }
  if(HIDE.indexOf('min_temp')<0&&p.min_temp&&HIDE.indexOf('Temperature')>=0)
    h+=mkCard('c-temp','temp',NAWS_I18N.card_temp_min+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_outdoor+'</span>','min_temp',p.min_temp.value,p.min_temp.unit||'°C',p.min_temp.recorded_at,[]);
  if(HIDE.indexOf('max_temp')<0&&p.max_temp&&HIDE.indexOf('Temperature')>=0)
    h+=mkCard('c-temp','temp',NAWS_I18N.card_temp_max+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_outdoor+'</span>','max_temp',p.max_temp.value,p.max_temp.unit||'°C',p.max_temp.recorded_at,[]);

  // ── Außen-Luftfeuchtigkeit ─────────────────────────────────────────────
  if(HIDE.indexOf('Humidity')<0&&p.Humidity)
    h+=mkCard('c-humid','humid',NAWS_I18N.card_humidity+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_outdoor+'</span>','Humidity',p.Humidity.value,p.Humidity.unit||'%',p.Humidity.recorded_at,[]);

  // ── Luftdruck (NAMain) ─────────────────────────────────────────────────
  var pr=p.Pressure||p.AbsolutePressure;
  if(HIDE.indexOf('Pressure')<0&&pr)
    h+=mkCard('c-press','press',NAWS_I18N.card_pressure,pr.parameter,pr.value,pr.unit||'hPa',pr.recorded_at,[],PRESS_TREND_HTML,'Pressure');

  // ── CO₂ Basis (NAMain) ────────────────────────────────────────────────
  if(HIDE.indexOf('CO2')<0&&p.CO2)
    h+=mkCard('c-co2','co2',NAWS_I18N.card_co2+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_base+'</span>','CO2',p.CO2.value,p.CO2.unit||'ppm',p.CO2.recorded_at,[]);

  // ── Lärm Basis (NAMain) ───────────────────────────────────────────────
  if(HIDE.indexOf('Noise')<0&&p.Noise)
    h+=mkCard('c-noise','noise',NAWS_I18N.card_noise+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_base+'</span>','Noise',p.Noise.value,p.Noise.unit||'dB',p.Noise.recorded_at,[]);

  // ── Innentemperatur Basis (NAMain) ─────────────────────────────────────
  if(HIDE.indexOf('Temperature_indoor')<0&&p.Temperature_indoor)
    h+=mkCard('c-temp','temp',NAWS_I18N.card_temperature+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_base+'</span>','Temperature_indoor',p.Temperature_indoor.value,p.Temperature_indoor.unit||'°C',p.Temperature_indoor.recorded_at,[]);

  // ── Innen-Luftfeuchtigkeit Basis (NAMain) ──────────────────────────────
  if(HIDE.indexOf('Humidity_indoor')<0&&p.Humidity_indoor)
    h+=mkCard('c-humid','humid',NAWS_I18N.card_humidity+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_base+'</span>','Humidity_indoor',p.Humidity_indoor.value,p.Humidity_indoor.unit||'%',p.Humidity_indoor.recorded_at,[]);

  // ── Regen (NAModule3) ─────────────────────────────────────────────────
  if(HIDE.indexOf('Rain')<0&&(p.Rain||p.sum_rain_1||p.sum_rain_24||p.rain_rolling_24h)){
    var rm=p.Rain||p.sum_rain_1||p.sum_rain_24||p.rain_rolling_24h,rs=[];
    if(p.sum_rain_1) rs.push({l:'1h', v:p.sum_rain_1.value, u:p.sum_rain_1.unit||'mm'});
    // Use our DB-computed rolling 24h; fall back to Netatmo sum_rain_24 (resets at midnight)
    var r24=p.rain_rolling_24h||p.sum_rain_24;
    if(r24&&r24!==rm) rs.push({l:'24h', v:r24.value, u:r24.unit||'mm'});
    h+=mkCard('c-rain','rain',NAWS_I18N.card_rain,'Rain',rm.value,rm.unit||'mm',rm.recorded_at,rs);
  }

  // ── Wind-Gauge (NAModule2) ────────────────────────────────────────────
  if(HIDE.indexOf('WindStrength')<0&&(p.WindStrength||p.GustStrength)){
    h+='<div class="naws-card c-wind" data-card="WindStrength">'
      +'<div class="naws-ico">'+ICO.wind+'</div>'
      +'<div class="naws-lbl">'+NAWS_I18N.card_wind_gusts+'</div>'
      +'<div id="'+WID+'-gauge" style="width:100%;display:flex;justify-content:center">'+gaugeSVG(wv,gv)+'</div>'
      +'<div class="naws-wvrow">'
      +'<div class="naws-wvblk"><div class="naws-wv-lbl">'+NAWS_I18N.card_wind+'</div><div class="naws-wv-num" id="'+WID+'-wv" style="color:var(--ink2)">'+esc(String(wv))+'</div><div class="naws-wv-unit">'+wu+'</div></div>'
      +'<div class="naws-wvblk"><div class="naws-wv-lbl">'+NAWS_I18N.card_gusts+'</div><div class="naws-wv-num" id="'+WID+'-gv" style="color:var(--muted)">'+esc(String(gv))+'</div><div class="naws-wv-unit">'+gu+'</div></div>'
      +'</div>';
    if(p.WindStrength&&p.WindStrength.recorded_at)
      h+='<div class="naws-time" style="text-align:center;margin-top:7px">'+fmt(p.WindStrength.recorded_at)+'</div>';
    h+='</div>';
  }

  // ── Windrichtung / Kompass (NAModule2) ────────────────────────────────
  if(HIDE.indexOf('WindAngle')<0&&p.WindAngle){
    h+='<div class="naws-card c-wind" data-card="WindAngle">'
      +'<div class="naws-ico"><svg viewBox="0 0 24 24" style="width:23px;height:23px;stroke:var(--ca,var(--ink));fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><circle cx="12" cy="12" r="10"/><polygon points="16.24,7.76 14.12,14.12 7.76,16.24 9.88,9.88" stroke="none" fill="var(--ca,var(--ink))"/></svg></div>'
      +'<div class="naws-lbl">'+NAWS_I18N.card_wind_dir+'</div>'
      +'<div class="naws-rose-wrap" id="'+WID+'-rose">'+ROSE+arrowSVG(wDeg)+'</div>'
      +'<div class="naws-rose-dir" id="'+WID+'-dir">'+Math.round(wDeg)+'° &nbsp;·&nbsp; '+cdir(wDeg)+'</div>';
    if(p.WindAngle.recorded_at) h+='<div class="naws-time" style="text-align:center;margin-top:5px">'+fmt(p.WindAngle.recorded_at)+'</div>';
    h+='</div>';
  }

  // ── NAModule4: je ein eigener Kachel-Block pro Sensor pro Modul ────────
  Object.keys(MODULE4_INFO).forEach(function(slug){
    var info=MODULE4_INFO[slug];
    var modLabel=esc(info.name);

    var kTemp='Temperature_'+slug, kHum='Humidity_'+slug, kCO2='CO2_'+slug, kNoise='Noise_'+slug;

    if(HIDE.indexOf(kTemp)<0&&p[kTemp])
      h+=mkCard('c-temp','temp',NAWS_I18N.card_temperature+'<span class="naws-lbl-badge">'+modLabel+'</span>',kTemp,p[kTemp].value,p[kTemp].unit||'°C',p[kTemp].recorded_at,[]);

    if(HIDE.indexOf(kHum)<0&&p[kHum])
      h+=mkCard('c-humid','humid',NAWS_I18N.card_humidity+'<span class="naws-lbl-badge">'+modLabel+'</span>',kHum,p[kHum].value,p[kHum].unit||'%',p[kHum].recorded_at,[]);

    if(HIDE.indexOf(kCO2)<0&&p[kCO2])
      h+=mkCard('c-co2','co2',NAWS_I18N.card_co2+'<span class="naws-lbl-badge">'+modLabel+'</span>',kCO2,p[kCO2].value,p[kCO2].unit||'ppm',p[kCO2].recorded_at,[]);

    if(HIDE.indexOf(kNoise)<0&&p[kNoise])
      h+=mkCard('c-noise','noise',NAWS_I18N.card_noise+'<span class="naws-lbl-badge">'+modLabel+'</span>',kNoise,p[kNoise].value,p[kNoise].unit||'dB',p[kNoise].recorded_at,[]);
  });

  h+='</div>';
  return h;
}

/* ── CARD ORDER ──────────────────────────
   buildLive() prints the cards in the order it always has. Rather than tear
   that apart, the grid is told where to put them: .naws-grid is a CSS grid,
   so an order property on each card decides the position and nothing else
   about the layout changes.

   A card whose id is not in the list keeps order 0 and therefore stays in
   front of the arranged ones — that is the visible signal that something new
   arrived and has never been placed. Hidden cards were never printed, so
   they leave no gap. */
function applyCardOrder(){
  if(!CARD_ORDER.length) return;
  document.querySelectorAll('#'+WID+' .naws-card[data-card]').forEach(function(card){
    var pos=CARD_ORDER.indexOf(card.dataset.card);
    if(pos>=0) card.style.order=pos+1;
  });
}

/* ── SOFT UPDATE ─────────────────────── */
function softUpdate(rows){
  var p=indexReadings(rows);
  document.querySelectorAll('#'+WID+' .naws-val[data-param]').forEach(function(el){
    var k=el.dataset.param; if(!k||!p[k]) return;
    var nv=String(p[k].value??'—');
    if(el.textContent!==nv){el.textContent=nv;el.classList.remove('naws-flash');void el.offsetWidth;el.classList.add('naws-flash');}
    var c=el.closest('.naws-card');if(c){var t=c.querySelector('.naws-time');if(t)t.textContent=fmt(p[k].recorded_at);}
  });
  var wv=p.WindStrength?parseFloat(p.WindStrength.value)||0:null;
  var gv=p.GustStrength?parseFloat(p.GustStrength.value)||0:null;
  var wDeg=p.WindAngle?parseFloat(p.WindAngle.value)||0:null;
  var gauge=document.getElementById(WID+'-gauge'); if(gauge&&wv!==null) gauge.innerHTML=gaugeSVG(wv,gv||0);
  var wvEl=document.getElementById(WID+'-wv'); if(wvEl&&wv!==null) wvEl.textContent=String(wv);
  var gvEl=document.getElementById(WID+'-gv'); if(gvEl&&gv!==null) gvEl.textContent=String(gv);
  var arr=document.getElementById(WID+'-arr'); if(arr&&wDeg!==null) arr.style.transform='rotate('+wDeg+'deg)';
  var dir=document.getElementById(WID+'-dir'); if(dir&&wDeg!==null) dir.innerHTML=Math.round(wDeg)+'° &nbsp;·&nbsp; '+cdir(wDeg);
}

// Chart.js plugin: fill canvas background to match card color
var canvasBgPlugin={
  id:'canvasBg',
  beforeDraw:function(chart){
    var ctx=chart.canvas.getContext('2d');
    ctx.save();
    ctx.globalCompositeOperation='destination-over';
    ctx.fillStyle='#ffffff';
    ctx.fillRect(0,0,chart.canvas.width,chart.canvas.height);
    ctx.restore();
  }
};
if(typeof Chart !== 'undefined' && Chart.register) Chart.register(canvasBgPlugin);

/* ── CHART.JS CONFIG ─────────────────── */
function nawsLiveFontSize(){ var w=window.innerWidth; return w<480?9:w<768?10:11; }

function chartOpts(unit, type){
  var fs = nawsLiveFontSize();
  return {
    responsive:true, maintainAspectRatio:true,
    animation:{duration:900,easing:'easeInOutQuart'},
    plugins:{
      legend:{display:false},
      tooltip:{
        backgroundColor:'rgba(45,82,82,.92)',
        titleColor:'#a0c8c8',bodyColor:'#fff',
        titleFont:{family:NAWS_FONT,size:fs+1},
        bodyFont:{family:NAWS_FONT,size:fs+3,weight:'bold'},
        padding:10,cornerRadius:8,displayColors:false,
        callbacks:{label:function(c){return (Math.round(c.parsed.y*10)/10)+' '+unit;}}
      }
    },
    scales:{
      x:{
        grid:{color:'rgba(218,240,240,.5)'},
        ticks:{color:'#7aa0a0',font:{family:NAWS_FONT,size:fs},maxRotation:0,maxTicksLimit:12}
      },
      y:{
        grid:{color:'rgba(218,240,240,.5)'},
        ticks:{
          color:'#7aa0a0',font:{family:NAWS_FONT,size:fs},
          callback:function(v){return Math.round(v*10)/10;}
        },
        title:{display:true,text:unit,color:'#a0b8b8',font:{family:NAWS_FONT,size:fs,weight:'600'}}
      }
    }
  };
}
function hexToRgba(hex, alpha){
  var r=0,g=0,b=0;
  if(hex.length===4){r=parseInt(hex[1]+hex[1],16);g=parseInt(hex[2]+hex[2],16);b=parseInt(hex[3]+hex[3],16);}
  else if(hex.length===7){r=parseInt(hex.substring(1,3),16);g=parseInt(hex.substring(3,5),16);b=parseInt(hex.substring(5,7),16);}
  return 'rgba('+r+','+g+','+b+','+alpha+')';
}
function colorToRgba(c, alpha){
  if(c.charAt(0)==='#') return hexToRgba(c, alpha);
  return c.replace('rgb(','rgba(').replace(')',', '+alpha+')');
}
function makeDataset(cfg, pts, canvasCtx){
  var c=cfg.color;
  var bg;
  if(cfg.type==='bar'){
    bg=colorToRgba(c, 0.45);
  } else if(canvasCtx){
    var grad=canvasCtx.createLinearGradient(0,0,0,canvasCtx.canvas.height||300);
    grad.addColorStop(0, colorToRgba(c, 0.28));
    grad.addColorStop(0.6, colorToRgba(c, 0.08));
    grad.addColorStop(1, colorToRgba(c, 0.01));
    bg=grad;
  } else {
    bg=colorToRgba(c, 0.08);
  }
  return {
    data:pts,
    borderColor:c, backgroundColor:bg,
    borderWidth:cfg.type==='bar'?1.5:2.5,
    pointRadius:0, pointHoverRadius:4,
    tension:0.35, fill:cfg.type!=='bar',
    borderRadius:cfg.type==='bar'?5:0,
  };
}

function renderChart(canvasId, cfg, labels, vals, animate){
  var el=document.getElementById(canvasId); if(!el) return;
  if(charts[canvasId]){charts[canvasId].destroy();delete charts[canvasId];}
  var ctx2d=el.getContext('2d');
  var opts=chartOpts(cfg.unit, cfg.type);
  if(!animate) opts.animation={duration:0};
  charts[canvasId]=new Chart(el,{
    type:cfg.type,
    data:{labels:labels, datasets:[makeDataset(cfg,vals,ctx2d)]},
    options:opts,
  });
}

/* ── LOAD CHARTS ─────────────────────── */
function chartCanvasId(key){ return WID+'-'+key.replace(/[^a-zA-Z0-9]/g,'-'); }

function loadCharts(){
  if(!CHART_CONFIGS||!CHART_CONFIGS.length) return;
  var now=Math.floor(Date.now()/1000);
  var dayStart=now-86400;

  CHART_CONFIGS.forEach(function(cfg){
    var params={action:'naws_get_chart_data',date_from:dayStart,date_to:now,parameter:[cfg.param],group_by:'hour'};
    if(cfg.module_id) params.module_id=cfg.module_id;
    post(params,function(r){
      if(!r||!r.success||!r.data||!r.data.datasets||!r.data.datasets.length) return;
      var ds=r.data.datasets[0]; if(!ds||!ds.data||!ds.data.length) return;
      var labels=ds.data.map(function(p){return hhmm(p.x);});
      var vals=ds.data.map(function(p){return p.y;});
      chartData[cfg.key]={cfg:cfg,labels:labels,vals:vals};
      renderChart(chartCanvasId(cfg.key), cfg, labels, vals, true);
      if(chartsEl) chartsEl.style.display='';
    });
  });
}

/* ── MODAL ───────────────────────────── */
var modal=document.getElementById(WID+'-modal');
var modalTitle=modal?modal.querySelector('.naws-modal-title'):null;
var modalCanvasId=WID+'-modal-canvas';

function openModal(cfgId, label){
  // cfgId is the canvas element id (WID-key-with-dashes), convert back to key
  var cfgKey=cfgId.replace(WID+'-','').replace(/-/g,'_');
  // Try exact match first, then try replacing dashes back to underscores
  var cd=chartData[cfgId]||chartData[cfgKey];
  if(!modal||!cd) return;
  modalTitle.textContent=label||cd.cfg.label;
  modal.style.display='flex';
  document.body.style.overflow='hidden';
  // destroy previous modal chart
  if(charts[modalCanvasId]){charts[modalCanvasId].destroy();delete charts[modalCanvasId];}
  // Need to re-get canvas after display:flex
  setTimeout(function(){
    var opts=chartOpts(cd.cfg.unit, cd.cfg.type);
    opts.animation={duration:600,easing:'easeInOutQuart'};
    opts.maintainAspectRatio=false;
    var mEl=document.getElementById(modalCanvasId); if(!mEl) return;
    mEl.style.height='340px';
    var mCtx=mEl.getContext('2d');
    charts[modalCanvasId]=new Chart(mEl,{
      type:cd.cfg.type,
      data:{labels:cd.labels, datasets:[makeDataset(cd.cfg, cd.vals, mCtx)]},
      options:opts,
    });
  },30);
}
function closeModal(){
  if(!modal) return;
  modal.style.display='none';
  document.body.style.overflow='';
  if(charts[modalCanvasId]){charts[modalCanvasId].destroy();delete charts[modalCanvasId];}
}

// Bind modal events
if(modal){
  modal.querySelector('.naws-modal-backdrop').addEventListener('click', closeModal);
  modal.querySelector('.naws-modal-close').addEventListener('click', closeModal);
  document.addEventListener('keydown',function(e){if(e.key==='Escape') closeModal();});
}
// Bind expand buttons (delegated – charts built after page load)
document.addEventListener('click',function(e){
  var btn=e.target.closest('.naws-chart-expand');
  if(!btn) return;
  var cid=btn.dataset.chartId; // e.g. "naws-live-1-ct"
  var cfgId=cid.replace(WID+'-',''); // "ct"
  openModal(cfgId, btn.dataset.label);
});
// Also click on card itself
document.addEventListener('click',function(e){
  var card=e.target.closest('.naws-chart-card');
  if(!card||e.target.closest('.naws-chart-expand')) return;
  var cid=card.dataset.chartId;
  var cfgId=cid.replace(WID+'-','');
  openModal(cfgId, card.dataset.chartLabel);
});




var _liveRetries = 0;
var _liveRetryMax = 3; // retry up to 3× with 5s intervals if first load returns empty

/* Since 1.7.0 the response is {readings:[…], weather_state:{…}}.
   Older cached responses were a bare array, so accept both shapes. */
function liveRows(d){ return Array.isArray(d) ? d : ((d && d.readings) || []); }

function applyWeatherIcon(d){
  var host = document.getElementById(WID+'-wx');
  if(!host) return;                      // icon switched off in the backend
  var wx = (d && !Array.isArray(d)) ? d.weather_state : null;
  if(!wx || !wx.markup){ host.innerHTML=''; return; }
  if(host.dataset.state === wx.state) return;   // unchanged, leave animations running
  host.dataset.state = wx.state;
  host.innerHTML = wx.markup;
}

function loadLive(){
  post({action:'naws_get_latest'},function(r){
    var rows = r && r.success ? liveRows(r.data) : [];
    if(r&&r.success) applyWeatherIcon(r.data);
    if(rows.length){
      _liveRetries = 0;
      var maxTs=rows.reduce(function(m,x){return x.recorded_at>m?x.recorded_at:m;},'');
      var tsEl=document.querySelector('#'+WID+' .naws-ts'); if(tsEl) tsEl.textContent=fmt(maxTs);
      var pulseEl=document.getElementById(WID+'-pulse');
      if(pulseEl){
        var ageMin=(Date.now()/1000 - parseInt(maxTs))/60;
        if(ageMin > 30){
          pulseEl.style.background='#e0a000';
          pulseEl.style.animation='none';
          pulseEl.title=NAWS_I18N.stale_data.replace('%d',Math.round(ageMin));
        } else {
          pulseEl.style.background='';
          pulseEl.style.animation='';
          pulseEl.title='';
        }
      }
      if(!built){
        liveEl.innerHTML=buildLive(rows);
        applyCardOrder();
        built=true;
        loadCharts();
      } else {
        softUpdate(rows);
      }
      // Schedule next normal refresh
      setTimeout(loadLive, RFSH);
    } else if(!built && _liveRetries < _liveRetryMax){
      // Data not yet available – retry quickly, don't start the normal RFSH loop yet
      _liveRetries++;
      setTimeout(loadLive, 5000);
    } else {
      if(!built){
        liveEl.innerHTML='<div class="naws-error">'+NAWS_I18N.no_live_data+'<br><small>'+NAWS_I18N.sync_inactive+'</small></div>';
        var pulseEl=document.getElementById(WID+'-pulse');
        if(pulseEl){ pulseEl.style.background='#e57373'; pulseEl.style.animation='none'; }
      }
      // Continue polling even after error
      setTimeout(loadLive, RFSH);
    }
  });
}
loadLive();

/* ── RESPONSIVE: update chart fonts on resize ── */
var _nawsLiveResizeTimer;
window.addEventListener('resize', function(){
  clearTimeout(_nawsLiveResizeTimer);
  _nawsLiveResizeTimer = setTimeout(function(){
    var fs = nawsLiveFontSize();
    Object.keys(charts).forEach(function(id){
      var ch = charts[id];
      if(!ch) return;
      if(ch.options.scales && ch.options.scales.x && ch.options.scales.x.ticks) ch.options.scales.x.ticks.font.size = fs;
      if(ch.options.scales && ch.options.scales.y && ch.options.scales.y.ticks) ch.options.scales.y.ticks.font.size = fs;
      if(ch.options.scales && ch.options.scales.y && ch.options.scales.y.title) ch.options.scales.y.title.font.size = fs;
      ch.update('none');
    });
  }, 250);
});
  }
})();
