<?php
session_start();

/* 🔥 SHOW ERRORS (for debugging) */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ── HELPERS ───────────────── */
function gcd($a, $b){
    $a = abs($a); $b = abs($b);
    while ($b) { $t = $b; $b = $a % $b; $a = $t; }
    return $a ?: 1;
}

function simplify($n, $d){
    $g = gcd($n, $d);
    return [$n/$g, $d/$g];
}

function divFrac($a, $b){
    return simplify($a[0]*$b[1], $a[1]*$b[0]);
}

/* ── QUESTION BANK ───────────────── */
$questionBank = [
    'easy' => [
        ['a'=>[1,2],'b'=>[1,4]],
        ['a'=>[1,3],'b'=>[1,6]],
        ['a'=>[1,2],'b'=>[1,6]],
        ['a'=>[2,3],'b'=>[1,3]],
    ],
    'medium' => [
        ['a'=>[3,4],'b'=>[3,8]],
        ['a'=>[2,3],'b'=>[1,6]],
    ],
    'hard' => [
        ['a'=>[5,3],'b'=>[2,3]],
        ['a'=>[7,4],'b'=>[7,8]],
    ],
];

/* ── HANDLE REQUEST ───────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!$input) {
        echo json_encode(['error'=>'Invalid JSON']);
        exit;
    }

    $action = $input['action'] ?? '';

    /* ── START ───────────────── */
    if ($action === 'start') {
        $level = $input['level'] ?? 'easy';
        $qs = $questionBank[$level];
        shuffle($qs);

        $_SESSION['game'] = [
            'questions'=>$qs,
            'qIdx'=>0,
            'score'=>0,
            'correct'=>0,
            'totalQ'=>count($qs),
        ];

        echo json_encode(['ok'=>true,'totalQ'=>count($qs)]);
        exit;
    }

    /* ── QUESTION ───────────────── */
if ($action === 'getQuestion') {
    $g = $_SESSION['game'];

    if ($g['qIdx'] >= $g['totalQ']) {
        echo json_encode(['done'=>true]);
        exit;
    }

    $q = $g['questions'][$g['qIdx']];
    [$rn,$rd] = divFrac($q['a'],$q['b']);

    // ✅ generate wrong answers
    $choices = [
        ['n'=>$rn,'d'=>$rd],
        ['n'=>$rn+1,'d'=>$rd],
        ['n'=>$rn,'d'=>$rd+1],
        ['n'=>$rd,'d'=>$rn]
    ];

    shuffle($choices);

    echo json_encode([
        'done'=>false,
        'q'=>$q,
        'answer'=>['n'=>$rn,'d'=>$rd],
        'choices'=>$choices,   // 🔥 THIS WAS MISSING
        'qIdx'=>$g['qIdx'],
        'totalQ'=>$g['totalQ']
    ]);
    exit;
}

    /* ── CHECK ANSWER ───────────────── */
