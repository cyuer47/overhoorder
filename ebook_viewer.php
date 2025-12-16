<?php
session_start();
require_once __DIR__ . '/php/db.php';

// --- Auth ---
if (empty($_SESSION['docent_id'])) {
    http_response_code(403);
   // exit("Je moet ingelogd zijn om een boek te bekijken.");
    header("Location: dashboard.php");
}

$docent_id = (int) $_SESSION['docent_id'];
$boek_id = (int) ($_SESSION['current_ebook_id'] ?? 0);

// Nieuwe logica: Standaard pagina is 's' als er geen of een ongeldig paginanummer is opgegeven.
$page_param = 's'; 

if (isset($_GET['page'])) {
    $page_input = $_GET['page'];
    
    if ($page_input === 's') {
        $page_param = 's';
    } else {
        $num = (int)$page_input;
        // Zorg ervoor dat het nummer >= 1 is. 0 wordt 's'
        if ($num >= 1) {
            $page_param = $num;
        } else {
            // Als het nummer <= 0 is (bijv. page=0), behandelen we dit als 's'
            $page_param = 's';
        }
    }
}

// In de JSON wordt de startpagina (s) met de key "0" geïdentificeerd.
$internal_page_key = ($page_param === 's') ? '0' : (string)$page_param;


if ($boek_id <= 0) {
    http_response_code(403);
    // exit("Toegang geweigerd. Het boek-ID is niet correct ingesteld. Gelieve terug te gaan naar het dashboard.");
    header("Location: dashboard.php");
}

// --- Licentie-check ---
$stmt = $pdo->prepare("
    SELECT b.id, b.titel, b.bestand
    FROM boeken b
    INNER JOIN licentie_boeken lb ON lb.boek_id = b.id
    INNER JOIN licenties l ON l.id = lb.licentie_id
    WHERE b.id = ? AND l.docent_id = ?
    LIMIT 1
");
$stmt->execute([$boek_id, $docent_id]);
$boek = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$boek) {
    http_response_code(403);
    // exit("Toegang geweigerd. Je hebt geen licentie voor dit boek.");
    header("Location: dashboard.php");
}

$bestand = basename($boek['bestand']);
$jsonFile = __DIR__ . "/ebooks/$bestand";

// Endpoint: serveer raw JSON (voor de frontend)
if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (strpos($bestand, '..') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige bestandsnaam']);
        exit;
    }
    if (!is_file($jsonFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'E-book JSON niet gevonden']);
        exit;
    }
    readfile($jsonFile);
    exit;
}

?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= htmlspecialchars($boek['titel']) ?></title>

<link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">

<link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<style>
:root{
  --primary:#f0bcff;
  --old-primary:#1565c0;
  --on-primary:#3f006a;
  --surface:#ffffff;
  --bg:#f4f6f9;
  --muted:#6b6b6b;
  --radius:14px;
  --elev:0 6px 18px rgba(19,38,63,0.12);
  --toast-bg: rgba(0,0,0,0.8);
  --header-height:72px;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:"Google Sans",system-ui,Segoe UI,Roboto,"Helvetica Neue",Arial;
  background:var(--bg);
  color:#111;
  display:flex;
  overflow:hidden;
}

/* Left toolbar */
.leftbar{
  width:72px;
  background:var(--primary);
  display:flex;
  flex-direction:column;
  align-items:center;
  padding:12px 8px;
  gap:10px;
  box-shadow:2px 0 12px rgba(0,0,0,0.12);
  min-height:100vh;
  transition:transform .3s ease;
}
.leftbar .btn{
  width:48px;height:48px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  color:var(--on-primary);
  cursor:pointer;border:none;background:transparent;
  transition:background .15s, transform .08s;
}
.leftbar .btn:hover{background:rgba(255,255,255,0.08)}
.leftbar .btn.active{background:rgba(255,255,255,0.14); transform:translateY(-2px)}

/* Responsive collapse for mobile */
@media(max-width:800px){
  .leftbar{
    position:fixed;
    left:0;top:0;bottom:0;
    transform:translateX(-100%);
    z-index:1200;
  }
  .leftbar.open{transform:translateX(0);}
}

/* Main area */
.container{
  flex:1;
  display:flex;
  flex-direction:column;
  height:100vh;
  overflow:hidden;
  padding-top:var(--header-height); /* ruimte voor header */
  position:relative;
}

/* Header (vast bovenaan) */
.header{
  position:fixed;
  top:0;
  left:72px;
  right:0;
  height:var(--header-height);
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:14px 20px;
  background:var(--surface);
  backdrop-filter:blur(10px);
  z-index:1000;
  box-shadow:0 2px 8px rgba(0,0,0,0.06);
  transition:box-shadow .2s ease, background .2s ease, left .3s ease;
}
.header.scrolled {
  box-shadow:0 4px 16px rgba(0,0,0,0.12);
  background:rgba(255,255,255,0.95);
}
.header .title{
  font-size:18px;
  font-weight:600;
}
.header .controls{
  display:flex;
  gap:10px;
  align-items:center;
}