if ($action === 'checkAnswer') {
    $g = &$_SESSION['game'];

    $cn = $input['cn'];
    $cd = $input['cd'];

    $q = $g['questions'][$g['qIdx']];
    [$rn,$rd] = divFrac($q['a'],$q['b']);

    [$sn,$sd] = simplify($cn,$cd);

    $ok = ($sn == $rn && $sd == $rd);

    if ($ok) {
        $g['score'] += 10;
        $g['correct']++;
    }

    $g['qIdx']++;

    echo json_encode([
        'ok'=>$ok,
        'answer'=>['n'=>$rn,'d'=>$rd],
        'score'=>$g['score'],
        'streak'=>0,        // ✅ prevent JS crash
        'xp'=>0,            // ✅ prevent JS crash
        'xpLevel'=>1,       // ✅ prevent JS crash
        'done'=>($g['qIdx'] >= $g['totalQ'])
    ]);
    exit;
}

    /* ── RESULTS + SAVE ───────────────── */
    if ($action === 'getResults') {
        $g = $_SESSION['game'];

        $pct = round(($g['correct']/$g['totalQ'])*100);

        /* ✅ GUARANTEED FILE SAVE */
        $file = __DIR__ . "/scores.txt";

        if (!file_exists($file)) {
            file_put_contents($file, "");
        }

        $line = date('Y-m-d H:i:s') .
                " | Score: ".$g['score'].
                " | Accuracy: ".$pct."%\n";

        file_put_contents($file, $line, FILE_APPEND);

        echo json_encode([
            'score'=>$g['score'],
            'accuracy'=>$pct,
            'saved'=>true
        ]);
        exit;
    }

    echo json_encode(['error'=>'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fraction Division — Visual Adventure</title>
<link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#0a0818;--bg2:#1a1040;--bg3:#0d0d2b;
  --y:#f9ca24;--o:#f0932b;--g:#00d2a0;--p:#e84393;--c:#22d3ee;--v:#a78bfa;
  --tile:rgba(255,255,255,0.06);--border:rgba(255,255,255,0.11);
  --text:#f0eeff;--sub:#9490bf;--r:18px;--sh:0 8px 40px rgba(0,0,0,0.55);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{min-height:100vh;background:radial-gradient(ellipse at 20% 20%,#1a0a3a 0%,#0a0818 50%,#0d1a2a 100%);
  font-family:'Nunito',sans-serif;color:var(--text);overflow-x:hidden}
#stars{position:fixed;inset:0;pointer-events:none;z-index:0}
.star{position:absolute;border-radius:50%;background:#fff;animation:twk var(--d,3s) ease-in-out infinite alternate}
@keyframes twk{from{opacity:var(--o,.5);transform:scale(1)}to{opacity:.05;transform:scale(.5)}}
.orb{position:fixed;border-radius:50%;pointer-events:none;z-index:0;filter:blur(90px);opacity:.14;animation:orbf 12s ease-in-out infinite alternate}
.o1{width:500px;height:500px;background:var(--p);top:-150px;left:-150px;animation-delay:0s}
.o2{width:400px;height:400px;background:var(--c);bottom:-100px;right:-100px;animation-delay:-6s}
.o3{width:300px;height:300px;background:var(--v);top:35%;left:55%;animation-delay:-3s}
@keyframes orbf{from{transform:translate(0,0) scale(1)}to{transform:translate(40px,50px) scale(1.15)}}
#app{position:relative;z-index:1;max-width:860px;margin:0 auto;padding:20px 14px 60px}
.hdr{text-align:center;padding:26px 0 14px;animation:fdown .7s ease both}
.hdr-title{font-family:'Fredoka One',cursive;font-size:clamp(1.9rem,5.5vw,3rem);letter-spacing:1px;
  background:linear-gradient(90deg,var(--y),var(--o),var(--p),var(--c));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  filter:drop-shadow(0 0 22px rgba(249,202,36,.38));line-height:1.1}
.hdr-sub{margin-top:5px;color:var(--sub);font-size:.88rem;font-weight:700;letter-spacing:.5px}
.sbar{display:flex;gap:9px;justify-content:center;align-items:center;margin:10px 0 16px;flex-wrap:wrap}
.pill{background:var(--tile);border:1.5px solid var(--border);border-radius:40px;padding:6px 16px;
  font-weight:700;font-size:.85rem;display:flex;align-items:center;gap:6px;backdrop-filter:blur(12px)}
.pill.hl{border-color:var(--y);color:var(--y)}.pill.st{border-color:var(--o);color:var(--o)}.pill.xpp{border-color:var(--c);color:var(--c)}
.xpw{height:5px;background:rgba(255,255,255,0.07);border-radius:99px;margin:0 0 16px;overflow:hidden}
.xpb{height:100%;background:linear-gradient(90deg,var(--c),var(--v));border-radius:99px;
  transition:width .6s cubic-bezier(.34,1.2,.64,1);box-shadow:0 0 10px rgba(34,211,238,.6)}
.screen{display:none}.screen.active{display:block;animation:fup .45s ease both}
@keyframes fup{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
@keyframes fdown{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
.card{background:var(--tile);border:1.5px solid var(--border);border-radius:var(--r);
  padding:22px;backdrop-filter:blur(16px);box-shadow:var(--sh);margin-bottom:16px;position:relative;overflow:hidden}
.card::before{content:'';position:absolute;inset:0;border-radius:var(--r);
  background:linear-gradient(135deg,rgba(255,255,255,.04) 0%,transparent 60%);pointer-events:none}
.intro-hero{text-align:center;padding:6px 0 16px}
.big-em{font-size:3.8rem;display:block;animation:bob 1.6s ease-in-out infinite alternate;
  filter:drop-shadow(0 0 22px rgba(249,202,36,.55))}
@keyframes bob{from{transform:translateY(0)}to{transform:translateY(-13px)}}
.intro-hero h2{font-family:'Fredoka One',cursive;font-size:1.55rem;margin:12px 0 5px;color:var(--y)}
.intro-hero p{color:var(--sub);font-size:.93rem;line-height:1.75;max-width:490px;margin:0 auto 16px}
.cgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:11px;margin:14px 0}
.cc{background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.09);border-radius:13px;
  padding:13px;text-align:center;transition:transform .25s,border-color .25s,box-shadow .25s;cursor:default}
.cc:hover{transform:translateY(-5px);border-color:var(--c);box-shadow:0 6px 22px rgba(34,211,238,.18)}
.cc .ci{font-size:1.8rem;display:block;margin-bottom:6px}
.cc h3{font-size:.85rem;font-weight:800;color:var(--c)}.cc p{font-size:.75rem;color:var(--sub);margin-top:3px}
.lgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin:12px 0}
.lb{background:rgba(255,255,255,.04);border:2px solid rgba(255,255,255,.09);border-radius:13px;
  padding:13px 7px;cursor:pointer;text-align:center;transition:all .2s;color:var(--text);font-family:'Nunito',sans-serif}
.lb .li{font-size:1.6rem;display:block;margin-bottom:4px}.lb .ln{font-weight:800;font-size:.88rem}
.lb .ld{font-size:.7rem;color:var(--sub);margin-top:2px}
.lb:hover,.lb.sel{border-color:var(--y);background:rgba(249,202,36,.09);transform:translateY(-3px);box-shadow:0 5px 18px rgba(249,202,36,.2)}
.btn{display:inline-flex;align-items:center;gap:7px;font-family:'Fredoka One',cursive;
  font-size:1rem;letter-spacing:.5px;padding:12px 28px;border:none;border-radius:50px;
  cursor:pointer;transition:transform .18s,box-shadow .18s;position:relative;overflow:hidden}
.btn::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,.14);opacity:0;transition:opacity .2s}
.btn:hover::after{opacity:1}.btn:active{transform:scale(.95)!important}
.btn-p{background:linear-gradient(135deg,var(--y),var(--o));color:#1a1000;box-shadow:0 4px 20px rgba(249,202,36,.35)}
.btn-p:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(249,202,36,.5)}
.btn-s{background:linear-gradient(135deg,var(--c),#2563eb);color:#fff;box-shadow:0 4px 20px rgba(34,211,238,.3)}
.btn-s:hover{transform:translateY(-3px);box-shadow:0 8px 26px rgba(34,211,238,.45)}
.btn-g{background:var(--tile);border:1.5px solid var(--border);color:var(--sub);backdrop-filter:blur(8px)}
.btn-g:hover{transform:translateY(-2px);border-color:var(--c);color:var(--c)}
.btn-c{text-align:center;margin-top:9px}
.ripple{position:absolute;border-radius:50%;background:rgba(255,255,255,.32);
  transform:scale(0);animation:rip .55s linear;pointer-events:none}
@keyframes rip{to{transform:scale(4);opacity:0}}
.qlabel{font-family:'Fredoka One',cursive;font-size:.85rem;color:var(--sub);
  text-transform:uppercase;letter-spacing:1.2px;margin-bottom:5px}
.qmain{display:flex;align-items:center;justify-content:center;gap:13px;margin:5px 0 16px;flex-wrap:wrap}
.fd{display:flex;flex-direction:column;align-items:center;gap:2px}
.fd .fn{font-family:'Fredoka One',cursive;font-size:clamp(1.7rem,5vw,2.6rem);line-height:1;
  color:var(--y);text-shadow:0 0 18px rgba(249,202,36,.5)}
.fd .fl{width:100%;min-width:36px;height:3px;background:var(--y);border-radius:2px}
.fd .fd2{font-family:'Fredoka One',cursive;font-size:clamp(1.7rem,5vw,2.6rem);line-height:1;
  color:var(--o);text-shadow:0 0 18px rgba(240,147,43,.5)}
.op{font-family:'Fredoka One',cursive;font-size:clamp(1.4rem,4vw,2.2rem);
  color:var(--p);text-shadow:0 0 14px rgba(232,67,147,.5);align-self:center}
.vsec{display:grid;grid-template-columns:1fr 42px 1fr;gap:7px;align-items:start;margin-bottom:16px}
@media(max-width:560px){.vsec{grid-template-columns:1fr}.arw{transform:rotate(90deg);margin:0 auto}}
.vp{background:rgba(255,255,255,.032);border:1.5px solid rgba(255,255,255,.075);border-radius:12px;padding:13px}
.vpl{font-size:.7rem;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;
  color:var(--sub);margin-bottom:8px;text-align:center}
.arw{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:14px 0;gap:4px;color:var(--c);font-size:.65rem;text-align:center}
.pwrap{display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
svg.psv{display:block;filter:drop-shadow(0 3px 9px rgba(0,0,0,.4));transition:transform .3s}
svg.psv:hover{transform:scale(1.07)}
.ps{transition:fill .4s}.ps.sh{animation:spop .4s ease both}
@keyframes spop{0%{transform:scale(.6);opacity:0}60%{transform:scale(1.1)}100%{transform:scale(1);opacity:1}}
.nlw{overflow-x:auto;padding:5px 0}.nls{display:block;min-width:210px;width:100%}
.srow{display:flex;align-items:flex-start;gap:10px;padding:8px 0;
  border-bottom:1px solid rgba(255,255,255,.052);animation:fup .4s ease both}
.srow:last-child{border-bottom:none}
.snum{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-family:'Fredoka One',cursive;font-size:.82rem;flex-shrink:0;margin-top:1px}
.stxt{font-size:.84rem;line-height:1.6;color:var(--sub)}.stxt strong{color:var(--text)}
.cgr{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:4px}
.cb{background:rgba(255,255,255,.04);border:2px solid rgba(255,255,255,.09);border-radius:13px;
  padding:15px 9px;cursor:pointer;text-align:center;transition:all .2s;
  font-family:'Nunito',sans-serif;color:var(--text);position:relative;overflow:hidden}
.cb:hover:not(:disabled){border-color:var(--c);background:rgba(34,211,238,.09);transform:translateY(-3px)}
.cb:disabled{cursor:not-allowed}
.ct{font-family:'Fredoka One',cursive;font-size:1.4rem;display:block;line-height:1}
.cl{width:42%;height:2.5px;background:currentColor;margin:4px auto;border-radius:2px}
.cb2{font-family:'Fredoka One',cursive;font-size:1.4rem;display:block;line-height:1}
.cb.cor{border-color:var(--g)!important;background:rgba(0,210,160,.15)!important;animation:cpulse .5s ease}
.cb.wrg{border-color:var(--p)!important;background:rgba(232,67,147,.1)!important;animation:shk .4s ease}
@keyframes cpulse{0%{transform:scale(1)}40%{transform:scale(1.07)}100%{transform:scale(1)}}
@keyframes shk{0%,100%{transform:translateX(0)}25%{transform:translateX(-8px)}75%{transform:translateX(8px)}}
.pgwrap{height:7px;background:rgba(255,255,255,.07);border-radius:99px;margin-bottom:16px;overflow:hidden}
.pgbar{height:100%;background:linear-gradient(90deg,var(--y),var(--o));border-radius:99px;
  transition:width .5s cubic-bezier(.34,1.2,.64,1);box-shadow:0 0 10px rgba(249,202,36,.5)}
.toast{position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(80px);
  padding:11px 24px;border-radius:50px;font-family:'Fredoka One',cursive;font-size:1.05rem;
  letter-spacing:.5px;z-index:999;pointer-events:none;
  transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .3s;
  opacity:0;white-space:nowrap;box-shadow:0 6px 28px rgba(0,0,0,.5)}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.toast.cor{background:linear-gradient(90deg,var(--g),#34d399);color:#002a1a}
.toast.wrg{background:linear-gradient(90deg,var(--p),var(--o));color:#fff}
.toast.lvl{background:linear-gradient(90deg,var(--v),var(--c));color:#fff}
.hbtn{background:none;border:none;cursor:pointer;color:var(--c);font-family:'Nunito',sans-serif;
  font-size:.8rem;font-weight:700;display:flex;align-items:center;gap:5px;padding:5px 0;transition:color .2s}
.hbtn:hover{color:var(--y)}
.hbox{background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.2);border-radius:10px;
  padding:10px 12px;font-size:.83rem;color:var(--c);line-height:1.65;margin-top:6px;display:none}
.hbox.open{display:block;animation:fup .3s ease}
.php-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(119,82,163,0.18);
  border:1.5px solid rgba(119,82,163,0.4);border-radius:30px;padding:3px 12px;
  font-size:.72rem;font-weight:800;color:#b48ffa;letter-spacing:.5px;margin-top:4px}
#mascot{position:fixed;bottom:22px;right:20px;z-index:500;pointer-events:none}
#mascot svg{display:block;animation:msc 2.5s ease-in-out infinite alternate;
  filter:drop-shadow(0 4px 14px rgba(167,139,250,.5))}
@keyframes msc{from{transform:translateY(0) rotate(-3deg)}to{transform:translateY(-10px) rotate(3deg)}}
#mbubble{position:absolute;bottom:82px;right:0;background:rgba(15,10,45,.94);
  border:1.5px solid var(--v);border-radius:13px;padding:9px 13px;
  font-size:.78rem;color:var(--text);max-width:185px;white-space:normal;
  box-shadow:0 4px 18px rgba(0,0,0,.5);line-height:1.55;
  opacity:0;transform:translateY(8px);transition:opacity .3s,transform .3s;pointer-events:none}
#mbubble::after{content:'';position:absolute;bottom:-7px;right:24px;border:7px solid transparent;
  border-top-color:var(--v);border-bottom:none}
#mbubble.show{opacity:1;transform:translateY(0)}
#lvlov{position:fixed;inset:0;z-index:800;display:flex;align-items:center;justify-content:center;
  background:rgba(8,6,20,.78);backdrop-filter:blur(7px);opacity:0;pointer-events:none;transition:opacity .3s}
#lvlov.show{opacity:1;pointer-events:auto}
.lvbox{background:linear-gradient(135deg,rgba(28,18,65,.96),rgba(12,10,36,.96));
  border:2px solid var(--v);border-radius:22px;padding:34px 42px;text-align:center;
  box-shadow:0 0 60px rgba(167,139,250,.4);animation:fup .4s ease}
.lvbox h2{font-family:'Fredoka One',cursive;font-size:1.9rem;color:var(--v);margin:10px 0 5px}
.lvbox p{color:var(--sub);font-size:.9rem}
.lvem{font-size:3.8rem;display:block;animation:lvspin .9s ease both}
@keyframes lvspin{from{transform:scale(0) rotate(-180deg)}to{transform:scale(1) rotate(0)}}
.res-c{text-align:center}
.res-em{font-size:4.8rem;display:block;margin:5px 0;animation:bob 1.3s ease-in-out infinite alternate}
.res-sc{font-family:'Fredoka One',cursive;font-size:2.7rem;
  background:linear-gradient(90deg,var(--y),var(--o));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.rbd{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin:18px 0}
.rbi{background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.09);
  border-radius:12px;padding:13px 7px;text-align:center}
.rbv{font-family:'Fredoka One',cursive;font-size:1.55rem;color:var(--y)}
.rbl{font-size:.68rem;color:var(--sub);margin-top:3px;font-weight:700;text-transform:uppercase;letter-spacing:.8px}
.ract{display:flex;gap:9px;justify-content:center;flex-wrap:wrap;margin-top:16px}
#cc{position:fixed;inset:0;pointer-events:none;z-index:900}
#sndBtn{position:fixed;top:16px;right:16px;z-index:600;background:var(--tile);
  border:1.5px solid var(--border);border-radius:50%;width:40px;height:40px;
  display:flex;align-items:center;justify-content:center;cursor:pointer;
  backdrop-filter:blur(12px);font-size:1.1rem;transition:all .2s}
#sndBtn:hover{border-color:var(--c);transform:scale(1.1)}
@media(max-width:500px){
  .cgr{grid-template-columns:1fr 1fr}.lgrid{grid-template-columns:1fr}
  .rbd{grid-template-columns:1fr 1fr}.card{padding:15px}
  #mascot{bottom:14px;right:12px}
}
</style>
</head>
<body>
<div id="stars"></div>
<div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div>
<canvas id="cc"></canvas>
<button id="sndBtn" onclick="toggleSound()" title="Toggle Sound">🔊</button>

<!-- Mascot -->
<div id="mascot">
  <div id="mbubble"></div>
  <svg width="60" height="60" viewBox="0 0 64 64">
    <circle cx="32" cy="32" r="30" fill="url(#mg)"/>
    <defs><radialGradient id="mg" cx="40%" cy="35%"><stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#6d28d9"/></radialGradient></defs>
    <circle cx="22" cy="28" r="5" fill="white"/><circle cx="42" cy="28" r="5" fill="white"/>
    <circle cx="23" cy="29" r="2.5" fill="#1a0a3a"/><circle cx="43" cy="29" r="2.5" fill="#1a0a3a"/>
    <path id="mmouth" d="M22 42 Q32 50 42 42" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
    <path d="M14 18 Q8 10 16 12" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round"/>
    <path d="M50 18 Q56 10 48 12" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round"/>
  </svg>
</div>

<!-- Level-up overlay -->
<div id="lvlov">
  <div class="lvbox">
    <span class="lvem" id="lvem">⭐</span>
    <h2 id="lvtit">Level Up!</h2>
    <p id="lvdesc">You've reached a new level!</p>
  </div>
</div>

<div id="app">
  <header class="hdr">
    <h1 class="hdr-title">🍕 Fraction Division</h1>
    <p class="hdr-sub">Visual Adventure · Learn by Seeing · Master by Doing</p>
    <div style="text-align:center"><span class="php-badge">⚙️ PHP Powered Backend</span></div>
  </header>

  <div class="sbar" id="sbar" style="display:none">
    <div class="pill hl">⭐ <span id="sv">0</span> pts</div>
    <div class="pill st">🔥 Streak <span id="stv">0</span></div>
    <div class="pill xpp">⚡ XP <span id="xpv">0</span></div>
    <div class="pill">📍 Q<span id="qn">1</span>/<span id="qt">8</span></div>
  </div>
  <div class="xpw" id="xpw" style="display:none"><div class="xpb" id="xpb" style="width:0%"></div></div>

  <!-- INTRO -->
  <div class="screen active" id="introScreen">
    <div class="card">
      <div class="intro-hero">
        <span class="big-em">÷</span>
        <h2>What is Fraction Division?</h2>
        <p>Dividing fractions means: <strong>"How many times does one fraction fit into another?"</strong><br>
          We use <em>Keep · Change · Flip</em> — and vivid pictures make it click instantly!</p>
      </div>
      <div class="cgrid">
        <div class="cc"><span class="ci">🍕</span><h3>Pizza Model</h3><p>Shade slices, count groups visually</p></div>
        <div class="cc"><span class="ci">📏</span><h3>Number Line</h3><p>Hop along to feel every step</p></div>
        <div class="cc"><span class="ci">🔄</span><h3>Keep·Change·Flip</h3><p>Colour-coded steps always shown</p></div>
      </div>
    </div>
    <div class="card">
      <div class="qlabel">Choose difficulty</div>
      <div class="lgrid">
        <button class="lb sel" data-lv="easy" onclick="selLv('easy',this)">
          <span class="li">🌱</span><div class="ln">Easy</div><div class="ld">Unit fractions</div></button>
        <button class="lb" data-lv="medium" onclick="selLv('medium',this)">
          <span class="li">🔥</span><div class="ln">Medium</div><div class="ld">Proper fractions</div></button>
        <button class="lb" data-lv="hard" onclick="selLv('hard',this)">
          <span class="li">⚡</span><div class="ln">Hard</div><div class="ld">Mixed &amp; improper</div></button>
      </div>
    </div>
    <div class="btn-c"><button class="btn btn-p" onclick="startGame()">🚀 Start Adventure</button></div>
  </div>

  <!-- GAME -->
  <div class="screen" id="gameScreen">
    <div class="pgwrap"><div class="pgbar" id="pgbar" style="width:0%"></div></div>
    <div class="card">
      <div class="qlabel">What is the answer?</div>
      <div class="qmain" id="qdisp"></div>
      <div class="vsec" id="vsec"></div>
      <div class="card" id="stepPanel" style="margin-bottom:12px;padding:12px 14px"></div>
      <button class="hbtn" onclick="toggleHint()" id="hbtn">💡 Show Hint</button>
      <div class="hbox" id="hbox"></div>
    </div>
    <div class="card">
      <div class="qlabel">Choose the correct answer</div>
      <div class="cgr" id="cgr"></div>
    </div>
  </div>

  <!-- RESULTS -->
  <div class="screen" id="resScreen">
    <div class="card res-c">
      <span class="res-em" id="rem">🏆</span>
      <div class="res-sc" id="rsc">0 pts</div>
      <p style="color:var(--sub);margin:7px 0 3px;font-size:.97rem" id="rmsg"></p>
      <div class="rbd">
        <div class="rbi"><div class="rbv" id="rb1">0</div><div class="rbl">Score</div></div>
        <div class="rbi"><div class="rbv" id="rb2">0</div><div class="rbl">Best Streak</div></div>
        <div class="rbi"><div class="rbv" id="rb3">0%</div><div class="rbl">Accuracy</div></div>
      </div>
      <div class="ract">
        <button class="btn btn-p" onclick="restartGame()">🔁 Play Again</button>
        <button class="btn btn-g" onclick="goHome()">🏠 Home</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ── STARS ───────────────────────────────────────────────────── */
(()=>{
  const s=document.getElementById('stars');
  for(let i=0;i<130;i++){
    const d=document.createElement('div');d.className='star';
    const sz=Math.random()*2.5+.4;
    d.style.cssText=`width:${sz}px;height:${sz}px;top:${Math.random()*100}%;left:${Math.random()*100}%;--d:${(Math.random()*3+2).toFixed(1)}s;--o:${(Math.random()*.5+.15).toFixed(2)};animation-delay:${(Math.random()*5).toFixed(1)}s`;
    s.appendChild(d);
  }
})();

/* ── AUDIO ───────────────────────────────────────────────────── */
let audioCtx=null,soundOn=true;
function getCtx(){if(!audioCtx)audioCtx=new(window.AudioContext||window.webkitAudioContext)();return audioCtx}
function tone(freq,type,dur,vol=0.25,delay=0){
  if(!soundOn)return;
  try{
    const ac=getCtx(),o=ac.createOscillator(),g=ac.createGain();
    o.connect(g);g.connect(ac.destination);
    o.type=type;o.frequency.setValueAtTime(freq,ac.currentTime+delay);
    g.gain.setValueAtTime(0,ac.currentTime+delay);
    g.gain.linearRampToValueAtTime(vol,ac.currentTime+delay+.01);
    g.gain.exponentialRampToValueAtTime(.001,ac.currentTime+delay+dur);
    o.start(ac.currentTime+delay);o.stop(ac.currentTime+delay+dur);
  }catch(e){}
}
function sndOk(){[0,.1,.2].forEach((d,i)=>tone([523,659,784][i],'sine',.18,.22,d))}
function sndBad(){tone(220,'sawtooth',.22,.18,0);tone(180,'sawtooth',.22,.12,.12)}
function sndClick(){tone(880,'sine',.07,.12,0)}
function sndLvl(){[523,659,784,1047].forEach((n,i)=>tone(n,'sine',.22,.2,i*.12))}
function sndNext(){tone(440,'sine',.1,.1,0);tone(550,'sine',.1,.08,.08)}
function toggleSound(){soundOn=!soundOn;document.getElementById('sndBtn').textContent=soundOn?'🔊':'🔇';sndClick()}

/* ── GCD (JS copy for client-side use) ──────────────────────── */
function gcd(a,b){a=Math.abs(a);b=Math.abs(b);while(b){let t=b;b=a%b;a=t;}return a||1;}
function simp(n,d){const g=gcd(n,d);return[n/g,d/g];}

/* ── STATE ───────────────────────────────────────────────────── */
let level='easy',answered=false,currentQ=null,currentAnswer=null,totalQ=0,qIdx=0;

/* ── PHP API CALL ────────────────────────────────────────────── */
async function api(payload){
  const r=await fetch('index.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)
  });
  return r.json();
}