/* Responsive header fix */
@media(max-width:800px){
  .header{left:0;}
  .container{padding-top:64px;}
  .header .title{font-size:16px;}
}

/* Viewer area */
.viewport{
  flex:1;
  display:flex;
  /* Centreert de viewer horizontaal, maar bovenaan verticaal in de scroll (align-items:flex-start) */
  align-items:flex-start; 
  justify-content:center;
  overflow:auto;
  /* Extra top padding om te voorkomen dat de pagina tegen de header plakt */
  padding: 20px; 
  height:calc(100vh - var(--header-height));
  -webkit-overflow-scrolling:touch;
  scroll-behavior:smooth;
}
.viewer{
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:20px;
  width:100%;
  max-width:1100px;
}
.page-wrapper{
  position:relative;
  border-radius:var(--radius);
  overflow:visible;
  background:var(--surface);
  padding:12px;
  box-shadow:var(--elev);
}
.page-inner{
  position:relative;
  display:block;
  overflow:hidden;
  border-radius:10px;
  transform-origin:center center;
}
.page-inner img{
  display:block;
  max-width:100%;
  height:auto;
  user-select:none;
  pointer-events:none;
}
.overlay-canvas{
  position:absolute;
  top:0;
  left:0;
  width:100%;
  height:100%;
  touch-action:none;
}

/* mini controls under header */
.pager {
	display: flex;
	gap: 4px;
	align-items: center;
	background: rgba(255,255,255,0.95);
	padding: 8px 12px;
	border-radius: 100vw;
	box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}
.pager input[type="text"] {
	width: 80px;
	padding: 10px 8px;
	border-radius: 8px;
	border: 1px solid #e6e6e6;
}
#prevSmall, #nextSmall {
	border: none;
	border-radius: 100vw;
	padding: 6px 8px;
        cursor: pointer;
}
#prevSmall {
	border-top-right-radius: 5rem;
	border-bottom-right-radius: 5rem;
}
#nextSmall {
	border-top-left-radius: 5rem;
	border-bottom-left-radius: 5rem;
}

/* chapters panel */
.panel{
  position:fixed;
  left:92px;
  top:88px;
  width:320px;
  max-height:70vh;
  overflow:auto;
  background:var(--surface);
  border-radius:12px;
  box-shadow:var(--elev);
  padding:12px;
  display:none;
}
.panel h4{
  margin:0 0 8px 0;
  font-size:15px;
}
.chapter-item{
  padding:8px 10px;
  border-radius:8px;
  cursor:pointer;
  color:#222;
  transition:background .2s;
}
.chapter-item:hover{
  background:rgba(0,0,0,0.04);
}

/* color palette */
.palette{
  display:flex;
  gap:8px;
  padding:8px;
  flex-wrap:wrap;
}
.color-swatch{
  width:34px;
  height:34px;
  border-radius:8px;
  box-shadow:0 2px 8px rgba(0,0,0,0.12);
  cursor:pointer;
  border:2px solid transparent;
}
.color-swatch.active{
  outline:3px solid rgba(0,0,0,0.08);
}

/* right side small panel for bookmarks/notes */
.sidebox{
  position:fixed;
  right:16px;
  top:88px;
  width:320px;
  max-height:70vh;
  overflow:auto;
  background:var(--surface);
  border-radius:12px;
  box-shadow:var(--elev);
  padding:12px;
  display:none;
}
.sidebox h4{margin:0 0 8px 0}

/* toast */
.toast{
  position:fixed;
  left:50%;
  transform:translateX(-50%);
  top:18px;
  background:var(--toast-bg);
  color:white;
  padding:8px 14px;
  border-radius:8px;
  opacity:0;
  transition:opacity .2s, transform .2s;
  transform-origin:center;
  z-index:9999;
}

/* small helpers */
.icon{
  font-family:'Material Icons';
  font-size:20px;
  line-height:1;
}
.small{font-size:13px; color:var(--muted)}

/* dark-mode minimal */
body.dark-mode {
	--primary: #411368;
	--surface: #2d2d2d;
	--bg: #1a1a1a;
	--text: #ddd;
	color: #eee;
	--on-primary: rgb(228, 210, 255);
        --muted: #ccc;
}
.dark-mode .pager {
	background-color: #353434f2;
}
</style>
</head>
<body>
  <div class="leftbar" role="toolbar" aria-label="Tools">
    <button class="btn" id="backBtn" title="Terug naar dashboard"><span class="icon">arrow_back</span></button>
    <button class="btn" id="zoomIn" title="Inzoomen"><span class="icon">zoom_in</span></button>
    <button class="btn" id="zoomOut" title="Uitzoomen"><span class="icon">zoom_out</span></button>
    <button class="btn" id="panBtn" title="Pan (houd spatie en sleep)"><span class="icon">open_with</span></button>
    <button class="btn" id="drawBtn" title="Tekenen"><span class="icon">edit</span></button>
    <button class="btn" id="highlightBtn" title="Markeren"><span class="icon">format_color_text</span></button>
    <button class="btn" id="eraseBtn" title="Gummen"><span class="icon">delete_outline</span></button>
    <button class="btn" id="undoBtn" title="Ongedaan (per pagina)"><span class="icon">undo</span></button>
    <button class="btn" id="clearBtn" title="Alles wissen (per boek)"><span class="icon">clear_all</span></button>
    <button class="btn" id="bookmarkBtn" title="Bladwijzers"><span class="icon">bookmark</span></button>
    <button class="btn" id="chaptersBtn" title="Hoofdstukken"><span class="icon">menu_book</span></button>
    <button class="btn" id="notesBtn" title="Notities"><span class="icon">note</span></button>
    <div style="flex:1"></div>
    <button class="btn" id="themeBtn" title="Donker/wit thema"><span class="icon">dark_mode</span></button>
  </div>

  <div class="container">
    <div class="header">
      <div class="title"><?= htmlspecialchars($boek['titel']) ?></div>
      <div class="controls">
        <div class="pager">
          <button id="prevSmall" title="Vorige"><span class="icon">chevron_left</span></button>
          <input id="pageNumber" type="text" value="<?= htmlspecialchars($page_param) ?>">
          <button id="nextSmall" title="Volgende"><span class="icon">chevron_right</span></button>
          <div style="width:8px"></div>
          <div class="small" id="pageCount"></div>
        </div>
      </div>
    </div>

    <div class="viewport" id="viewport">
      <div class="viewer" id="viewer">
        <div class="page-wrapper" id="pageWrapper" aria-live="polite">
          <div class="page-inner" id="pageInner" style="transform:scale(1);">
            </div>
        </div>
      </div>
    </div>

    <div class="panel" id="chaptersPanel" aria-hidden="true">
      <h4>Hoofdstukken</h4>
      <div id="chapList"></div>
    </div>

    <div class="sidebox" id="sidePanel" aria-hidden="true">
      <h4 id="sideTitle">Bladwijzers</h4>
      <div id="sideContent"></div>
    </div>

  </div>

  <div class="toast" id="toast" aria-live="assertive"></div>

<script>
/* ========== Config & state ========== */
/* EBOOK API: same file, json endpoint */
const EBOOK_API = `<?= htmlspecialchars(basename(__FILE__)) ?>?json=1&id=<?= (int)$boek_id ?>`;

/* startPageRequested: 's' or number (page 1+) */
let startPageRequested = '<?= $page_param ?>';
// Initial value. If $page_param is an integer, it is correctly cast to a string here.
let currentPage = startPageRequested; 

let currentBook = null;
let totalPages = 0;
let scale = 1;
let mode = 'pan'; // pan, draw, highlight, erase
let penColor = '#ff5252';
let penWidth = 3;
let highlightAlpha = 0.35;
const MIN_SCALE = 0.5, MAX_SCALE = 2.5;
const viewer = document.getElementById('viewer');
const pageInner = document.getElementById('pageInner');
const viewport = document.getElementById('viewport');
const pageNumberInput = document.getElementById('pageNumber');
const pageCountEl = document.getElementById('pageCount');
const toastEl = document.getElementById('toast');
const headerEl = document.querySelector('.header');

/* Storage helpers */
const LS_PREFIX = 'ebookviewer_v1';
function lsKeyBook(bid){ return `${LS_PREFIX}_book_${bid}_drawings`; }
function lsKeyBookmarks(bid){ return `${LS_PREFIX}_book_${bid}_bmarks`; }
function lsKeyNotes(bid){ return `${LS_PREFIX}_book_${bid}_notes`; }
function lsGet(k, fallback = null){ try{ const v = localStorage.getItem(k); return v? JSON.parse(v) : fallback; }catch(e){return fallback} }
function lsSet(k, v){ try{ localStorage.setItem(k, JSON.stringify(v)); }catch(e){} }

/* simple toast */
let toastTimer = null;
function toast(msg, time=1800){
  toastEl.textContent = msg;
  toastEl.style.opacity = '1';
  toastEl.style.transform = 'translateY(0)';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>{ toastEl.style.opacity='0'; toastEl.style.transform='translateY(-8px)'; }, time);
}

/* fetch JSON, load ebook(s) */
async function loadEbookJson(){
  try{
    const resp = await fetch(EBOOK_API, { credentials: 'same-origin' });
    if(!resp.ok) throw new Error('Kan ebook JSON niet laden.');
    const data = await resp.json();
    if(data.error) throw new Error(data.error);
    if(!Array.isArray(data.ebooks) || data.ebooks.length===0) throw new Error('Geen ebooks gevonden in JSON.');
    // find ebook in list by id param (if multiple)
    const ebooks = data.ebooks;
    let chosen = ebooks.find(e => Number(e.id) === <?= (int)$boek_id ?>) ?? ebooks[0];
    currentBook = chosen;
    setupFromBook(chosen);
  }catch(err){
    pageInner.innerHTML = `<div style="padding:28px;color:#b00">Fout: ${err.message}</div>`;
  }
}