/* ── HELPERS ─────────────────────────────────────────────────── */
function sc(col,t){return`<span style="color:${col};font-weight:800">${t}</span>`}
function ripple(btn,e){
  const rp=document.createElement('span');rp.className='ripple';
  const rect=btn.getBoundingClientRect(),sz=Math.max(rect.width,rect.height);
  rp.style.cssText=`width:${sz}px;height:${sz}px;left:${e.clientX-rect.left-sz/2}px;top:${e.clientY-rect.top-sz/2}px`;
  btn.appendChild(rp);setTimeout(()=>rp.remove(),600);
}

const TIPS=["Remember: Keep · Change · Flip! 🔄","Flip the second fraction before multiplying!",
  "Division = multiplication in disguise! 🕵️","Count how many groups fit in — pizza helps!",
  "You're amazing! Keep going! 🌟","The number line shows each step visually!","Simplify using GCF for cleaner answers!"];

/* ── LEVEL SELECT ────────────────────────────────────────────── */
function selLv(lv,el){
  level=lv;sndClick();
  document.querySelectorAll('.lb').forEach(b=>b.classList.remove('sel'));
  el.classList.add('sel');
}

/* ── GAME FLOW ───────────────────────────────────────────────── */
async function startGame(){
  sndClick();
  const res=await api({action:'start',level});
  if(!res.ok)return;
  totalQ=res.totalQ;
  qIdx=0;
  _xpLevel=1;
  document.getElementById('sbar').style.display='flex';
  document.getElementById('xpw').style.display='block';
  document.getElementById('qt').textContent=totalQ;
  showMascot("Let's go! You've got this! 🚀",3500);
  showScreen('gameScreen');
  await loadQuestion();
}

function showScreen(id){
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  window.scrollTo({top:0,behavior:'smooth'});
}

async function loadQuestion(){
  answered=false;
  const data=await api({action:'getQuestion'});
  if(data.done){showResults();return;}
  currentQ=data.q;
  currentAnswer=data.answer;
  qIdx=data.qIdx;
  const[an,ad]=data.q.a,[bn,bd]=data.q.b;
  const rn=data.answer.n,rd=data.answer.d;

  updateBar(data.score,data.streak,data.xp,qIdx+1);
  document.getElementById('pgbar').style.width=`${(qIdx/totalQ)*100}%`;
  document.getElementById('qdisp').innerHTML=`
    <div class="fd"><span class="fn">${an}</span><div class="fl"></div><span class="fd2">${ad}</span></div>
    <div class="op">÷</div>
    <div class="fd"><span class="fn">${bn}</span><div class="fl"></div><span class="fd2">${bd}</span></div>
    <div class="op">=</div>
    <div class="op" style="color:var(--sub)">?</div>`;
  buildVisuals(an,ad,bn,bd,rn,rd);
  buildSteps(an,ad,bn,bd,rn,rd);
  buildChoices(data.choices,rn,rd,an,ad,bn,bd);
  document.getElementById('hbox').classList.remove('open');
  document.getElementById('hbtn').textContent='💡 Show Hint';
  document.getElementById('hbox').innerHTML=`<strong>Keep·Change·Flip:</strong> ${an}/${ad} ÷ ${bn}/${bd} = ${an}/${ad} × ${bd}/${bn} = ${an*bd}/${ad*bn} = <strong>${rn}/${rd}</strong>`;
  if(qIdx%3===0) showMascot(TIPS[Math.floor(Math.random()*TIPS.length)],3000);
}