/* Utility: normalize page key ('s' -> '0' internally) */
function pageKeyForJson(page){
  // if page is 's' we map to "0" key (the internal startpage index)
  if(page === 's' || page === 'S') return String(0);
  // for numeric pages (1, 2, 3...) we use their string representation
  return String(page);
}

/* Setup UI from JSON book object */
function setupFromBook(b){
  const pages = b.pageFiles || {};
  const keys = Object.keys(pages).filter(k => k !== undefined).sort((a,b)=> Number(a)-Number(b));
  // determine totalPages excluding the special 0
  totalPages = keys.filter(k => k !== String(0)).length;
  pageCountEl.textContent = ` / ${totalPages}`;
  
  // build chapters
  const chpanel = document.getElementById('chapList'); chpanel.innerHTML='';
  if(b.chapters){
    for(const [title, p] of Object.entries(b.chapters)){
      const div = document.createElement('div');
      div.className='chapter-item';
      div.textContent = title;
      // map internal 0 back to 's' for navigation
      const targetPage = (Number(p) === 0) ? 's' : Number(p);
      div.onclick = ()=> goToPage(targetPage);
      chpanel.appendChild(div);
    }
  }
  // build palette from highlightColors or default
  window.paletteColors = b.highlightColors ?? ['#FFEB3B','#00E676','#00B0FF','#FF5252'];
  buildPalette();

  // show/hide tools based on tools flags
  const tools = b.tools ?? { drawing:true, highlighting:true, eraser:true, zoom:true };
  document.getElementById('drawBtn').style.display = tools.drawing? 'flex' : 'none';
  document.getElementById('highlightBtn').style.display = tools.highlighting? 'flex' : 'none';
  document.getElementById('eraseBtn').style.display = tools.eraser? 'flex' : 'none';
  document.getElementById('zoomIn').style.display = tools.zoom? 'flex' : 'none';
  document.getElementById('zoomOut').style.display = tools.zoom? 'flex' : 'none';

  // Check if requested page exists
  const pf = b.pageFiles || {};
  const startKey = pageKeyForJson(currentPage);
  if(!(startKey in pf)){
    // Fallback: If 's' exists, use 's'. Otherwise, use the first numeric page.
    if(String(0) in pf) {
        currentPage = 's';
    } else {
        const numericKeys = Object.keys(pf).filter(k => k !== String(0)).sort((a,b)=> Number(a)-Number(b));
        currentPage = numericKeys.length ? Number(numericKeys[0]) : 's'; // fallback 's' if all else fails
    }
    pageNumberInput.value = currentPage; // Update UI
  }
  
  // render first visible page
  renderPage(currentPage);
  setActiveTool('panBtn'); // default to pan
}

/* Build color palette UI (inserted near leftbar bottom in DOM as floating) */
function buildPalette(){
  let pal = document.getElementById('paletteFloat');
  if(!pal){
    pal = document.createElement('div');
    pal.id = 'paletteFloat';
    pal.style.position = 'fixed';
    pal.style.left = '88px';
    pal.style.bottom = '20px';
    pal.style.background = 'rgba(255,255,255,0.98)';
    pal.style.padding = '10px';
    pal.style.borderRadius = '10px';
    pal.style.boxShadow = '0 6px 18px rgba(0,0,0,0.08)';
    pal.style.display = 'flex';
    pal.style.flexDirection = 'column';
    pal.style.gap = '8px';
    pal.style.zIndex = 999;
    document.body.appendChild(pal);
  }
  pal.innerHTML = `<div style="font-size:13px;color:#333;margin-bottom:4px">Kleur</div>`;
  const wrap = document.createElement('div');
  wrap.style.display='flex'; wrap.style.gap='8px';
  window.paletteColors.forEach(c=>{
    const sw = document.createElement('div');
    sw.className='color-swatch';
    sw.style.background = c;
    sw.onclick = ()=> { penColor = c; highlightAlpha = (c === '#FFEB3B')? 0.4 : 0.35; updateActiveSwatch(); };
    wrap.appendChild(sw);
  });
  pal.appendChild(wrap);
  updateActiveSwatch();
}
function updateActiveSwatch(){
  document.querySelectorAll('.color-swatch').forEach(el=>{
    el.classList.toggle('active', el.style.background === penColor);
  });
}