/* ── PIZZA ───────────────────────────────────────────────────── */
function pizza(n,d,size=108,anim=true){
  const r=size*.45,cx=size/2,cy=size/2,ang=(2*Math.PI)/d;
  const cols=['#f9ca24','#f0932b','#00d2a0','#e84393','#22d3ee','#a78bfa','#fd79a8','#fdcb6e'];
  let p='';
  for(let i=0;i<d;i++){
    const s=-Math.PI/2+i*ang,e=s+ang;
    const x1=cx+r*Math.cos(s),y1=cy+r*Math.sin(s),x2=cx+r*Math.cos(e),y2=cy+r*Math.sin(e);
    const lg=ang>Math.PI?1:0,sh=i<n;
    const cls=sh&&anim?`class="ps sh" style="animation-delay:${i*.06}s"`:`class="ps"`;
    p+=`<path ${cls} d="M${cx},${cy} L${x1.toFixed(1)},${y1.toFixed(1)} A${r},${r} 0 ${lg},1 ${x2.toFixed(1)},${y2.toFixed(1)} Z" fill="${sh?cols[i%cols.length]:'rgba(255,255,255,0.04)'}" stroke="rgba(255,255,255,0.13)" stroke-width="1.5"/>`;
  }
  p+=`<circle cx="${cx}" cy="${cy}" r="${r+1}" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="1"/>`;
  return`<svg class="psv" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">${p}</svg>`;
}
function miniPizza(n,d,col,partial){
  const sz=58,r=22,cx=29,cy=29,ang=(2*Math.PI)/d;let p='';
  for(let i=0;i<d;i++){
    const s=-Math.PI/2+i*ang,e=s+ang;
    const x1=cx+r*Math.cos(s),y1=cy+r*Math.sin(s),x2=cx+r*Math.cos(e),y2=cy+r*Math.sin(e);
    const lg=ang>Math.PI?1:0,sh=i<n;
    p+=`<path d="M${cx},${cy} L${x1.toFixed(1)},${y1.toFixed(1)} A${r},${r} 0 ${lg},1 ${x2.toFixed(1)},${y2.toFixed(1)} Z" fill="${sh?col:'rgba(255,255,255,0.04)'}" stroke="rgba(255,255,255,0.1)" stroke-width="1.2" ${partial&&sh?'opacity=".55"':''}/>`;
  }
  return`<svg class="psv" width="${sz}" height="${sz}" viewBox="0 0 ${sz} ${sz}">${p}</svg>`;
}