/* Render page: image + overlay canvas (single page view) */
function renderPage(pageNum){
  if(!currentBook) return;
  const pf = currentBook.pageFiles || {};
  const key = pageKeyForJson(pageNum);
  if(!pf[key]) {
    pageInner.innerHTML = `<div style="padding:28px;color:#b00">Pagina ${pageNum} niet gevonden</div>`;
    return;
  }
  currentPage = pageNum;
  pageNumberInput.value = pageNum;

  // clear previous
  pageInner.innerHTML = '';

  // create image
  const img = new Image();
  img.draggable = false;
  img.alt = (pageNum === 's') ? 'Startpagina' : `Pagina ${pageNum}`;
  img.src = `https://ebook.noordhoff.nl/${pf[key]}_1.5.png`;
  img.onload = ()=>{
    // build overlay canvas matching displayed image pixel size
    const wrapper = document.createElement('div'); wrapper.style.position='relative';
    wrapper.style.display='inline-block';
    wrapper.style.maxWidth = '100%';
    const imgEl = img;
    imgEl.style.display='block';
    imgEl.style.maxWidth='100%';
    imgEl.style.height='auto';
    imgEl.style.userSelect='none';

    // precise canvas dimensions: use intrinsic image dimensions
    const canvas = document.createElement('canvas');
    canvas.className = 'overlay-canvas';
    canvas.width = imgEl.naturalWidth;
    canvas.height = imgEl.naturalHeight;
    // initially set CSS size after image painted
    canvas.style.position = 'absolute';
    canvas.style.left = 0;
    canvas.style.top = 0;
    canvas.style.zIndex = 5;

    // put image and canvas into wrapper
    wrapper.appendChild(imgEl);
    wrapper.appendChild(canvas);
    pageInner.appendChild(wrapper);

    // after image added to DOM, set CSS canvas size to match displayed image
    requestAnimationFrame(()=> {
      canvas.style.width = imgEl.getBoundingClientRect().width + 'px';
      canvas.style.height = imgEl.getBoundingClientRect().height + 'px';
      initCanvasInteractions(canvas, pageNum);

      // if saved drawing exists load
      const drawings = lsGet(lsKeyBook(<?= (int)$boek_id ?>), {});
      if(drawings && drawings[pageNum]){
        const imgData = new Image();
        imgData.onload = ()=> {
          const ctx = canvas.getContext('2d');
          ctx.clearRect(0,0,canvas.width,canvas.height);
          // draw into canvas sized by intrinsic pixels; the saved dataURL already matches canvas dimensions
          ctx.drawImage(imgData,0,0);
        };
        imgData.src = drawings[pageNum];
      }

      // keep transform (scale) consistent when image size changes
      updateScaleOnPage();
      // ensure the rendered page is visible below the header (not hidden under it)
      scrollRenderedPageIntoView();

      toast((pageNum === 's') ? 'Startpagina' : `Pagina ${pageNum}`, 900);
    });
  };
  img.onerror = ()=> {
    pageInner.innerHTML = `<div style="padding:28px;color:#b00">Fout bij laden van afbeelding</div>`;
  };
}

/* Ensure rendered page is scrolled into view below header (so it's not hidden under the fixed header) */
function scrollRenderedPageIntoView(){
  // Omdat de CSS (.viewport in .container met padding-top) de pagina al onder de header plaatst,
  // hoeven we alleen de viewport naar de top te scrollen (scrollPos = 0).
  requestAnimationFrame(()=>{
    viewport.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

/* Initialize drawing interactions on given canvas for page p */
function initCanvasInteractions(canvas, pageNum){
  const ctx = canvas.getContext('2d');
  ctx.lineJoin = 'round';
  ctx.lineCap = 'round';

  // manage undo stack per page
  const undoStacks = lsGet('undoStacks', {}) || {};
  if(!undoStacks[pageNum]) undoStacks[pageNum] = [];
  // push initial state
  function pushState(){
    try{
      const data = canvas.toDataURL();
      undoStacks[pageNum].push(data);
      if(undoStacks[pageNum].length > 20) undoStacks[pageNum].shift();
      lsSet('undoStacks', undoStacks);
    }catch(e){}
  }

  let drawing = false;
  let last = {x:0,y:0};
  let pointerId = null;

  // pointer handling
  canvas.addEventListener('pointerdown', (ev)=>{
    if(!(mode === 'draw' || mode === 'highlight' || mode === 'erase')) return;
    canvas.setPointerCapture(ev.pointerId);
    pointerId = ev.pointerId;
    drawing = true;
    const pos = toCanvasCoords(ev, canvas);
    last = pos;
    pushState();
    if(mode === 'erase'){
      ctx.clearRect(pos.x - 10, pos.y - 10, 20, 20);
    } else {
      ctx.beginPath();
      ctx.moveTo(pos.x, pos.y);
    }
  }, {passive:false});

  canvas.addEventListener('pointermove', (ev)=>{
    if(!drawing || ev.pointerId !== pointerId) return;
    const pos = toCanvasCoords(ev, canvas);
    if(mode === 'erase'){
      ctx.clearRect(pos.x - (penWidth*2), pos.y - (penWidth*2), penWidth*4, penWidth*4);
    } else {
      ctx.strokeStyle = mode === 'highlight' ? rgbaFromHex(penColor, highlightAlpha) : penColor;
      ctx.lineWidth = mode === 'highlight' ? Math.max(10, penWidth*4) : penWidth;
      ctx.lineTo(pos.x, pos.y);
      ctx.stroke();
    }
    last = pos;
  }, {passive:false});

  function finishStroke(ev){
    if(!drawing) return;
    if(pointerId !== null && ev && ev.pointerId !== pointerId) return;
    drawing = false;
    pointerId = null;
    try{
      const map = lsGet(lsKeyBook(<?= (int)$boek_id ?>), {});
      map[pageNum] = canvas.toDataURL();
      lsSet(lsKeyBook(<?= (int)$boek_id ?>), map);
    }catch(e){}
  }

  canvas.addEventListener('pointerup', finishStroke);
  canvas.addEventListener('pointercancel', finishStroke);
  canvas.addEventListener('pointerout', finishStroke);
  canvas.addEventListener('pointerleave', finishStroke);

  // public helpers for undo/clear etc stored on DOM for external calls
  canvas._undo = ()=> {
    const stacks = lsGet('undoStacks', {}) || {};
    const st = stacks[pageNum] || [];
    if(st.length <= 1) {
      // clear canvas if only one state left
      ctx.clearRect(0,0,canvas.width,canvas.height);
      const map = lsGet(lsKeyBook(<?= (int)$boek_id ?>), {});
      if(map && map[pageNum]) { delete map[pageNum]; lsSet(lsKeyBook(<?= (int)$boek_id ?>), map); }
      return;
    }
    st.pop(); // remove current
    const prev = st[st.length-1];
    const img = new Image();
    img.onload = ()=>{
      ctx.clearRect(0,0,canvas.width,canvas.height);
      ctx.drawImage(img,0,0);
      const map = lsGet(lsKeyBook(<?= (int)$boek_id ?>), {});
      map[pageNum] = canvas.toDataURL();
      lsSet(lsKeyBook(<?= (int)$boek_id ?>), map);
      lsSet('undoStacks', stacks);
    };
    img.src = prev;
  };

  canvas._clear = ()=> {
    ctx.clearRect(0,0,canvas.width,canvas.height);
    const map = lsGet(lsKeyBook(<?= (int)$boek_id ?>), {});
    if(map && map[pageNum]) { delete map[pageNum]; lsSet(lsKeyBook(<?= (int)$boek_id ?>), map); }
    const stacks = lsGet('undoStacks', {}) || {};
    stacks[pageNum] = [];
    lsSet('undoStacks', stacks);
  };

  // ensure initial empty state in undo stack
  const stacks = lsGet('undoStacks', {}) || {};
  if(!(stacks[pageNum] && stacks[pageNum].length)) {
    const blank = canvas.toDataURL();
    stacks[pageNum] = [blank];
    lsSet('undoStacks', stacks);
  }
}

/* convert pointer event to canvas pixel coords */
function toCanvasCoords(ev, canvas){
  const rect = canvas.getBoundingClientRect();
  const x = (ev.clientX - rect.left) * (canvas.width / rect.width);
  const y = (ev.clientY - rect.top) * (canvas.height / rect.height);
  return {x,y};
}

/* apply current scale to pageInner (img + canvas) preserving center */
function updateScaleOnPage(){
  pageInner.style.transform = `scale(${scale})`;
  const canvas = pageInner.querySelector('canvas.overlay-canvas');
  const img = pageInner.querySelector('img');
  if(!img || !canvas) return;
  const displayW = img.getBoundingClientRect().width;
  const displayH = img.getBoundingClientRect().height;
  canvas.style.width = displayW + 'px';
  canvas.style.height = displayH + 'px';
}

/* helper rgba from hex */
function rgbaFromHex(hex, a=0.4){
  const c = hex.replace('#','');
  const num = parseInt(c,16);
  const r = (num >> 16) & 255;
  const g = (num >> 8) & 255;
  const b = num & 255;
  return `rgba(${r},${g},${b},${a})`;
}

/* navigation helpers */
function goToPage(n){
  let targetPage;
  if(n === 's' || n === 'S') {
    targetPage = 's';
  } else {
    // Force numeric pages to be at least 1
    targetPage = Math.max(1, Math.floor(Number(n))); 
  }

  // Check bounds
  const pf = (currentBook && currentBook.pageFiles) ? currentBook.pageFiles : {};
  const targetKey = pageKeyForJson(targetPage);

  if (targetPage !== 's' && (targetPage > totalPages || targetPage < 1)) {
    toast('Ongeldig paginanummer (1-' + totalPages + ')');
    return;
  }
  
  if (!(targetKey in pf)) {
    toast('Pagina is niet beschikbaar.');
    return;
  }

  if(targetPage === currentPage) return;
  currentPage = targetPage;
  renderPage(targetPage);
}

function setActiveTool(btnId){
    document.querySelectorAll('.leftbar .btn').forEach(b => b.classList.remove('active'));
    const el = document.getElementById(btnId);
    if(el) el.classList.add('active');
    // Hide palette if pan/erase is selected
    const pal = document.getElementById('paletteFloat');
    if(pal){
        pal.style.display = (btnId === 'drawBtn' || btnId === 'highlightBtn') ? 'flex' : 'none';
    }
}


/* UI wiring */
document.getElementById('backBtn').onclick = ()=> { window.location.href='dashboard.php'; };
document.getElementById('zoomIn').onclick = ()=> { scale = Math.min(MAX_SCALE, +(scale + 0.15).toFixed(2)); updateScaleOnPage(); };
document.getElementById('zoomOut').onclick = ()=> { scale = Math.max(MIN_SCALE, +(scale - 0.15).toFixed(2)); updateScaleOnPage(); };
document.getElementById('drawBtn').onclick = ()=> { mode='draw'; setActiveTool('drawBtn'); toast('Tekenen actief'); };
document.getElementById('highlightBtn').onclick = ()=> { mode='highlight'; setActiveTool('highlightBtn'); toast('Markeren actief'); };
document.getElementById('eraseBtn').onclick = ()=> { mode='erase'; setActiveTool('eraseBtn'); toast('Gummen actief'); };
document.getElementById('panBtn').onclick = ()=> { mode='pan'; setActiveTool('panBtn'); toast('Panstand (houd spatie en sleep)'); };
document.getElementById('undoBtn').onclick = ()=> {
  const c = pageInner.querySelector('canvas.overlay-canvas');
  if(c && c._undo) c._undo();
  toast('Ongedaan (pagina)');
};
document.getElementById('clearBtn').onclick = ()=> {
  const map = lsGet(lsKeyBook(<?= (int)$boek_id ?>), {});
  if(map && Object.keys(map).length){
    if(confirm('Weet je zeker dat je alle aantekeningen van dit boek wilt verwijderen?')) {
      localStorage.removeItem(lsKeyBook(<?= (int)$boek_id ?>));
      lsSet('undoStacks', {});
      const c = pageInner.querySelector('canvas.overlay-canvas'); if(c && c._clear) c._clear();
      toast('Alle aantekeningen verwijderd');
    }
  } else toast('Geen aantekeningen gevonden');
};
document.getElementById('chaptersBtn').onclick = ()=>{
  const p = document.getElementById('chaptersPanel');
  p.style.display = (p.style.display === 'block')? 'none':'block';
};
document.getElementById('notesBtn').onclick = ()=>{
  const sp = document.getElementById('sidePanel');
  const title = document.getElementById('sideTitle');
  const content = document.getElementById('sideContent');
  if(sp.style.display === 'block'){ sp.style.display='none'; return; }
  sp.style.display='block';
  title.textContent = 'Notities & bladwijzers';
  const notes = lsGet(lsKeyNotes(<?= (int)$boek_id ?>), {});
  const bms = lsGet(lsKeyBookmarks(<?= (int)$boek_id ?>), {});
  content.innerHTML = '';
  if(Object.keys(bms||{}).length){
    const h = document.createElement('div'); h.style.marginBottom='8px'; h.textContent='Bladwijzers:'; content.appendChild(h);
    Object.keys(bms).sort((a,b)=>a-b).forEach(p=>{
      const btn = document.createElement('div'); 
      btn.style.cursor='pointer'; 
      btn.textContent = (p === 's') ? 'Startpagina' : `Pagina ${p}`; 
      btn.onclick = ()=>{ goToPage((p === 's') ? 's' : Number(p)); sp.style.display='none'; };
      btn.style.padding='6px'; btn.style.borderRadius='8px'; btn.style.background='rgba(0,0,0,0.03)'; btn.style.marginBottom='6px';
      content.appendChild(btn);
    });
  }
  if(Object.keys(notes||{}).length){
    const h2 = document.createElement('div'); h2.style.marginTop='6px'; h2.style.marginBottom='8px'; h2.textContent='Notities:'; content.appendChild(h2);
    Object.entries(notes).forEach(([p,t])=>{
      const d = document.createElement('div');
      d.style.padding='8px'; d.style.borderRadius='8px'; d.style.background='rgba(0,0,0,0.02)';
      d.style.marginBottom='8px';
      const head = document.createElement('div'); head.style.fontWeight=600; head.textContent = (p === 's') ? 'Startpagina' : `Pagina ${p}`;
      const body = document.createElement('div'); body.textContent = t; body.style.whiteSpace='pre-wrap';
      d.appendChild(head); d.appendChild(body);
      d.onclick = ()=>{ goToPage((p === 's') ? 's' : Number(p)); sp.style.display='none' };
      content.appendChild(d);
    });
  }
  if(!Object.keys(bms||{}).length && !Object.keys(notes||{}).length) content.textContent = 'Nog geen notities of bladwijzers.';
};
document.getElementById('bookmarkBtn').onclick = ()=>{
  const bms = lsGet(lsKeyBookmarks(<?= (int)$boek_id ?>), {});
  // Use current page identifier ('s' or number)
  if(!bms[currentPage]) bms[currentPage] = Date.now();
  else delete bms[currentPage];
  lsSet(lsKeyBookmarks(<?= (int)$boek_id ?>), bms);
  const pageLabel = (currentPage === 's') ? 'Startpagina' : `p.${currentPage}`;
  toast(bms[currentPage]? `Bladwijzer toegevoegd (${pageLabel})` : `Bladwijzer verwijderd (${pageLabel})`);
};
document.getElementById('themeBtn').onclick = ()=>{
  document.body.classList.toggle('dark-mode');
  localStorage.setItem('viewer_theme', document.body.classList.contains('dark-mode')? 'dark':'light');
};

/* page number input: now text to support 's' */
pageNumberInput.addEventListener('change', e=> {
  const v = e.target.value.trim();
  if(v === 's' || v === 'S') {
    goToPage('s');
  } else {
    const num = Number(v);
    if(!Number.isFinite(num) || num < 1 || num > totalPages) {
      toast('Ongeldig paginanummer');
      // restore current
      pageNumberInput.value = currentPage;
      return;
    }
    goToPage(Math.floor(num));
  }
});

document.getElementById('prevSmall').onclick = ()=> {
  if (currentPage === 's') return; // Cannot go back from startpage
  
  if (currentPage === 1) {
    // Van pagina 1 naar 's'
    const pf = (currentBook && currentBook.pageFiles) ? currentBook.pageFiles : {};
    if (String(0) in pf) { // Check if 's' exists (internal key '0')
        goToPage('s');
    }
    return;
  }
  
  goToPage(Number(currentPage) - 1);
};
document.getElementById('nextSmall').onclick = ()=> {
  if(currentPage === 's') {
    // Van 's' naar pagina 1
    goToPage(1); 
  } else {
      if(Number(currentPage) >= totalPages) return;
      goToPage(Number(currentPage) + 1);
  }
};

/* keyboard nav: left/right page & space handling */
window.addEventListener('keydown', (e)=>{
  if(e.key === 'ArrowLeft') {
      e.preventDefault();
      
      if(currentPage === 's') return;

      if(Number(currentPage) === 1) { // from page 1, go to startpage 's' if it exists
          const pf = (currentBook && currentBook.pageFiles) ? currentBook.pageFiles : {};
          if(String(0) in pf) goToPage('s');
          return;
      }
      
      goToPage(Number(currentPage) - 1);
  }
  if(e.key === 'ArrowRight') {
    e.preventDefault();
    
    if(currentPage === 's') {
        goToPage(1); // from startpage, go to page 1
    } else {
        if(Number(currentPage) >= totalPages) return;
        goToPage(Number(currentPage) + 1);
    }
  }
  if (e.code === 'Space') {
    // Spatie ingedrukt: activeer tijdelijk panmodus
    if (mode !== 'pan') {
      document.body.dataset.prevMode = mode;
      mode = 'pan';
      setActiveTool('panBtn');
      toast('Panmodus actief (spatie ingedrukt)', 800);
    }
    // prevent page scroll when focusing input etc
    e.preventDefault();
  }
});

window.addEventListener('keyup', (e) => {
  if (e.code === 'Space') {
    // Terug naar vorige modus na loslaten
    if (document.body.dataset.prevMode) {
      const prev = document.body.dataset.prevMode;
      mode = prev;
      delete document.body.dataset.prevMode;
      // try set button id like 'drawBtn' etc, otherwise clear highlight
      const btnId = (mode === 'pan' ? 'panBtn' : (mode === 'draw' ? 'drawBtn' : (mode === 'highlight' ? 'highlightBtn' : (mode === 'erase' ? 'eraseBtn' : null))));
      if(btnId) setActiveTool(btnId);
      toast('Pan uit', 600);
    }
  }
});

/* panning: hold space or mode === 'pan' and drag on viewport we pan */
let isPanning = false, panStart = {x:0,y:0}, scrollStart = {x:0,y:0};
viewport.addEventListener('pointerdown', (ev)=>{
  // decide to pan: mode === 'pan' OR space held
  const spaceHeld = (document.body.dataset.prevMode !== undefined);
  const wantPan = (mode === 'pan') || spaceHeld || ev.button === 1 || ev.shiftKey;
  if(!wantPan) return;
  isPanning = true;
  panStart = {x: ev.clientX, y: ev.clientY};
  scrollStart = { x: viewport.scrollLeft, y: viewport.scrollTop };
  viewport.setPointerCapture(ev.pointerId);
  viewport.style.cursor = 'grabbing';
});
viewport.addEventListener('pointermove', (ev)=>{
  if(!isPanning) return;
  const dx = ev.clientX - panStart.x;
  const dy = ev.clientY - panStart.y;
  viewport.scrollLeft = scrollStart.x - dx;
  viewport.scrollTop = scrollStart.y - dy;
});
viewport.addEventListener('pointerup', (ev)=>{ isPanning=false; viewport.style.cursor='auto'; });
viewport.addEventListener('pointercancel', ()=>{ isPanning=false; viewport.style.cursor='auto'; });

/* Undo global button uses current canvas if present */
document.getElementById('undoBtn').addEventListener('click', ()=>{
  const c = pageInner.querySelector('canvas.overlay-canvas');
  if(c && c._undo) c._undo();
});


/* when entering page we need to update scale for new image */
window.addEventListener('resize', ()=> updateScaleOnPage());

/* load saved theme */
if(localStorage.getItem('viewer_theme') === 'dark') document.body.classList.add('dark-mode');

/* load JSON and start */
loadEbookJson();

</script>
</body>
</html>