function buildVisuals(an,ad,bn,bd,rn,rd){
  const sec=document.getElementById('vsec');
  const total=rn/rd,whole=Math.floor(total),rem=rn-whole*rd;
  const gc=['#22d3ee','#a78bfa','#00d2a0','#f9ca24','#e84393','#f0932b'];
  let ghtml='<div class="pwrap">';
  for(let g=0;g<whole;g++){
    ghtml+=`<div style="text-align:center">${miniPizza(bn,bd,gc[g%gc.length],false)}
      <div style="font-size:.68rem;color:${gc[g%gc.length]};font-weight:700;margin-top:2px">Grp ${g+1}</div></div>`;
  }
  if(rem>0){
    const pn=Math.max(1,Math.round((rem/rd)*bn));
    ghtml+=`<div style="text-align:center">${miniPizza(pn,bd,'#9490bf',true)}
      <div style="font-size:.68rem;color:var(--sub);font-weight:700;margin-top:2px">partial</div></div>`;
  }
  ghtml+='</div>';
  const note=rem===0
    ?`<div style="margin-top:7px;font-size:.76rem;color:var(--g);font-weight:700">✅ ${whole} complete group${whole!==1?'s':''}!</div>`
    :`<div style="margin-top:7px;font-size:.76rem;color:var(--sub)">= ${rn}/${rd} groups</div>`;
  sec.innerHTML=`
    <div>
      <div class="vp"><div class="vpl">🍕 Dividend</div>
        <div class="pwrap">${pizza(an,ad)}</div>
        <div style="text-align:center;font-size:.75rem;color:var(--sub);margin-top:6px"><strong style="color:var(--y)">${an}/${ad}</strong> shaded</div>
      </div>
      <div class="vp" style="margin-top:8px"><div class="vpl">📏 Number Line</div>${buildNL(an,ad,bn,bd,rn,rd)}</div>
    </div>
    <div class="arw">
      <svg width="32" height="54" viewBox="0 0 32 54">
        <line x1="16" y1="3" x2="16" y2="42" stroke="var(--c)" stroke-width="2.5" stroke-linecap="round"/>
        <polyline points="6,32 16,48 26,32" fill="none" stroke="var(--c)" stroke-width="2.5" stroke-linejoin="round"/>
      </svg>
      <span style="color:var(--sub);font-size:.62rem">How<br>many<br>fit?</span>
    </div>
    <div class="vp"><div class="vpl">📦 Groups of ${bn}/${bd}</div>${ghtml}${note}</div>`;
}

function buildNL(an,ad,bn,bd,rn,rd){
  const W=240,H=65,pad=17,lY=32,tickH=8,ll=W-pad*2;
  const dec=rn/rd,maxV=Math.max(2,Math.ceil(dec)+.5),step=bn/bd;
  let marks=[];for(let v=0;v<=maxV+.01;v+=step)marks.push(+v.toFixed(6));
  const xOf=v=>pad+(v/maxV)*ll;
  const cols=['#22d3ee','#a78bfa','#00d2a0','#f9ca24','#e84393','#f0932b'];
  let svg=`<svg class="nls" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">`;
  const divX=xOf(an/ad);
  svg+=`<rect x="${pad}" y="${lY-tickH/2}" width="${divX-pad}" height="${tickH}" fill="rgba(249,202,36,0.15)" rx="2"/>`;
  svg+=`<line x1="${pad}" y1="${lY}" x2="${W-pad+7}" y2="${lY}" stroke="rgba(255,255,255,0.18)" stroke-width="2" stroke-linecap="round"/>`;
  svg+=`<polyline points="${W-pad+1},${lY-4} ${W-pad+8},${lY} ${W-pad+1},${lY+4}" fill="rgba(255,255,255,0.18)"/>`;
  marks.forEach((v,i)=>{
    if(i===0)return;
    const x=xOf(v),xp=xOf(marks[i-1]),col=v<=an/ad+.003?cols[(i-1)%cols.length]:'rgba(255,255,255,0.09)';
    svg+=`<path d="M${xp.toFixed(1)},${lY} Q${((xp+x)/2).toFixed(1)},${lY-15} ${x.toFixed(1)},${lY}" fill="none" stroke="${col}" stroke-width="1.7" stroke-dasharray="4 2"/>`;
    svg+=`<circle cx="${x.toFixed(1)}" cy="${lY}" r="3.2" fill="${col}" opacity=".85"/>`;
  });
  svg+=`<text x="${pad}" y="${lY+19}" font-size="9" fill="rgba(255,255,255,0.38)" text-anchor="middle" font-family="Nunito,sans-serif">0</text>`;
  svg+=`<line x1="${divX.toFixed(1)}" y1="${lY-12}" x2="${divX.toFixed(1)}" y2="${lY+8}" stroke="var(--y)" stroke-width="1.5" stroke-dasharray="3 2"/>`;
  svg+=`<text x="${divX.toFixed(1)}" y="${lY+19}" font-size="8.5" fill="var(--y)" text-anchor="middle" font-family="Fredoka One,cursive">${an}/${ad}</text>`;
  svg+=`</svg>`;
  return`<div class="nlw">${svg}</div><div style="font-size:.7rem;color:var(--sub);margin-top:3px">Each hop = ${bn}/${bd} → <strong style="color:var(--c)">${rn}/${rd}</strong> hops reach ${an}/${ad}</div>`;
}

function buildSteps(an,ad,bn,bd,rn,rd){
  const fN=bd,fD=bn,mN=an*fN,mD=ad*fD;
  const g2=gcd(Math.abs(mN),Math.abs(mD));
  const sn=mN/g2,sd=mD/g2;
  document.getElementById('stepPanel').innerHTML=`
    <div class="vpl" style="margin-bottom:0">🪄 Keep · Change · Flip</div>
    <div class="srow" style="animation-delay:.04s"><div class="snum" style="background:rgba(249,202,36,.18);color:var(--y)">1</div>
      <div class="stxt">${sc('var(--y)','Keep')} first fraction: ${sc('var(--y)',`${an}/${ad}`)}</div></div>
    <div class="srow" style="animation-delay:.12s"><div class="snum" style="background:rgba(232,67,147,.18);color:var(--p)">2</div>
      <div class="stxt">${sc('var(--p)','Change')} ÷ → ×</div></div>
    <div class="srow" style="animation-delay:.20s"><div class="snum" style="background:rgba(34,211,238,.18);color:var(--c)">3</div>
      <div class="stxt">${sc('var(--c)','Flip')} second: ${sc('var(--c)',`${bn}/${bd}`)} → ${sc('var(--c)',`${fN}/${fD}`)}</div></div>
    <div class="srow" style="animation-delay:.28s"><div class="snum" style="background:rgba(0,210,160,.18);color:var(--g)">4</div>
      <div class="stxt">${sc('var(--g)','Multiply')}: ${sc('var(--y)',`${an}/${ad}`)} × ${sc('var(--c)',`${fN}/${fD}`)} = ${mN}/${mD}${(sn!==mN||sd!==mD)?' = '+sc('var(--y)',`${sn}/${sd}`):'= '+sc('var(--y)',`${sn}/${sd}`)}</div></div>`;
}

/* ── BUILD CHOICES — stores n/d as data attributes (KEY FIX) ── */
function buildChoices(choices,rn,rd,an,ad,bn,bd){
  document.getElementById('cgr').innerHTML=choices.map(ch=>{
    const lbl=ch.d===1
      ?`<span class="ct">${ch.n}</span>`
      :`<span class="ct">${ch.n}</span><div class="cl"></div><span class="cb2">${ch.d}</span>`;
    // ✅ FIX: store numeric n/d in data attributes so checkAns is reliable
    return`<button class="cb" data-n="${ch.n}" data-d="${ch.d}" onclick="checkAns(event,this)">${lbl}</button>`;
  }).join('');
}

/* ── CHECK ANSWER (calls PHP) — FIXED ───────────────────────── */
async function checkAns(e,btn){
  if(answered)return;
  answered=true;
  ripple(btn,e);

  // ✅ FIX: read n/d from data attributes — no DOM text parsing
  const cn=parseInt(btn.dataset.n,10);
  const cd=parseInt(btn.dataset.d,10);

  document.querySelectorAll('.cb').forEach(b=>b.disabled=true);

  let data;
  try {
    data = await api({action:'checkAnswer',cn,cd});
  } catch(err) {
    console.error('API error:',err);
    answered=false;
    document.querySelectorAll('.cb').forEach(b=>b.disabled=false);
    return;
  }

  const ok=data.ok;
  const rn=data.answer.n;
  const rd=data.answer.d;

  if(ok){
    btn.classList.add('cor');
    updateBar(data.score,data.streak,data.xp,qIdx+1);
    gainXP(data.xp,data.xpLevel);
    sndOk();confetti();
    showToast('✅ Correct! Fantastic!','cor');
    mascotMood('happy');
    if(data.streak>=3)showMascot(`🔥 ${data.streak} in a row! You're on fire!`,2500);
  } else {
    btn.classList.add('wrg');
    updateBar(data.score,data.streak,data.xp,qIdx+1);
    sndBad();
    showToast(`❌ Not quite! Answer: ${rn}/${rd}`,'wrg');
    mascotMood('sad');
    // ✅ FIX: highlight correct using data attributes, not DOM text parsing
    document.querySelectorAll('.cb').forEach(b=>{
      const [sn,sd]=simp(parseInt(b.dataset.n,10),parseInt(b.dataset.d,10));
      if(sn===rn && sd===rd) b.classList.add('cor');
    });
  }

  setTimeout(()=>{
    sndNext();
    nextQ(data.done);
  }, ok ? 1500 : 2000);
}

async function nextQ(done){
  if(done){showResults();return;}
  qIdx++;
  window.scrollTo({top:0,behavior:'smooth'});
  setTimeout(async()=>{showScreen('gameScreen');await loadQuestion();},150);
}

/* ── XP UI ───────────────────────────────────────────────────── */
let _xpLevel=1;
function gainXP(xp,newLevel){
  document.getElementById('xpb').style.width=`${Math.min(100,(xp%(newLevel*100))/(newLevel*100)*100)}%`;
  document.getElementById('xpv').textContent=xp;
  if(newLevel>_xpLevel){
    _xpLevel=newLevel;
    const em=['','🌱','🌟','🔥','💎','👑','🏆'];
    document.getElementById('lvem').textContent=em[Math.min(newLevel,em.length-1)]||'⭐';
    document.getElementById('lvtit').textContent=`Level ${newLevel} Reached!`;
    document.getElementById('lvdesc').textContent='Your fraction skills are levelling up!';
    const ov=document.getElementById('lvlov');
    ov.classList.add('show');sndLvl();
    showToast(`⚡ Level ${newLevel} Unlocked!`,'lvl');
    setTimeout(()=>ov.classList.remove('show'),2800);
  }
}

/* ── RESULTS ─────────────────────────────────────────────────── */
async function showResults(){
  document.getElementById('pgbar').style.width='100%';
  document.getElementById('sbar').style.display='none';
  document.getElementById('xpw').style.display='none';

  let result;
  try {
    result = await api({action:'getResults'});
  } catch(err) {
    console.error('getResults error:',err);
    return;
  }

  if(result.error){
    console.error('Session error:',result.error);
    return;
  }

  const pct=result.accuracy;
  let em,msg;
  if(pct>=90){em='🏆';msg="Outstanding! You're a Fraction Master!";}
  else if(pct>=70){em='🌟';msg='Great work! Keep practising to reach the top!';}
  else if(pct>=50){em='🙂';msg='Solid effort! Review the steps and try again.';}
  else{em='📚';msg="Every expert starts here — you've got this!";}

  document.getElementById('rem').textContent=em;
  document.getElementById('rsc').textContent=`${result.score} pts`;
  document.getElementById('rmsg').textContent=msg;
  document.getElementById('rb1').textContent=result.score;
  document.getElementById('rb2').textContent=result.bestStreak;
  document.getElementById('rb3').textContent=`${pct}%`;

  showMascot('Great game! 🎉 Well done!',4000);
  if(pct>=70) setTimeout(()=>confetti(120),300);

  showScreen('resScreen');

  // ✅ Score is saved inside getResults on the PHP side
  if(result.saved){
    console.log('✅ Score saved successfully! Score:', result.score, '| Level:', result.level, '| Accuracy:', result.accuracy + '%');
  } else {
    console.warn('⚠️ Score may not have been saved.');
  }
}

/* ── UI HELPERS ──────────────────────────────────────────────── */
function updateBar(score,streak,xp,qn){
  if(score!==undefined) document.getElementById('sv').textContent=score;
  if(streak!==undefined) document.getElementById('stv').textContent=streak;
  if(xp!==undefined) document.getElementById('xpv').textContent=xp;
  if(qn!==undefined) document.getElementById('qn').textContent=qn;
}
function toggleHint(){
  const box=document.getElementById('hbox'),btn=document.getElementById('hbtn');
  box.classList.toggle('open');
  btn.textContent=box.classList.contains('open')?'🙈 Hide Hint':'💡 Show Hint';
  sndClick();
}
let toastT;
function showToast(msg,type){
  const t=document.getElementById('toast');
  t.textContent=msg;t.className=`toast ${type} show`;
  clearTimeout(toastT);toastT=setTimeout(()=>t.classList.remove('show'),2100);
}
async function restartGame(){sndClick();await startGame();}
function goHome(){
  sndClick();qIdx=0;_xpLevel=1;
  document.getElementById('sbar').style.display='none';
  document.getElementById('xpw').style.display='none';
  showScreen('introScreen');
  showMascot('Welcome back! Ready to learn? 🎓',3000);
}

/* ── MASCOT ──────────────────────────────────────────────────── */
let mascotT;
function showMascot(msg,dur=3000){
  const b=document.getElementById('mbubble');
  b.textContent=msg;b.classList.add('show');
  clearTimeout(mascotT);mascotT=setTimeout(()=>b.classList.remove('show'),dur);
}
function mascotMood(mood){
  const m=document.getElementById('mmouth');
  m.setAttribute('d',mood==='happy'?'M20 40 Q32 52 44 40':mood==='sad'?'M22 46 Q32 38 42 46':'M22 42 Q32 50 42 42');
  setTimeout(()=>m.setAttribute('d','M22 42 Q32 50 42 42'),2000);
}
setTimeout(()=>showMascot("Hi! I'm Frac! Pick a level to begin 👋",4000),900);

/* ── CONFETTI ─────────────────────────────────────────────────── */
const cc=document.getElementById('cc'),cx2=cc.getContext('2d');
let parts=[];
cc.width=window.innerWidth;cc.height=window.innerHeight;
window.addEventListener('resize',()=>{cc.width=window.innerWidth;cc.height=window.innerHeight});
function confetti(n=68){
  const cls=['#f9ca24','#f0932b','#00d2a0','#e84393','#22d3ee','#a78bfa','#fd79a8'];
  for(let i=0;i<n;i++){
    parts.push({x:Math.random()*cc.width,y:cc.height*.25+Math.random()*cc.height*.3,
      vx:(Math.random()-.5)*8,vy:-(Math.random()*9+4),r:Math.random()*6+2.5,
      color:cls[Math.floor(Math.random()*cls.length)],rot:Math.random()*360,
      dr:(Math.random()-.5)*9,life:1,circle:Math.random()>.5});
  }
  animC();
}
let craf;
function animC(){
  cx2.clearRect(0,0,cc.width,cc.height);
  parts=parts.filter(p=>p.life>.02);
  parts.forEach(p=>{
    p.x+=p.vx;p.y+=p.vy;p.vy+=.28;p.rot+=p.dr;p.life-=.016;
    cx2.save();cx2.translate(p.x,p.y);cx2.rotate(p.rot*Math.PI/180);
    cx2.globalAlpha=p.life;cx2.fillStyle=p.color;
    if(p.circle){cx2.beginPath();cx2.arc(0,0,p.r,0,Math.PI*2);cx2.fill();}
    else cx2.fillRect(-p.r,-p.r*.5,p.r*2,p.r);
    cx2.restore();
  });
  if(parts.length>0)craf=requestAnimationFrame(animC);
  else cx2.clearRect(0,0,cc.width,cc.height);
}
</script>
</body>
</html