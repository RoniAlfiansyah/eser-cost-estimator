"use strict";

const DAY_MS = 86400000;
const state = {
  user: null, csrf: "", projects: [], activeId: null, project: null,
  master: {wbs: [], activities: [], personnel: [], equipment: [], costCategories: [], canViewRates:false},
  selectedId: null, editingId: null, view: "activities", layout: "all",
  query: "", wbs: "", status: "", scale: "day", zoom: "normal", ganttFocus: false, ganttEdit: null, sidebarCollapsed: false, columns: {pic:true, predecessor:true}, saveTimer: null,
  pendingRestore: null, confirmAction: null, editingDeploymentId: null, costSnapshots: [], draggedActivityId: null, pendingDragDependency: null, dragLinkStart: null
};
const schedulerViews = ["overview","activities","relationships","resources","calendar","baselines","reports","master","users"];
const masterUi = {
  tab: "wbs",
  filters: {wbsSearch:"",activitySearch:"",activityWbs:"",personnelSearch:"",personnelStatus:"",equipmentSearch:"",equipmentCategory:"",equipmentStatus:""}
};
const schedulerParams = typeof window !== "undefined" ? new URLSearchParams(window.location.search) : new URLSearchParams();
const embeddedMode = schedulerParams.get("embedded") === "1";
const requestedView = schedulerParams.get("view");
if (schedulerViews.includes(requestedView)) state.view = requestedView;
if (embeddedMode && typeof document !== "undefined") document.body.classList.add("scheduler-embedded");

const $ = selector => document.querySelector(selector);
const $$ = selector => [...document.querySelectorAll(selector)];
const esc = value => String(value ?? "").replace(/[&<>'"]/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"}[c]));
const isoToday = () => new Date().toISOString().slice(0, 10);
const parseDate = value => new Date(`${value}T00:00:00Z`);
const toISO = date => new Date(date).toISOString().slice(0, 10);
const addCalendarDays = (value, amount) => { const date = parseDate(typeof value === "string" ? value : toISO(value)); date.setUTCDate(date.getUTCDate() + amount); return date; };
const dayDiff = (a, b) => Math.round((parseDate(typeof b === "string" ? b : toISO(b)) - parseDate(typeof a === "string" ? a : toISO(a))) / DAY_MS);
const formatDate = value => value ? new Intl.DateTimeFormat("id-ID", {day:"2-digit",month:"short",year:"numeric",timeZone:"UTC"}).format(parseDate(value)) : "—";
const clone = value => JSON.parse(JSON.stringify(value));
const canEdit = () => state.user && state.user.role !== "viewer";
function suggestWbsPrefix(name) {
  const upper=String(name||"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toUpperCase();
  const known=[["MARINE GEOPHYSICAL","MGEO"],["LAND GEOPHYSICAL","GEO"],["GEOTECH","GTECH"],["GEODETIC","GEOD"],["OCEAN","OCE"],["HYDRO","HYD"],["TOPO","TOPO"],["AERIAL","AERO"],["PHOTOGRAM","AERO"],["DATA PROCESS","PROC"],["PREPAR","PREP"]];
  const matched=known.find(([needle])=>upper.includes(needle));if(matched)return matched[1];
  const words=upper.split(/[^A-Z0-9]+/).filter(word=>word&&!['PROJECT','SURVEY','AND','OF','THE'].includes(word));
  if(!words.length)return"WBS";const candidate=words.length===1?words[0].slice(0,3):words.slice(0,4).map(word=>word[0]).join("");return candidate.padEnd(2,"X").slice(0,8);
}
function nextActivityId(prefix,ids=(state.project?.activities||[]).map(activity=>activity.id)) {
  const clean=String(prefix||"").toUpperCase().replace(/[^A-Z0-9]/g,"");if(!clean)return"";
  const escaped=clean.replace(/[.*+?^${}()|[\]\\]/g,"\\$&"),pattern=new RegExp(`^${escaped}-(\\d+)$`,`i`),numbers=ids.map(value=>String(value?.activity_code||value?.id||value)).map(value=>value.match(pattern)).filter(Boolean).map(match=>Number(match[1])).filter(Number.isFinite);
  const next=numbers.length?(Math.floor(Math.max(...numbers)/10)+1)*10:10;return`${clean}-${String(next).padStart(3,"0")}`;
}
function saveColumnPreference() { try { localStorage.setItem("eserSchedulerColumns",JSON.stringify(state.columns)); } catch {} }
function applySidebarState() {
  const shell = $(".app-shell"), button = $("#sidebarToggle"), expanded = !state.sidebarCollapsed;
  shell.classList.toggle("sidebar-collapsed",state.sidebarCollapsed);
  document.body.classList.toggle("drawer-open",expanded && window.matchMedia("(max-width: 760px)").matches);
  button.setAttribute("aria-expanded",String(expanded));
  button.setAttribute("aria-label",expanded ? "Tutup menu navigasi" : "Buka menu navigasi");
  button.title = expanded ? "Tutup menu navigasi" : "Buka menu navigasi";
}
function saveSidebarPreference() { try { localStorage.setItem("eserSchedulerSidebarCollapsed",JSON.stringify(state.sidebarCollapsed)); } catch {} }
function setupEmbeddedMode() {
  if (!embeddedMode) return;
  const toolbar=$(".schedule-toolbar");
  if(toolbar&&!$("#embeddedLayoutSelect")){
    toolbar.insertAdjacentHTML("afterbegin",'<label>Layout <select id="embeddedLayoutSelect"><option value="all">Activity + Gantt</option><option value="critical">Critical Path</option><option value="lookahead">Two Week Lookahead</option></select></label>');
    $("#embeddedLayoutSelect").value=state.layout;
    $("#embeddedLayoutSelect").onchange=event=>{state.layout=event.target.value;renderActivities();};
  }
  window.addEventListener("message",event=>{
    if(event.source!==window.parent)return;
    const message=event.data||{};
    if(message.type!=="eser-scheduler-navigate"||!schedulerViews.includes(message.view))return;
    state.view=message.view;
    state.ganttFocus=false;
    render();
  });
}
let pendingRequests = 0;
function setNetworkBusy(change) { pendingRequests=Math.max(0,pendingRequests+change); $("#loadingBar").hidden=pendingRequests===0; }

function toast(message, tone = "success") {
  const element = $("#toast"); element.textContent = message; element.className = `toast ${tone}`; element.hidden = false;
  clearTimeout(toast.timer); toast.timer = setTimeout(() => { element.hidden = true; }, 3200);
}

async function api(action, options = {}) {
  setNetworkBusy(1);
  try {
    const response = await fetch(`../api/scheduler?action=${encodeURIComponent(action)}${options.query || ""}`, {
      method: options.method || "GET",
      headers: {"Content-Type":"application/json", ...(state.csrf ? {"X-CSRF-Token":state.csrf} : {})},
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: "same-origin"
    });
    let payload; try { payload = await response.json(); } catch { throw new Error("Server API tidak aktif atau respons tidak valid."); }
    if (!response.ok || !payload.ok) throw new Error(payload.error || `HTTP ${response.status}`);
    return payload;
  } finally { setNetworkBusy(-1); }
}

function sampleProject(name = "Survey Hidrografi Teluk Aruna") {
  return {
    version: 3, name, dataDate: isoToday(), displayMode:"date", projectStartDate:"2026-08-25", calendar: {name:"Kalender 6 Hari", workingDays:[1,2,3,4,5,6]},
    baseline: null,
    activities: [
      {id:"A1010",wbs:"1.1",name:"Mobilisasi personel & peralatan",duration:4,pic:"Arif Rahman",predecessors:[],start:"2026-08-25",status:"In progress",progress:40},
      {id:"A1020",wbs:"1.2",name:"Persiapan lapangan",duration:5,pic:"Nisa Safitri",predecessors:[{id:"A1010",type:"FS",lag:0}],start:"2026-08-29",status:"Not started",progress:0},
      {id:"A1030",wbs:"2.1",name:"Instalasi stasiun pasang surut",duration:3,pic:"Bayu Wicaksono",predecessors:[{id:"A1020",type:"FS",lag:0}],start:"2026-09-03",status:"Not started",progress:0},
      {id:"A1040",wbs:"2.2",name:"Pengamatan pasang surut",duration:14,pic:"Laras Anindita",predecessors:[{id:"A1030",type:"FS",lag:0}],start:"2026-09-06",status:"Not started",progress:0},
      {id:"A1050",wbs:"3.1",name:"Demobilisasi alat",duration:2,pic:"Arif Rahman",predecessors:[{id:"A1040",type:"FS",lag:0}],start:"2026-09-20",status:"Not started",progress:0}
    ]
  };
}

function emptyProject(name = "Project Baru") {
  return {
    version: 3,
    name,
    dataDate: isoToday(),
    displayMode: "relative",
    projectStartDate: null,
    calendar: {name:"Kalender 6 Hari", workingDays:[1,2,3,4,5,6]},
    baseline: null,
    activities: []
  };
}

const deploymentRateKeys=["daily_rate","standby_rate","travel_rate","field_allowance","accommodation_rate","meal_rate","transport_rate","overtime_rate","mobilization_cost","demobilization_cost","fuel_daily"];
function normalizeDeploymentRateOverride(value){if(!value||typeof value!=="object")return null;return Object.fromEntries(deploymentRateKeys.map(key=>[key,Math.max(0,Number(value[key]||0))]));}

function normalizeProject(raw, name = "Untitled Project") {
  const source = raw && typeof raw === "object" ? clone(raw) : sampleProject(name);
  source.version = 3; source.name = source.name || name; source.dataDate = source.dataDate || isoToday();
  source.calendar = source.calendar || {name:"Kalender 7 Hari",workingDays:[0,1,2,3,4,5,6]};
  source.calendar.workingDays = Array.isArray(source.calendar.workingDays) && source.calendar.workingDays.length ? source.calendar.workingDays.map(Number) : [0,1,2,3,4,5,6];
  source.activities = Array.isArray(source.activities) ? source.activities.map((a, index) => ({
    id: String(a.id || `A${index + 1}`).trim().toUpperCase(), wbs: String(a.wbs || "1"), name: String(a.name || "Aktivitas"),
    duration: Math.max(1, Number(a.duration || 1)), pic: String(a.pic || "Belum ditentukan"),
    predecessors: Array.isArray(a.predecessors) ? a.predecessors.map(r => ({id:String(r.id || r.predecessor || "").toUpperCase(),type:["FS","SS","FF","SF"].includes(r.type || r.relationshipType) ? (r.type || r.relationshipType) : "FS",lag:Number(r.lag || 0)})).filter(r => r.id)
      : [...(a.predecessor ? [{id:a.predecessor,type:a.relationshipType || "FS",lag:Number(a.lag || 0)}] : []), ...((a.extraRelationships || []).map(r => ({id:r.predecessor,type:r.relationshipType || "FS",lag:Number(r.lag || 0)})))],
    start: /^\d{4}-\d{2}-\d{2}$/.test(a.start || "") ? a.start : isoToday(), startConstraint: /^\d{4}-\d{2}-\d{2}$/.test(a.startConstraint || "") ? a.startConstraint : null, status: a.status || "Not started",
    progress: Math.min(100, Math.max(0, Number(a.progress ?? (a.status === "Completed" ? 100 : 0))))
  })) : [];
  source.displayMode = source.displayMode === "relative" ? "relative" : "date";
  source.projectStartDate = /^\d{4}-\d{2}-\d{2}$/.test(source.projectStartDate || "")
    ? source.projectStartDate
    : (source.displayMode === "date" && source.activities.length ? source.activities.reduce((value, activity) => activity.start < value ? activity.start : value, source.activities[0].start) : null);
  source.baseline = source.baseline && Array.isArray(source.baseline.activities) ? source.baseline : null;
  source.deployments = Array.isArray(source.deployments) ? source.deployments.map((item,index)=>({
    id:String(item.id||`DEP-${Date.now()}-${index}`),type:item.type==="equipment"?"equipment":"personnel",resourceId:Number(item.resourceId||0),resourceCode:String(item.resourceCode||""),resourceName:String(item.resourceName||""),
    resourceSource:item.resourceSource==="cost_estimator"?"cost_estimator":"scheduler",costItemIds:item.costItemIds&&typeof item.costItemIds==="object"?Object.fromEntries(Object.entries(item.costItemIds).map(([key,value])=>[key,Number(value||0)])): {},
    quantity:Math.max(.01,Number(item.quantity||1)),wbs:String(item.wbs||"1.0"),periodMode:item.periodMode==="activities"?"activities":"manual",startDay:Math.max(1,Number(item.startDay||1)),endDay:Math.max(1,Number(item.endDay||item.startDay||1)),
    standbyDays:Math.max(0,Number(item.standbyDays||0)),travelDays:Math.max(0,Number(item.travelDays||0)),overtimeHours:Math.max(0,Number(item.overtimeHours||0)),rateOverride:normalizeDeploymentRateOverride(item.rateOverride),activityIds:Array.isArray(item.activityIds)?item.activityIds.map(String):[],notes:String(item.notes||"")
  })) : [];
  return source;
}

function isWorkday(date) { return state.project.calendar.workingDays.includes(date.getUTCDay()); }
function normalizeWorkday(date, direction = 1) { const result = new Date(date); while (!isWorkday(result)) result.setUTCDate(result.getUTCDate() + direction); return result; }
function addWorkdays(value, amount) {
  let date = normalizeWorkday(parseDate(typeof value === "string" ? value : toISO(value)), amount < 0 ? -1 : 1);
  let left = Math.abs(Number(amount)); const direction = amount < 0 ? -1 : 1;
  while (left > 0) { date.setUTCDate(date.getUTCDate() + direction); if (isWorkday(date)) left -= 1; }
  return date;
}
function workdayDistance(from, to) {
  if (from === to) return 0; const direction = parseDate(to) >= parseDate(from) ? 1 : -1; let cursor = parseDate(from); let count = 0;
  while (toISO(cursor) !== to) { cursor.setUTCDate(cursor.getUTCDate() + direction); if (isWorkday(cursor)) count += direction; }
  return count;
}
function finishDate(activity) { return toISO(addWorkdays(activity.start, activity.duration - 1)); }
function scheduleAnchor() { return state.project.projectStartDate ? toISO(normalizeWorkday(parseDate(state.project.projectStartDate))) : "2000-01-03"; }
function activityStartDay(activity) { return workdayDistance(scheduleAnchor(), activity.start) + 1; }
function activityFinishDay(activity) { return workdayDistance(scheduleAnchor(), finishDate(activity)) + 1; }
function relativeTimelineRange(activities) {
  if (!activities.length) return {minimumDay:1, maximumDay:1, days:1};
  const minimumDay=Math.min(...activities.map(activityStartDay)),maximumDay=Math.max(...activities.map(activityFinishDay));
  return {minimumDay,maximumDay,days:Math.max(1,maximumDay-minimumDay+1)};
}
function scheduleLabel(activity) {
  if (state.project.displayMode === "relative") return `Hari ke-${activityStartDay(activity)} – Hari ke-${activityFinishDay(activity)}`;
  return `${formatDate(activity.start)} – ${formatDate(finishDate(activity))}`;
}
function relationshipOffset(predecessor, successor, relationship) {
  const lag = Number(relationship.lag || 0); const type = relationship.type || "FS";
  if (type === "SS") return lag;
  if (type === "FF") return predecessor.duration - successor.duration + lag;
  if (type === "SF") return 1 - successor.duration + lag;
  return predecessor.duration + lag;
}

function dependencyRequiredStart(activity,map=new Map((state.project?.activities||[]).map(item=>[item.id,item]))) {
  let candidate=null;for(const rel of activity.predecessors||[]){const predecessor=map.get(rel.id);if(!predecessor)continue;const date=addWorkdays(predecessor.start,relationshipOffset(predecessor,activity,rel));if(!candidate||date>candidate)candidate=date;}return candidate;
}

function scheduleProject() {
  if (!state.project) return {ok:true,order:[]};
  const activities = state.project.activities; const map = new Map(activities.map(a => [a.id, a]));
  for (const activity of activities) activity.predecessors = activity.predecessors.filter(r => map.has(r.id) && r.id !== activity.id);
  const indegree = new Map(activities.map(a => [a.id, 0])); const successors = new Map(activities.map(a => [a.id, []]));
  for (const activity of activities) for (const rel of activity.predecessors) { indegree.set(activity.id, indegree.get(activity.id) + 1); successors.get(rel.id).push({successor:activity,relationship:rel}); }
  const queue = activities.filter(a => indegree.get(a.id) === 0).sort((a,b) => a.start.localeCompare(b.start)); const order = [];
  while (queue.length) { const current = queue.shift(); order.push(current); for (const edge of successors.get(current.id)) { indegree.set(edge.successor.id, indegree.get(edge.successor.id) - 1); if (indegree.get(edge.successor.id) === 0) queue.push(edge.successor); } }
  if (order.length !== activities.length) return {ok:false,error:"Dependency membentuk siklus."};
  for (const activity of order) {
    if (!activity.predecessors.length) activity.start = toISO(normalizeWorkday(parseDate(activity.start)));
    else {
      let candidate = dependencyRequiredStart(activity,map);
      if(activity.startConstraint){const constrained=normalizeWorkday(parseDate(activity.startConstraint));if(constrained>candidate)candidate=constrained;}
      activity.start = toISO(candidate);
    }
  }
  if (!activities.length) return {ok:true,order};
  const projectEnd = activities.reduce((latest,a) => finishDate(a) > latest ? finishDate(a) : latest, finishDate(activities[0]));
  const latest = new Map(activities.map(a => [a.id, toISO(addWorkdays(projectEnd, -(a.duration - 1)))]));
  for (const activity of [...order].reverse()) for (const edge of successors.get(activity.id)) {
    const candidate = toISO(addWorkdays(latest.get(edge.successor.id), -relationshipOffset(activity, edge.successor, edge.relationship)));
    if (candidate < latest.get(activity.id)) latest.set(activity.id, candidate);
  }
  for (const activity of activities) { activity.totalFloat = Math.max(0, workdayDistance(activity.start, latest.get(activity.id))); activity.critical = activity.totalFloat === 0; activity.finish = finishDate(activity); }
  return {ok:true,order,projectEnd};
}

function filteredActivities() {
  if (!state.project) return []; const now = isoToday(); const lookaheadEnd = toISO(addCalendarDays(now, 14));
  return state.project.activities.filter(a => {
    const haystack = `${a.id} ${a.wbs} ${a.name} ${a.pic}`.toLowerCase();
    const inLookahead = state.project.displayMode === "relative" ? activityFinishDay(a) >= 1 && activityStartDay(a) <= 14 : finishDate(a) >= now && a.start <= lookaheadEnd;
    return (!state.query || haystack.includes(state.query.toLowerCase())) && (!state.wbs || a.wbs === state.wbs) && (!state.status || a.status === state.status) && (state.layout !== "critical" || a.critical) && (state.layout !== "lookahead" || inLookahead);
  });
}

function reorderActivities(activities,sourceId,targetId,placeAfter=false) {
  if(sourceId===targetId)return activities;
  const sourceIndex=activities.findIndex(item=>item.id===sourceId);if(sourceIndex<0||!activities.some(item=>item.id===targetId))return activities;
  const [moved]=activities.splice(sourceIndex,1),targetIndex=activities.findIndex(item=>item.id===targetId),insertAt=targetIndex+(placeAfter?1:0);
  activities.splice(insertAt,0,moved);return activities;
}

function dependencyPath(x1,y1,x2,y2) {
  const clearance=14,entryX=x2-clearance;
  if(entryX>x1+clearance){const middleX=(x1+x2)/2;return`M${x1},${y1} H${middleX} V${y2} H${x2}`;}
  const exitX=x1+clearance,middleY=y1+(y2-y1)/2;
  return`M${x1},${y1} H${exitX} V${middleY} H${entryX} V${y2} H${x2}`;
}

function projectRange(activities = state.project?.activities || []) {
  if (!activities.length) return {start:isoToday(),end:isoToday(),days:1};
  const start = activities.reduce((v,a) => a.start < v ? a.start : v, activities[0].start); const end = activities.reduce((v,a) => finishDate(a) > v ? finishDate(a) : v, finishDate(activities[0]));
  return {start,end,days:Math.max(1,dayDiff(start,end)+1)};
}

function render() {
  applySidebarState();
  document.body.classList.toggle("can-edit",canEdit());
  $(".app-shell").classList.toggle("gantt-focus",state.view==="activities"&&state.ganttFocus);
  if (!state.project) { renderEmptyWorkspace(); return; }
  const result = scheduleProject(); if (!result.ok) toast(result.error,"error");
  syncDeploymentPeriods();
  $("#projectName").textContent = state.project.name; $("#profileButton").textContent = (state.user?.display_name || "?").split(/\s+/).map(v=>v[0]).join("").slice(0,2).toUpperCase();
  $("#securityWarning").hidden=!state.user?.must_change_password;
  $("#usersNav").hidden = state.user?.role !== "admin";
  $("#masterNav").hidden = state.user?.role !== "admin";
  const activities = state.project.activities; const today = isoToday();
  $("#normalCount").textContent = activities.filter(a=>!a.critical).length; $("#criticalCount").textContent = activities.filter(a=>a.critical).length;
  $("#lateCount").textContent = activities.filter(a=>a.status!=="Completed" && finishDate(a)<today).length;
  $$(".main-nav [data-view]").forEach(b=>b.classList.toggle("active",b.dataset.view===state.view));
  $("#activitiesView").hidden = state.view !== "activities"; $("#contentView").hidden = state.view === "activities";
  const titles = {overview:"Project Overview",activities:"Activity Schedule",relationships:"Relationships",resources:"Resources",calendar:"Working Calendar",baselines:"Baselines",reports:"Reports",master:"Master Data",users:"Users & Audit"};
  $("#viewTitle").textContent = titles[state.view] || "EserScheduler";
  const range = projectRange();
  const scheduleRange = state.project.displayMode === "relative" && activities.length
    ? `Hari ke-${Math.min(...activities.map(activityStartDay))} – Hari ke-${Math.max(...activities.map(activityFinishDay))}`
    : `${formatDate(range.start)} – ${formatDate(range.end)}`;
  $("#viewMeta").textContent = `${activities.length} aktivitas · ${scheduleRange} · ${state.project.calendar.name}`;
  $$(".header-actions button").forEach(button => button.hidden = state.view !== "activities" && !["backupButton","restoreButton"].includes(button.id));
  $("#exportCostButton").hidden=state.view!=="resources"||!state.master.canViewRates;
  $("#exportPdfButton").hidden=!["activities","resources"].includes(state.view);
  if (!canEdit()) ["restoreButton","importRpjButton","addActivityButton"].forEach(id => { document.getElementById(id).hidden = true; });
  if (state.view === "activities") renderActivities(); else renderContent();
  if (embeddedMode) window.parent.postMessage({type:"eser-scheduler-view-changed",view:state.view},"*");
}

function renderEmptyWorkspace() {
  $("#projectName").textContent = "Belum ada proyek";
  $("#profileButton").textContent = (state.user?.display_name || "?").split(/\s+/).map(v=>v[0]).join("").slice(0,2).toUpperCase();
  $("#securityWarning").hidden=!state.user?.must_change_password;
  $("#usersNav").hidden = state.user?.role !== "admin";
  $("#masterNav").hidden = state.user?.role !== "admin";
  $("#normalCount").textContent = "0"; $("#criticalCount").textContent = "0"; $("#lateCount").textContent = "0";
  $("#viewTitle").textContent = "Mulai project baru";
  $("#viewMeta").textContent = "Belum ada proyek pada akun ini.";
  $("#activitiesView").hidden = true; $("#contentView").hidden = false;
  $$(".header-actions button").forEach(button => { button.hidden = true; });
  $("#contentPanel").innerHTML = `<div class="content-card empty-workspace"><span class="empty-workspace-icon">＋</span><h2>Workspace masih kosong</h2><p>Buat project baru tanpa aktivitas contoh, lalu tambahkan schedule dari awal atau import file RPJ.</p>${canEdit()?`<button id="emptyNewProject" class="button primary" type="button">Buat project pertama</button>`:""}</div>`;
  $$(".main-nav [data-view]").forEach(button=>button.classList.toggle("active",button.dataset.view===state.view));
  if(state.view==="master"){$("#viewTitle").textContent="Master Data";$("#viewMeta").textContent="Kelola referensi WBS dan Activity ID.";renderMasterData();}
  if(state.view==="users"){$("#viewTitle").textContent="Users & Audit";$("#viewMeta").textContent="Kelola pengguna dan lihat riwayat perubahan.";renderUsers();}
}

function renderActivities() {
  const visible = filteredActivities(); const source = visible.length ? visible : state.project.activities; const allWbs = [...new Set(state.project.activities.map(a=>a.wbs))].sort();
  const activityTable=$(".activity-table"); activityTable.hidden=state.ganttFocus; activityTable.classList.toggle("hide-pic",!state.columns.pic); activityTable.classList.toggle("hide-predecessor",!state.columns.predecessor);
  const focusButton=$("#toggleGanttFocus");focusButton.classList.toggle("active",state.ganttFocus);focusButton.setAttribute("aria-pressed",String(state.ganttFocus));focusButton.textContent=state.ganttFocus?"⊞ Tampilkan tabel":"⛶ Fokus Gantt";
  [["togglePicColumn","pic"],["togglePredecessorColumn","predecessor"]].forEach(([id,column])=>{const button=$("#"+id),visible=state.columns[column];button.classList.toggle("active",visible);button.setAttribute("aria-pressed",String(visible));button.title=visible?`Sembunyikan kolom ${button.textContent.trim()}`:`Tampilkan kolom ${button.textContent.trim()}`;});
  $("#displayModeSelect").value = state.project.displayMode; $("#scaleSelect").value=state.scale; $("#zoomSelect").value=state.zoom; $("#startHeader").textContent = state.project.displayMode === "relative" ? "Hari mulai" : "Mulai"; $("#finishHeader").textContent = state.project.displayMode === "relative" ? "Hari selesai" : "Selesai";
  $("#wbsFilter").innerHTML = `<option value="">Semua</option>${allWbs.map(v=>`<option ${v===state.wbs?"selected":""}>${esc(v)}</option>`).join("")}`;
  const relative = state.project.displayMode === "relative";
  const range = projectRange(source); const relativeRange=relativeTimelineRange(source),minimumDay=relativeRange.minimumDay,maximumDay=relativeRange.maximumDay,relativeDays=relativeRange.days;
  const domainDays = relative ? relativeDays : range.days; const step = state.scale === "week" ? 7 : 1; const columns = Math.max(1, Math.ceil(domainDays / step));
  const dailyWidths={compact:34,normal:52,large:72},weeklyWidths={compact:60,normal:88,large:116}; const columnWidth=(state.scale==="week"?weeklyWidths:dailyWidths)[state.zoom]||52; let timelineWidth=Math.max(480,columns*columnWidth);
  const rootStyle=getComputedStyle(document.documentElement),baseTableWidth=parseFloat(rootStyle.getPropertyValue("--table-width"))||760,tableWidth=state.ganttFocus?0:Math.max(520,baseTableWidth-(state.columns.pic?0:100)-(state.columns.predecessor?0:82)),scheduleGrid=$(".schedule-grid"),ganttPanel=$(".gantt-panel");
  if(state.zoom==="fit"){const available=$(".schedule-scroller").clientWidth-tableWidth;timelineWidth=Math.max(360,available,columns*18);}
  if(state.ganttFocus)timelineWidth=Math.max(timelineWidth,$(".schedule-scroller").clientWidth||0);
  scheduleGrid.style.gridTemplateColumns=`${tableWidth}px ${timelineWidth}px`;scheduleGrid.style.width=`${tableWidth+timelineWidth}px`;scheduleGrid.style.minWidth=`${tableWidth+timelineWidth}px`;ganttPanel.style.width=`${timelineWidth}px`;ganttPanel.style.minWidth=`${timelineWidth}px`;$("#timelineHeader").style.width=`${timelineWidth}px`;$("#timelineBody").style.width=`${timelineWidth}px`;$("#timelineBody").style.backgroundSize=`${timelineWidth/columns}px 100%`;Object.assign($("#timelineBody").dataset,{relative:String(relative),domainDays:String(domainDays),minimumDay:String(minimumDay),rangeStart:range.start});
  $("#timelineHeader").style.gridTemplateColumns = `repeat(${columns}, ${timelineWidth/columns}px)`;
  $("#timelineHeader").innerHTML = Array.from({length:columns},(_,i)=>{
    if (relative) { const first = minimumDay + i * step; const last=Math.min(maximumDay, first + 6); const label=state.scale === "week" ? `${first}–${last}` : `${first}`; const title=state.scale === "week" ? `Hari ${first}–${last}` : `Hari ke-${first}`; return `<span title="${esc(title)}">${esc(label)}</span>`; }
    const date=addCalendarDays(range.start,i*step),label=state.scale==="week"?"Minggu "+weekNumber(date):new Intl.DateTimeFormat("id-ID",{day:"2-digit",month:"short",timeZone:"UTC"}).format(date),fullDate=new Intl.DateTimeFormat("id-ID",{day:"2-digit",month:"long",year:"numeric",timeZone:"UTC"}).format(date); return `<span title="${esc(fullDate)}">${esc(label)}</span>`;
  }).join("");
  $("#activityRows").innerHTML = visible.map((a,index)=>`<button class="activity-row ${a.id===state.selectedId?"selected":""}" data-select="${esc(a.id)}" type="button"><span class="activity-id"><i class="print-sequence">${index+1}. </i>${esc(a.id)}</span><span class="activity-main-cell"><i class="dependency-drag-handle" draggable="true" data-drag-activity="${esc(a.id)}" title="Tarik untuk mengubah urutan baris">⠿</i><span class="activity-copy"><strong class="activity-name ${a.critical?"critical-text":""}" title="${esc(a.name)}">${esc(a.name)}</strong><small>WBS ${esc(a.wbs)} · ${a.progress}%</small></span></span><span>${a.duration}d</span><span title="${esc(a.pic)}">${esc(a.pic)}</span><span title="${esc(a.predecessors.map(formatRel).join(", ")||"Tidak ada predecessor")}">${esc(a.predecessors.map(formatRel).join(", ")||"—")}</span><span>${relative?`Hari ke-${activityStartDay(a)}`:formatDate(a.start)}</span><span>${relative?`Hari ke-${activityFinishDay(a)}`:formatDate(finishDate(a))}</span></button>`).join("");
  $("#ganttRows").innerHTML = visible.map((a,index)=>{ const left=relative?(activityStartDay(a)-minimumDay)/relativeDays*100:dayDiff(range.start,a.start)/range.days*100; const width=relative?Math.max(1,a.duration/relativeDays*100):Math.max(1,(dayDiff(a.start,finishDate(a))+1)/range.days*100); const baseline=state.project.baseline?.activities?.find(b=>b.id===a.id); const baselineStart=baseline?workdayDistance(scheduleAnchor(),baseline.start)+1:0; const baselineFinish=baseline?workdayDistance(scheduleAnchor(),baseline.finish)+1:0; const bleft=baseline?(relative?(baselineStart-minimumDay)/relativeDays*100:dayDiff(range.start,baseline.start)/range.days*100):0; const bwidth=baseline?(relative?Math.max(1,(baselineFinish-baselineStart+1)/relativeDays*100):Math.max(1,(dayDiff(baseline.start,baseline.finish)+1)/range.days*100)):0; return `<div class="gantt-row" data-select="${esc(a.id)}"><span class="gantt-print-label">${index+1}. ${esc(a.id)} - ${esc(a.name)}</span><div class="task-bar ${a.critical?"critical":""}" data-bar="${esc(a.id)}" style="left:${left}%;width:${width}%"><b class="gantt-resize-handle left" data-gantt-resize="left" title="Tarik untuk mengubah awal dan durasi"></b><span>${esc(a.id)}</span><i style="width:${a.progress}%"></i>${canEdit()?`<b class="gantt-dependency-port" draggable="true" data-gantt-link="${esc(a.id)}" title="Tarik ke bar successor"></b><b class="gantt-resize-handle right" data-gantt-resize="right" title="Tarik untuk mengubah durasi"></b>`:""}</div>${baseline?`<div class="baseline-bar" style="left:${bleft}%;width:${bwidth}%"></div>`:""}</div>`; }).join("");
  $$("#ganttRows .task-bar").forEach(bar=>{const activity=visible.find(item=>item.id===bar.dataset.bar);if(activity){bar.dataset.activityTooltip=`${activity.id} — ${activity.name} · ${scheduleLabel(activity)} · ${activity.duration} hari`;bar.title=`Geser untuk memindahkan ${activity.id}`;}});
  const todayLeft=dayDiff(range.start,isoToday())/range.days*100; $("#todayLine").style.left=`${todayLeft}%`; $("#todayLine").hidden=relative||todayLeft<0||todayLeft>100;
  renderDetail(); requestAnimationFrame(drawDependencies);
}

function relationCode(rel) { const lag=Number(rel.lag||0); return `${rel.type}${lag?`${lag>0?"+":""}${lag}d`:""}`; }
function formatRel(rel) { return `${rel.id} ${relationCode(rel)}`; }
function weekNumber(date) { const d=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth(),date.getUTCDate())); d.setUTCDate(d.getUTCDate()+4-(d.getUTCDay()||7)); const y=new Date(Date.UTC(d.getUTCFullYear(),0,1)); return Math.ceil((((d-y)/DAY_MS)+1)/7); }

function renderDetail() {
  const a=state.project.activities.find(item=>item.id===state.selectedId) || state.project.activities[0];if(!a){$("#activityDetail").innerHTML="";return;}state.selectedId=a.id;
  const baseline=state.project.baseline?.activities?.find(b=>b.id===a.id),variance=baseline?dayDiff(baseline.finish,finishDate(a)):null,mappedDate=state.project.displayMode==="relative"&&state.project.projectStartDate?`<small>Tanggal: ${formatDate(a.start)} – ${formatDate(finishDate(a))}</small>`:"",constraintInfo=a.startConstraint?`<small>Manual delay: mulai tidak lebih awal dari ${state.project.displayMode==="relative"?`Hari ke-${workdayDistance(scheduleAnchor(),a.startConstraint)+1}`:formatDate(a.startConstraint)}</small>`:"";
  const predecessorItems=a.predecessors.map(rel=>{const activity=state.project.activities.find(item=>item.id===rel.id);return`<button class="relationship-card-link" data-select="${esc(rel.id)}" type="button"><strong>${esc(rel.id)}</strong><span>${esc(activity?.name||"Aktivitas tidak ditemukan")}</span><em>${esc(relationCode(rel))}</em></button>`;}).join("");
  const successorItems=state.project.activities.flatMap(activity=>activity.predecessors.filter(rel=>rel.id===a.id).map(rel=>({activity,rel}))).map(({activity,rel})=>`<button class="relationship-card-link" data-select="${esc(activity.id)}" type="button"><strong>${esc(activity.id)}</strong><span>${esc(activity.name)}</span><em>${esc(relationCode(rel))}</em></button>`).join("");
  $("#activityDetail").innerHTML=`<article class="detail-card"><div class="card-heading"><h2>${esc(a.id)} · ${esc(a.name)}</h2>${canEdit()?`<button class="text-button" data-edit="${esc(a.id)}">Edit</button>`:""}</div><p>${esc(a.status)} · ${a.progress}% · WBS ${esc(a.wbs)}</p><div class="progress"><i style="width:${a.progress}%"></i></div></article><article class="detail-card relationship-detail-card"><div class="card-heading"><h2>Relationship</h2>${canEdit()?`<button class="text-button" data-rel="${esc(a.id)}">Edit</button>`:""}</div><div class="relationship-card-sections"><section><h3>Predecessor</h3>${predecessorItems||'<span class="relationship-empty">Tidak ada predecessor</span>'}</section><section><h3>Successor</h3>${successorItems||'<span class="relationship-empty">Tidak ada successor</span>'}</section></div><small>Total float: ${a.totalFloat} hari kerja</small></article><article class="detail-card"><h2>Schedule</h2><p>${scheduleLabel(a)}</p>${mappedDate}${constraintInfo}<small>${baseline?`Variance finish: ${variance>0?"+":""}${variance} hari kalender`:"Baseline belum dibuat"}</small></article>`;
}

function drawDependencies() {
  const svg=$("#dependencyLayer"), body=$("#timelineBody"); if (!svg||!body)return; svg.querySelectorAll(".dependency-path").forEach(p=>p.remove());
  const bodyRect=body.getBoundingClientRect(); svg.setAttribute("width",body.scrollWidth); svg.setAttribute("height",body.scrollHeight);
  for(const a of filteredActivities()) for(const rel of a.predecessors){
    const from=body.querySelector(`[data-bar="${CSS.escape(rel.id)}"]`),to=body.querySelector(`[data-bar="${CSS.escape(a.id)}"]`);if(!from||!to)continue;
    const fr=from.getBoundingClientRect(),tr=to.getBoundingClientRect(),x1=fr.right-bodyRect.left+body.scrollLeft,y1=fr.top+fr.height/2-bodyRect.top+body.scrollTop,x2=tr.left-bodyRect.left+body.scrollLeft,y2=tr.top+tr.height/2-bodyRect.top+body.scrollTop,route=dependencyPath(x1,y1,x2,y2);
    const halo=document.createElementNS("http://www.w3.org/2000/svg","path");halo.setAttribute("d",route);halo.setAttribute("class","dependency-path dependency-halo");svg.append(halo);
    const path=document.createElementNS("http://www.w3.org/2000/svg","path");path.setAttribute("d",route);path.setAttribute("class",`dependency-path ${a.critical?"critical":""}`);path.setAttribute("marker-end",`url(#${a.critical?"arrowCritical":"arrowNormal"})`);path.setAttribute("aria-label",`${rel.id} menuju ${a.id}`);svg.append(path);
  }
}

function clearGanttDependencyDrag(){const svg=$("#dependencyLayer");svg?.querySelectorAll(".dependency-draft-path").forEach(path=>path.remove());$$('.task-bar.dependency-bar-target,.task-bar.dependency-bar-source').forEach(item=>item.classList.remove('dependency-bar-target','dependency-bar-source'));state.draggedActivityId=null;state.dragLinkStart=null;}
function drawGanttDependencyDraft(clientX,clientY,targetBar=null){const svg=$("#dependencyLayer"),body=$("#timelineBody");if(!svg||!body||!state.dragLinkStart)return;let path=svg.querySelector(".dependency-draft-path");if(!path){path=document.createElementNS("http://www.w3.org/2000/svg","path");path.setAttribute("class","dependency-draft-path");svg.append(path);}const bodyRect=body.getBoundingClientRect(),targetRect=targetBar?.getBoundingClientRect(),x1=state.dragLinkStart.x,y1=state.dragLinkStart.y,x2=targetRect?targetRect.left-bodyRect.left:clientX-bodyRect.left,y2=targetRect?targetRect.top+targetRect.height/2-bodyRect.top:clientY-bodyRect.top,bend=Math.max(28,Math.abs(x2-x1)*.45);path.setAttribute("d",`M${x1},${y1} C${x1+bend},${y1} ${x2-bend},${y2} ${x2},${y2}`);}

function ganttOffsetDate(value,delta,relative=state.project?.displayMode==="relative"){if(!delta)return value;return relative?toISO(addWorkdays(value,delta)):toISO(normalizeWorkday(addCalendarDays(value,delta),delta<0?-1:1));}
function alignActivityRelationshipsToStart(activity,target,map=new Map((state.project?.activities||[]).map(item=>[item.id,item]))){for(const relationship of activity.predecessors||[]){const predecessor=map.get(relationship.id);if(!predecessor)continue;const calculated=toISO(addWorkdays(predecessor.start,relationshipOffset(predecessor,activity,relationship)));relationship.lag=Number(relationship.lag||0)+workdayDistance(calculated,target);}activity.startConstraint=null;return activity.predecessors;}
function migrateStartConstraintsToRelationshipLags(){if(!state.project)return false;scheduleProject();const map=new Map(state.project.activities.map(item=>[item.id,item]));let changed=false;for(const activity of state.project.activities){if(!activity.startConstraint)continue;if(activity.predecessors.length)alignActivityRelationshipsToStart(activity,activity.start,map);else activity.startConstraint=null;changed=true;}if(changed)scheduleProject();return changed;}
function applyGanttScheduleEdit(edit,delta){
  state.project.activities=clone(edit.activities);const activity=state.project.activities.find(item=>item.id===edit.id);if(!activity)return null;const relative=edit.relative;
  if(edit.mode==="move"){
    const target=ganttOffsetDate(edit.start,delta,relative);
    if(activity.predecessors.length)alignActivityRelationshipsToStart(activity,target);else{activity.start=target;activity.startConstraint=null;}
  }else if(edit.mode==="right"){
    let targetFinish=ganttOffsetDate(edit.finish,delta,relative);if(targetFinish<activity.start)targetFinish=activity.start;activity.duration=Math.max(1,workdayDistance(activity.start,targetFinish)+1);if(activity.predecessors.length)alignActivityRelationshipsToStart(activity,edit.start);
  }else{
    let targetStart=ganttOffsetDate(edit.start,delta,relative);if(targetStart>edit.finish)targetStart=edit.finish;activity.duration=Math.max(1,workdayDistance(targetStart,edit.finish)+1);
    if(activity.predecessors.length)alignActivityRelationshipsToStart(activity,targetStart);else{activity.start=targetStart;activity.startConstraint=null;}
  }
  scheduleProject();syncDeploymentPeriods();return state.project.activities.find(item=>item.id===edit.id);
}
function cancelGanttScheduleEdit(){const edit=state.ganttEdit;if(!edit)return;state.project.activities=clone(edit.activities);state.ganttEdit=null;document.body.classList.remove("gantt-schedule-editing");scheduleProject();renderActivities();}

const money=value=>new Intl.NumberFormat("id-ID",{style:"currency",currency:"IDR",maximumFractionDigits:0}).format(Number(value||0));
function deploymentResources(type){return [...(type==="equipment"?state.master.equipment:state.master.personnel),...(state.master.costProfiles||[]).filter(item=>item.type===type)];}
function resourceForDeployment(deployment) { return deploymentResources(deployment.type).find(item=>item.id===Number(deployment.resourceId))||null; }
function deploymentActivityRange(activityIds,activities=state.project?.activities||[]) { const selected=activities.filter(activity=>(activityIds||[]).includes(activity.id));if(!selected.length)return null;return{startDay:Math.min(...selected.map(activityStartDay)),endDay:Math.max(...selected.map(activityFinishDay))}; }
function syncDeploymentPeriods(){const validIds=new Set((state.project?.activities||[]).map(activity=>activity.id));for(const deployment of state.project?.deployments||[]){deployment.activityIds=(deployment.activityIds||[]).filter(id=>validIds.has(id));if(deployment.periodMode==="manual")continue;const range=deploymentActivityRange(deployment.activityIds);if(range){deployment.startDay=range.startDay;deployment.endDay=range.endDay;}else deployment.periodMode="manual";}}
function deploymentDays(deployment) { const onsite=Math.max(1,Number(deployment.endDay)-Number(deployment.startDay)+1),standby=Math.min(onsite,Math.max(0,Number(deployment.standbyDays||0))),travel=Math.min(onsite-standby,Math.max(0,Number(deployment.travelDays||0)));return{onsite,standby,travel,working:Math.max(0,onsite-standby-travel)}; }
function deploymentCost(deployment,resource=resourceForDeployment(deployment)) {
  const days=deploymentDays(deployment),quantity=Math.max(.01,Number(deployment.quantity||1)),rates=deployment.rateOverride||resource;if(!rates)return{...days,quantity,total:0,base:0,allowances:0,standbyCost:0,travelCost:0,overtime:0,mobDemob:0,fuel:0};
  if(deployment.type==="equipment"){
    const base=quantity*days.working*Number(rates.daily_rate||0),standbyCost=quantity*days.standby*Number(rates.standby_rate||0),travelCost=quantity*days.travel*Number(rates.travel_rate??rates.daily_rate??0),fuel=quantity*days.working*Number(rates.fuel_daily||0),mobDemob=quantity*(Number(rates.mobilization_cost||0)+Number(rates.demobilization_cost||0));return{...days,quantity,base,allowances:0,standbyCost,travelCost,overtime:0,mobDemob,fuel,total:base+standbyCost+travelCost+fuel+mobDemob};
  }
  const base=quantity*days.working*Number(rates.daily_rate||0),standbyCost=quantity*days.standby*Number(rates.standby_rate||0),travelCost=quantity*days.travel*Number(rates.travel_rate??rates.daily_rate??0),allowanceRate=Number(rates.field_allowance||0)+Number(rates.accommodation_rate||0)+Number(rates.meal_rate||0)+Number(rates.transport_rate||0),allowances=quantity*days.onsite*allowanceRate,overtime=quantity*Number(deployment.overtimeHours||0)*Number(rates.overtime_rate||0);return{...days,quantity,base,allowances,standbyCost,travelCost,overtime,mobDemob:0,fuel:0,total:base+allowances+standbyCost+travelCost+overtime};
}
function deploymentConflictIds(deployments=state.project?.deployments||[]) { const conflicts=new Set();for(let i=0;i<deployments.length;i++)for(let j=i+1;j<deployments.length;j++){const a=deployments[i],b=deployments[j];if(a.type===b.type&&Number(a.resourceId)===Number(b.resourceId)&&Number(a.startDay)<=Number(b.endDay)&&Number(b.startDay)<=Number(a.endDay)){conflicts.add(a.id);conflicts.add(b.id);}}return conflicts; }
function calculateCostPlan(deployments=state.project?.deployments||[]) {
  const rows=deployments.map(deployment=>{const resource=resourceForDeployment(deployment),cost=deploymentCost(deployment,resource);return{deployment,resource,cost};}),byWbs={},byActivity={};let personnel=0,equipment=0;
  for(const row of rows){if(row.deployment.type==="equipment")equipment+=row.cost.total;else personnel+=row.cost.total;byWbs[row.deployment.wbs]=(byWbs[row.deployment.wbs]||0)+row.cost.total;const ids=row.deployment.activityIds.length?row.deployment.activityIds:["Tanpa aktivitas"];for(const id of ids)byActivity[id]=(byActivity[id]||0)+row.cost.total/ids.length;}
  return{rows,personnel,equipment,total:personnel+equipment,byWbs,byActivity,createdAt:new Date().toISOString()};
}
function resourceTimelineHtml(deployments){if(!deployments.length)return"";const minimum=Math.min(...deployments.map(item=>item.startDay)),maximum=Math.max(...deployments.map(item=>item.endDay)),days=Math.max(1,maximum-minimum+1),timelineWidth=Math.max(720,days*48),headers=Array.from({length:days},(_,index)=>{const day=minimum+index,date=state.project.projectStartDate?formatDate(toISO(addWorkdays(scheduleAnchor(),day-1))):"";return`<span title="${esc(date)}">Hari ${day}</span>`;}).join(""),rows=deployments.map(item=>{const left=(item.startDay-minimum)/days*100,width=Math.max(1,(item.endDay-item.startDay+1)/days*100);return`<div class="resource-timeline-row"><div><strong>${esc(item.resourceCode)}</strong><small>${esc(item.resourceName)} · ${item.quantity} ${item.type==="equipment"?"unit":"orang"}</small></div><div class="resource-timeline-track" style="width:${timelineWidth}px"><i class="${item.type}" style="left:${left}%;width:${width}%">${item.startDay}–${item.endDay}</i></div></div>`;}).join("");return`<section class="content-card"><h2>Deployment Timeline</h2><div class="resource-timeline-scroller"><div class="resource-timeline" style="width:${220+timelineWidth}px"><div class="resource-timeline-header"><strong>Resource</strong><div style="width:${timelineWidth}px;grid-template-columns:repeat(${days},1fr)">${headers}</div></div>${rows}</div></div></section>`;}

function renderResources() {
  const deployments=state.project.deployments||[],conflicts=deploymentConflictIds(deployments),plan=calculateCostPlan(deployments),personDays=plan.rows.filter(r=>r.deployment.type==="personnel").reduce((sum,row)=>sum+row.cost.quantity*row.cost.onsite,0),unitDays=plan.rows.filter(r=>r.deployment.type==="equipment").reduce((sum,row)=>sum+row.cost.quantity*row.cost.onsite,0);
  const rows=plan.rows.map(({deployment,resource,cost})=>`<tr class="${conflicts.has(deployment.id)?"cost-warning-row":""}"><td><strong>${esc(deployment.resourceCode||resource?.code||"—")}</strong><br><small>${esc(deployment.resourceName||resource?.role_name||resource?.name||"Resource tidak ditemukan")}</small></td><td>${deployment.type==="personnel"?"Personel":"Peralatan"}</td><td>${cost.quantity}</td><td>Hari ${deployment.startDay}–${deployment.endDay}<br><small>${cost.onsite} hari onsite</small></td><td>${cost.working}</td><td>${cost.standby}</td><td>${cost.travel}</td><td>${esc(deployment.wbs)}</td><td>${esc(deployment.activityIds.join(", ")||"—")}</td>${state.master.canViewRates?`<td>${money(cost.total)}</td>`:""}<td>${conflicts.has(deployment.id)?'<span class="warning-chip">Overlap</span>':""}${canEdit()?`<button class="text-button" data-edit-deployment="${esc(deployment.id)}">Edit</button>`:""}</td></tr>`).join("");
  const costSection=state.master.canViewRates?`<section class="content-card"><div class="card-heading"><h2>Basic Cost</h2><div class="card-heading-actions"><button id="saveCostSnapshot" class="button secondary compact" type="button">Simpan snapshot</button><button id="createBasicCost" class="button primary compact" type="button">Lanjutkan ke Basic Cost</button></div></div><div class="metric-grid cost-metrics"><article><span>Personel</span><strong>${money(plan.personnel)}</strong></article><article><span>Peralatan</span><strong>${money(plan.equipment)}</strong></article><article><span>Total direct basic cost</span><strong>${money(plan.total)}</strong></article><article><span>Konflik deployment</span><strong>${conflicts.size}</strong></article></div><div class="cost-breakdown-grid"><div><h3>Biaya per WBS</h3><table><tbody>${Object.entries(plan.byWbs).map(([key,value])=>`<tr><td>WBS ${esc(key)}</td><td>${money(value)}</td></tr>`).join("")||'<tr><td>Belum ada biaya.</td></tr>'}</tbody></table></div><div><h3>Alokasi per aktivitas</h3><table><tbody>${Object.entries(plan.byActivity).map(([key,value])=>`<tr><td>${esc(key)}</td><td>${money(value)}</td></tr>`).join("")||'<tr><td>Belum ada biaya.</td></tr>'}</tbody></table></div></div><h3>Snapshot biaya</h3><div id="costSnapshotList"><small>Memuat...</small></div></section>`:`<section class="content-card"><h2>Basic Cost</h2><p class="field-help">Nilai tarif dan basic cost hanya dapat dilihat oleh Admin. Deployment tetap dapat disusun oleh Planner.</p></section>`;
  $("#contentPanel").innerHTML=`<div class="metric-grid"><article><span>Deployment personel</span><strong>${deployments.filter(item=>item.type==="personnel").length}</strong></article><article><span>Deployment alat</span><strong>${deployments.filter(item=>item.type==="equipment").length}</strong></article><article><span>Person-day</span><strong>${personDays}</strong></article><article><span>Unit-day</span><strong>${unitDays}</strong></article></div><section class="content-card"><div class="card-heading"><div><h2>Resource Deployment Plan</h2><p class="section-help">Working = durasi onsite dikurangi standby dan perjalanan. Resource yang sama dan overlap akan diberi peringatan.</p></div>${canEdit()?'<div class="card-heading-actions"><button class="button secondary compact" data-add-deployment="personnel">＋ Personel</button><button class="button primary compact" data-add-deployment="equipment">＋ Peralatan</button></div>':""}</div><div class="master-table"><table><thead><tr><th>Resource</th><th>Jenis</th><th>Qty</th><th>Periode</th><th>Working</th><th>Standby</th><th>Travel</th><th>WBS</th><th>Aktivitas</th>${state.master.canViewRates?"<th>Basic cost</th>":""}<th></th></tr></thead><tbody>${rows||`<tr><td colspan="${state.master.canViewRates?11:10}" class="empty-cell">Belum ada deployment personel atau peralatan.</td></tr>`}</tbody></table></div></section>${costSection}`;
  $("#contentPanel").insertAdjacentHTML("beforeend",resourceTimelineHtml(deployments));if(state.master.canViewRates)loadCostSnapshots();
}
async function loadCostSnapshots(){try{const result=await api("cost_snapshots",{query:`&projectId=${state.activeId}`});state.costSnapshots=result.snapshots||[];const host=$("#costSnapshotList");if(host)host.innerHTML=state.costSnapshots.length?`<table><thead><tr><th>Nama</th><th>Total</th><th>Dibuat</th><th></th></tr></thead><tbody>${state.costSnapshots.map(item=>`<tr><td>${esc(item.name)}</td><td>${money(item.data.total)}</td><td>${esc(item.created_at)} · ${esc(item.created_by_name)}</td><td><button class="text-button danger-text" data-delete-cost-snapshot="${item.id}">Hapus</button></td></tr>`).join("")}</tbody></table>`:"<small>Belum ada snapshot biaya.</small>";}catch(error){const host=$("#costSnapshotList");if(host)host.textContent=error.message;}}

function renderContent() {
  const activities=state.project.activities, range=projectRange(), completed=activities.filter(a=>a.status==="Completed").length, avg=activities.length?Math.round(activities.reduce((s,a)=>s+a.progress,0)/activities.length):0;
  if(state.view==="overview") $("#contentPanel").innerHTML=`<div class="metric-grid"><article><span>Total activities</span><strong>${activities.length}</strong></article><article><span>Progress rata-rata</span><strong>${avg}%</strong></article><article><span>Completed</span><strong>${completed}</strong></article><article><span>Critical</span><strong>${activities.filter(a=>a.critical).length}</strong></article></div><div class="content-card"><h2>Project summary</h2><dl class="summary-list"><div><dt>Mode schedule</dt><dd>${state.project.displayMode==="relative"?"Hari ke":"Tanggal"}</dd></div><div><dt>Rentang proyek</dt><dd>${activities.length?scheduleLabel({start:range.start,duration:workdayDistance(range.start,range.end)+1}):"—"}</dd></div><div><dt>Tanggal mulai proyek</dt><dd>${state.project.projectStartDate?formatDate(state.project.projectStartDate):"Belum ditentukan"}</dd></div><div><dt>Calendar</dt><dd>${esc(state.project.calendar.name)}</dd></div><div><dt>Baseline</dt><dd>${state.project.baseline?esc(state.project.baseline.name):"Belum dibuat"}</dd></div></dl></div>`;
  if(state.view==="relationships") $("#contentPanel").innerHTML=`<div class="content-card"><h2>Network relationships</h2><table><thead><tr><th>Successor</th><th>Predecessors</th><th>Mulai</th><th>Float</th><th></th></tr></thead><tbody>${activities.map(a=>`<tr><td>${esc(a.id)} · ${esc(a.name)}</td><td>${esc(a.predecessors.map(formatRel).join(", ")||"—")}</td><td>${state.project.displayMode==="relative"?`Hari ke-${activityStartDay(a)}`:formatDate(a.start)}</td><td>${a.totalFloat}d</td><td>${canEdit()?`<button class="text-button" data-rel="${esc(a.id)}">Edit</button>`:""}</td></tr>`).join("")}</tbody></table></div>`;
  if(state.view==="resources") renderResources();
  if(state.view==="calendar") $("#contentPanel").innerHTML=`<div class="content-card narrow"><h2>Mode dan kalender schedule</h2><label>Format schedule<select id="calendarDisplayMode" ${canEdit()?"":"disabled"}><option value="relative" ${state.project.displayMode==="relative"?"selected":""}>Hari ke</option><option value="date" ${state.project.displayMode==="date"?"selected":""}>Tanggal</option></select></label><label>Tanggal mulai proyek (opsional untuk mode Hari ke)<input id="projectStartDate" type="date" value="${state.project.projectStartDate||""}" ${canEdit()?"":"disabled"}></label><p class="field-help">Hari ke-1 akan dipetakan ke tanggal ini. Aktivitas dan relationship tidak perlu dimasukkan ulang ketika tanggal proyek ditetapkan.</p><label>Nama kalender<input id="calendarName" value="${esc(state.project.calendar.name)}" ${canEdit()?"":"disabled"}></label><fieldset><legend>Hari kerja</legend>${["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"].map((v,i)=>`<label class="day-check"><input type="checkbox" value="${i}" ${state.project.calendar.workingDays.includes(i)?"checked":""} ${canEdit()?"":"disabled"}>${v}</label>`).join("")}</fieldset>${canEdit()?`<button id="saveCalendar" class="button primary" type="button">Simpan & hitung ulang</button>`:""}</div>`;
  if(state.view==="baselines") { const b=state.project.baseline; $("#contentPanel").innerHTML=`<div class="content-card"><h2>Baseline schedule</h2>${b?`<p><strong>${esc(b.name)}</strong><br><small>Dibuat ${formatDate(b.createdAt.slice(0,10))}, ${b.activities.length} aktivitas.</small></p>${canEdit()?`<button id="clearBaseline" class="button danger" type="button">Hapus baseline</button>`:""}`:`<p>Belum ada baseline. Snapshot akan menyimpan tanggal mulai dan selesai saat ini.</p>`}${canEdit()?`<button id="createBaseline" class="button primary" type="button">${b?"Perbarui baseline":"Buat baseline"}</button>`:""}</div>`; }
  if(state.view==="reports") { const rows=activities.map(a=>{const b=state.project.baseline?.activities?.find(x=>x.id===a.id),v=b?dayDiff(b.finish,finishDate(a)):null;return`<tr><td>${esc(a.id)}</td><td>${esc(a.name)}</td><td>${a.progress}%</td><td>${state.project.displayMode==="relative"?`Hari ke-${activityFinishDay(a)}`:formatDate(finishDate(a))}</td><td>${v===null?"—":`${v>0?"+":""}${v}d`}</td><td>${a.critical?"Critical":"Normal"}</td></tr>`;}); $("#contentPanel").innerHTML=`<div class="content-card"><h2>Schedule report</h2><table><thead><tr><th>ID</th><th>Activity</th><th>Progress</th><th>Finish</th><th>Variance</th><th>Path</th></tr></thead><tbody>${rows.join("")}</tbody></table></div>`; }
  if(state.view==="master") renderMasterData();
  if(state.view==="users") renderUsers();
}

async function renderUsers() {
  if(state.user?.role!=="admin")return; $("#contentPanel").innerHTML=`<div class="content-card"><h2>Users</h2><div id="usersTable">Memuat...</div><h3>Tambah pengguna</h3><form id="userForm" class="inline-form"><input name="displayName" placeholder="Nama" required><input name="username" placeholder="Username" required><input name="password" type="password" placeholder="Password min. 8" required><select name="role"><option>planner</option><option>viewer</option><option>admin</option></select><button class="button primary">Tambah</button></form></div><div class="content-card"><h2>Audit log</h2><div id="auditTable">Memuat...</div></div>`;
  try { const [users,auditLog]=await Promise.all([api("users"),api("audit")]); $("#usersTable").innerHTML=`<table><thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th></tr></thead><tbody>${users.users.map(u=>`<tr><td>${esc(u.display_name)}</td><td>${esc(u.username)}</td><td>${esc(u.role)}</td><td>${u.active?"Aktif":"Nonaktif"}</td></tr>`).join("")}</tbody></table>`; $("#auditTable").innerHTML=`<table><thead><tr><th>Waktu</th><th>User</th><th>Action</th><th>Entity</th></tr></thead><tbody>${auditLog.entries.map(e=>`<tr><td>${esc(e.created_at)}</td><td>${esc(e.display_name||"System")}</td><td>${esc(e.action)}</td><td>${esc(e.entity_type)} ${esc(e.entity_id||"")}</td></tr>`).join("")}</tbody></table>`; } catch(error){toast(error.message,"error");}
}

async function loadMasterData() {
  const [result,resources]=await Promise.all([api("master"),api("master_resources")]); state.master={wbs:result.wbs||[],activities:result.activities||[],personnel:resources.personnel||[],equipment:resources.equipment||[],costProfiles:resources.costProfiles||[],costCategories:resources.costCategories||[],canViewRates:Boolean(resources.canViewRates)};
}

function renderMasterData() {
  if(state.user?.role!=="admin")return;
  const wbsRows=state.master.wbs.map(item=>`<tr><td><strong>${esc(item.code)}</strong></td><td><strong>${esc(item.prefix||"—")}</strong></td><td>${esc(item.name)}</td><td>${esc(item.description||"—")}</td><td class="table-actions"><button class="text-button" data-edit-wbs="${item.id}" type="button">Edit</button><button class="text-button danger-text" data-delete-wbs="${item.id}" type="button">Hapus</button></td></tr>`).join("");
  const activityRows=state.master.activities.map(item=>`<tr><td><strong>${esc(item.activity_code)}</strong></td><td>${esc(item.wbs_code)} — ${esc(item.wbs_name)}</td><td>${esc(item.name)}</td><td>${item.default_duration}d</td><td>${esc(item.default_pic||"—")}</td><td class="table-actions"><button class="text-button" data-edit-master-activity="${item.id}" type="button">Edit</button><button class="text-button danger-text" data-delete-master-activity="${item.id}" type="button">Hapus</button></td></tr>`).join("");
  $("#contentPanel").innerHTML=`<div class="field-help master-source-note"><strong>Katalog awal Eser Geosurvey:</strong> ${state.master.wbs.length} kelompok WBS dan ${state.master.activities.length} template aktivitas. Durasi dan PIC adalah nilai awal yang dapat disesuaikan untuk setiap proyek.</div><div class="master-grid"><section class="content-card"><h2>Master WBS</h2><p class="section-help">Prefix dipakai untuk membuat Activity ID otomatis, misalnya OCE-010.</p><form id="masterWbsForm" class="master-form"><input name="id" type="hidden"><label>Kode WBS<input name="code" required maxlength="30" placeholder="Contoh: 4.0"></label><label>Nama WBS<input name="name" required maxlength="120" placeholder="Contoh: Oceanography"></label><label>Prefix Activity ID<input name="prefix" required maxlength="8" pattern="[A-Za-z][A-Za-z0-9]{1,7}" placeholder="OCE" title="2-8 karakter, diawali huruf"></label><label class="full">Keterangan<input name="description" maxlength="250" placeholder="Opsional"></label><div class="dialog-actions full"><button class="button secondary" data-reset-master="wbs" type="button">Bersihkan</button><button class="button primary" type="submit">Simpan WBS</button></div></form><div class="master-table"><table><thead><tr><th>Kode</th><th>Prefix</th><th>Nama</th><th>Keterangan</th><th></th></tr></thead><tbody>${wbsRows||`<tr><td colspan="5" class="empty-cell">Belum ada master WBS.</td></tr>`}</tbody></table></div></section><section class="content-card"><h2>Master Activity ID</h2><p class="section-help">Activity ID disarankan otomatis dari prefix WBS dan tetap dapat diedit.</p><form id="masterActivityForm" class="master-form"><input name="id" type="hidden"><label>WBS<select name="wbsId" required ${state.master.wbs.length?"":"disabled"}><option value="">Pilih WBS</option>${state.master.wbs.map(item=>`<option value="${item.id}">${esc(item.code)} — ${esc(item.name)} (${esc(item.prefix||"-")})</option>`).join("")}</select></label><label>Activity ID<input name="activityCode" required maxlength="30" placeholder="Pilih WBS untuk membuat ID"></label><label class="full">Nama aktivitas<input name="name" required maxlength="160"></label><label>Durasi standar<input name="defaultDuration" type="number" min="1" max="10000" value="1" required></label><label>PIC standar<input name="defaultPic" placeholder="Opsional"></label><div class="dialog-actions full"><button class="button secondary" data-reset-master="activity" type="button">Bersihkan</button><button class="button primary" type="submit" ${state.master.wbs.length?"":"disabled"}>Simpan Activity ID</button></div></form>${state.master.wbs.length?"":`<p class="form-error">Tambahkan minimal satu WBS sebelum membuat master Activity ID.</p>`}<div class="master-table"><table><thead><tr><th>Activity ID</th><th>WBS</th><th>Nama</th><th>Durasi</th><th>PIC</th><th></th></tr></thead><tbody>${activityRows||`<tr><td colspan="6" class="empty-cell">Belum ada master aktivitas.</td></tr>`}</tbody></table></div></section></div>`;
  setupMasterIdAutomation();
  const personnelRows=state.master.personnel.map(item=>`<tr><td><strong>${esc(item.code)}</strong></td><td>${esc(item.role_name)}</td><td>${money(item.daily_rate)}</td><td>${money(item.standby_rate)}</td><td>${money(Number(item.field_allowance||0)+Number(item.accommodation_rate||0)+Number(item.meal_rate||0)+Number(item.transport_rate||0))}</td><td>${item.active?"Aktif":"Nonaktif"}</td><td><button class="text-button" data-edit-personnel="${item.id}">Edit</button><button class="text-button danger-text" data-delete-personnel="${item.id}">Hapus</button></td></tr>`).join("");
  const equipmentRows=state.master.equipment.map(item=>`<tr><td><strong>${esc(item.code)}</strong></td><td>${esc(item.name)}</td><td>${esc(item.brand||"—")} ${esc(item.model||"")}<small>${item.asset_tag?`Asset: ${esc(item.asset_tag)}`:""}${item.specification?` · ${esc(item.specification)}`:""}</small></td><td>${esc(item.category)}</td><td>${money(item.daily_rate)}</td><td>${money(item.standby_rate)}</td><td>${money(Number(item.mobilization_cost||0)+Number(item.demobilization_cost||0))}</td><td>${item.active?"Aktif":"Nonaktif"}</td><td><button class="text-button" data-edit-equipment="${item.id}">Edit</button><button class="text-button danger-text" data-delete-equipment="${item.id}">Hapus</button></td></tr>`).join("");
  $("#contentPanel").insertAdjacentHTML("beforeend",`<section class="content-card"><h2>Master Personel & Rate</h2><p class="section-help">Tarif bersifat internal dan hanya tersedia untuk Admin.</p><form id="masterPersonnelForm" class="master-form resource-rate-form"><input name="id" type="hidden"><label>Kode<input name="code" required placeholder="PERS-HYD"></label><label>Posisi<input name="roleName" required placeholder="Hydrographer"></label><label>Tarif working/hari<input name="dailyRate" type="number" min="0" value="0"></label><label>Tarif standby/hari<input name="standbyRate" type="number" min="0" value="0"></label><label>Field allowance/hari<input name="fieldAllowance" type="number" min="0" value="0"></label><label>Akomodasi/hari<input name="accommodationRate" type="number" min="0" value="0"></label><label>Konsumsi/hari<input name="mealRate" type="number" min="0" value="0"></label><label>Transport lokal/hari<input name="transportRate" type="number" min="0" value="0"></label><label>Overtime/jam<input name="overtimeRate" type="number" min="0" value="0"></label><label class="checkbox-field"><input name="active" type="checkbox" checked> Aktif</label><div class="dialog-actions full"><button class="button secondary" data-reset-rate-form="personnel" type="button">Bersihkan</button><button class="button primary" type="submit">Simpan personel</button></div></form><div class="master-table"><table><thead><tr><th>Kode</th><th>Posisi</th><th>Working</th><th>Standby</th><th>Allowance/hari</th><th>Status</th><th></th></tr></thead><tbody>${personnelRows}</tbody></table></div></section><section class="content-card"><h2>Master Peralatan & Rate</h2><form id="masterEquipmentForm" class="master-form resource-rate-form"><input name="id" type="hidden"><label>Kode<input name="code" required placeholder="EQ-SBES"></label><label>Nama alat<input name="name" required></label><label>Kategori<input name="category" required value="Survey Equipment"></label><label>Tarif working/hari<input name="dailyRate" type="number" min="0" value="0"></label><label>Tarif standby/hari<input name="standbyRate" type="number" min="0" value="0"></label><label>Mobilisasi<input name="mobilizationCost" type="number" min="0" value="0"></label><label>Demobilisasi<input name="demobilizationCost" type="number" min="0" value="0"></label><label>BBM/hari aktif<input name="fuelDaily" type="number" min="0" value="0"></label><label class="checkbox-field"><input name="operatorIncluded" type="checkbox"> Operator termasuk</label><label class="checkbox-field"><input name="active" type="checkbox" checked> Aktif</label><div class="dialog-actions full"><button class="button secondary" data-reset-rate-form="equipment" type="button">Bersihkan</button><button class="button primary" type="submit">Simpan peralatan</button></div></form><div class="master-table"><table><thead><tr><th>Kode</th><th>Peralatan</th><th>Kategori</th><th>Working</th><th>Standby</th><th>Mob+Demob</th><th>Status</th><th></th></tr></thead><tbody>${equipmentRows}</tbody></table></div></section>`);
  const sourceNote=$("#contentPanel .master-source-note");
  if(sourceNote)sourceNote.innerHTML=`<strong>Satu sumber master:</strong> personel, peralatan, kapal, satuan, dan rate dibaca langsung dari Master Cost Estimator (${(state.master.costProfiles||[]).length} profil aktif). Scheduler hanya menyimpan WBS, aktivitas, periode, jumlah, dan override khusus proyek.`;
  const catalogProfiles=state.master.costProfiles||[];
  for(const [type,formId] of [["personnel","masterPersonnelForm"],["equipment","masterEquipmentForm"]]){
    const form=$("#"+formId),panel=form?.closest(".content-card");if(!form||!panel)continue;
    const profiles=catalogProfiles.filter(item=>item.type===type),rows=profiles.map(item=>`<tr><td><strong>${esc(item.code)}</strong></td><td>${esc(item.role_name||item.name)}</td><td>${money(item.daily_rate)}</td><td>${money(item.standby_rate)}</td><td>${type==="personnel"?money(Number(item.field_allowance||0)+Number(item.accommodation_rate||0)+Number(item.meal_rate||0)+Number(item.transport_rate||0)):money(Number(item.mobilization_cost||0)+Number(item.demobilization_cost||0))}</td><td><span class="status-chip">Cost Estimator</span></td></tr>`).join("");
    panel.querySelector("h2").textContent=type==="personnel"?"Personel & Rate":"Peralatan, Kapal & Rate";
    form.insertAdjacentHTML("beforebegin",`<div class="field-help"><strong>Master utama Cost Estimator</strong><br>Data berikut otomatis tersedia saat membuat deployment dan tidak perlu dibuat ulang di Scheduler.</div><div class="master-table"><table><thead><tr><th>Kode</th><th>${type==="personnel"?"Posisi":"Resource"}</th><th>Working</th><th>Standby</th><th>${type==="personnel"?"Allowance/hari":"Mob+Demob"}</th><th>Sumber</th></tr></thead><tbody>${rows||`<tr><td colspan="6" class="empty-cell">Belum ada profil aktif yang sesuai di Master Cost Estimator.</td></tr>`}</tbody></table></div>`);
    const details=document.createElement("details");details.className="scheduler-legacy-master";details.innerHTML=`<summary>Tambah resource baru</summary><p class="section-help">Resource yang disimpan di sini otomatis ditambahkan ke Master Cost Estimator dan langsung tersedia di Scheduler.</p>`;form.before(details);details.append(form);
    const legacyTable=[...panel.querySelectorAll(":scope > .master-table")].at(-1);if(legacyTable)details.append(legacyTable);
  }
  const equipmentForm=$("#masterEquipmentForm"),categoryLabel=equipmentForm.elements.category.closest("label");
  categoryLabel.insertAdjacentHTML("afterend",`<label>Merek<input name="brand" maxlength="80" placeholder="Contoh: Odom atau Teledyne"></label><label>Model<input name="model" maxlength="80" placeholder="Contoh: CV-100 atau T20"></label><label>Asset tag / No. inventaris<input name="assetTag" maxlength="80" placeholder="Opsional"></label><label class="full">Spesifikasi singkat<input name="specification" maxlength="500" placeholder="Frekuensi, konfigurasi sensor, atau catatan unit"></label>`);
  categoryLabel.childNodes[0].nodeValue="Jenis peralatan";
  categoryLabel.insertAdjacentHTML("afterend",`<label>Kategori Basic Cost<select name="costCategoryId" required><option value="">Pilih kategori</option>${state.master.costCategories.map(category=>`<option value="${category.id}">${esc(category.name)}</option>`).join("")}</select><small>Diambil langsung dari Master Kategori Cost Estimator.</small></label>`);
  const legacyEquipmentTable=equipmentForm.closest("details").querySelector(".master-table"),equipmentHeader=legacyEquipmentTable.querySelector("thead tr");equipmentHeader.children[1].insertAdjacentHTML("afterend","<th>Detail unit</th>");
  equipmentHeader.children[3].textContent="Jenis";
  equipmentHeader.children[3].insertAdjacentHTML("afterend","<th>Kategori Basic Cost</th>");
  legacyEquipmentTable.querySelectorAll("tbody tr").forEach((row,index)=>row.children[3]?.insertAdjacentHTML("afterend",`<td>${esc(state.master.equipment[index]?.cost_category_name||"Belum dipetakan")}</td>`));
  $("#masterWbsForm").addEventListener("submit",saveMasterForm);
  $("#masterActivityForm").addEventListener("submit",saveMasterForm);
  setupMasterTabsAndFilters();
}

function masterSearchText(value) {
  return String(value||"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();
}

function masterFilterToolbar(panel,markup) {
  const table=panel.querySelector(".master-table");
  table.insertAdjacentHTML("beforebegin",`<div class="master-list-toolbar">${markup}<span class="master-result-count" data-master-count></span></div><p class="master-filter-empty" hidden>Tidak ada data yang sesuai dengan pencarian atau filter.</p>`);
}

function setupMasterTabsAndFilters() {
  const content=$("#contentPanel"),grid=content.querySelector(".master-grid");
  if(!grid)return;
  const personnelList=[...(state.master.costProfiles||[]).filter(item=>item.type==="personnel"),...state.master.personnel],equipmentList=[...(state.master.costProfiles||[]).filter(item=>item.type==="equipment"),...state.master.equipment];
  const originalPanels=[...grid.children],extraPanels=[...content.children].filter(element=>element.matches(".content-card")&&!grid.contains(element));
  extraPanels.forEach(panel=>grid.append(panel));
  const panels=[...originalPanels,...extraPanels],definitions=[
    ["wbs","WBS",state.master.wbs.length],
    ["activity","Activity",state.master.activities.length],
    ["personnel","Personel & Rate",personnelList.length],
    ["equipment","Peralatan & Rate",equipmentList.length]
  ];
  grid.classList.add("master-tab-panels");
  panels.forEach((panel,index)=>{panel.classList.add("master-tab-panel");panel.dataset.masterPanel=definitions[index][0];panel.setAttribute("role","tabpanel");});
  grid.insertAdjacentHTML("beforebegin",`<div class="master-tabs" role="tablist" aria-label="Kelompok master data">${definitions.map(([key,label,count])=>`<button class="master-tab" type="button" role="tab" data-master-tab="${key}"><span>${label}</span><strong>${count}</strong></button>`).join("")}</div>`);

  const [wbsPanel,activityPanel,personnelPanel,equipmentPanel]=panels;
  masterFilterToolbar(wbsPanel,`<label class="master-search-field"><span>Cari WBS</span><input type="search" data-master-filter="wbsSearch" value="${esc(masterUi.filters.wbsSearch)}" placeholder="Kode, prefix, nama, atau keterangan"></label>`);
  masterFilterToolbar(activityPanel,`<label class="master-search-field"><span>Cari Activity</span><input type="search" data-master-filter="activitySearch" value="${esc(masterUi.filters.activitySearch)}" placeholder="Activity ID, nama, WBS, atau PIC"></label><label><span>Filter WBS</span><select data-master-filter="activityWbs"><option value="">Semua WBS</option>${state.master.wbs.map(item=>`<option value="${item.id}" ${String(item.id)===masterUi.filters.activityWbs?"selected":""}>${esc(item.code)} — ${esc(item.name)}</option>`).join("")}</select></label>`);
  masterFilterToolbar(personnelPanel,`<label class="master-search-field"><span>Cari Personel</span><input type="search" data-master-filter="personnelSearch" value="${esc(masterUi.filters.personnelSearch)}" placeholder="Kode atau posisi personel"></label><label><span>Status</span><select data-master-filter="personnelStatus"><option value="">Semua status</option><option value="active" ${masterUi.filters.personnelStatus==="active"?"selected":""}>Aktif</option><option value="inactive" ${masterUi.filters.personnelStatus==="inactive"?"selected":""}>Nonaktif</option></select></label>`);
  const equipmentCategories=[...new Set(equipmentList.map(item=>item.category||item.category_name).filter(Boolean))].sort((a,b)=>a.localeCompare(b,"id"));
  masterFilterToolbar(equipmentPanel,`<label class="master-search-field"><span>Cari Peralatan</span><input type="search" data-master-filter="equipmentSearch" value="${esc(masterUi.filters.equipmentSearch)}" placeholder="Kode, nama, merek, model, atau kategori"></label><label><span>Jenis</span><select data-master-filter="equipmentCategory"><option value="">Semua jenis</option>${equipmentCategories.map(category=>`<option value="${esc(category)}" ${category===masterUi.filters.equipmentCategory?"selected":""}>${esc(category)}</option>`).join("")}</select></label><label><span>Status</span><select data-master-filter="equipmentStatus"><option value="">Semua status</option><option value="active" ${masterUi.filters.equipmentStatus==="active"?"selected":""}>Aktif</option><option value="inactive" ${masterUi.filters.equipmentStatus==="inactive"?"selected":""}>Nonaktif</option></select></label>`);

  const lists=[state.master.wbs,state.master.activities,personnelList,equipmentList];
  panels.forEach((panel,panelIndex)=>panel.querySelectorAll("tbody tr").forEach((row,rowIndex)=>{
    const item=lists[panelIndex][rowIndex]||{};
    row.dataset.masterRow=definitions[panelIndex][0];
    row.dataset.search=masterSearchText(row.textContent);
    if(panelIndex===1)row.dataset.wbs=String(item.wbs_id||"");
    if(panelIndex>=2)row.dataset.active=item.active?"active":"inactive";
    if(panelIndex===3)row.dataset.category=String(item.category||item.category_name||"");
  }));
  content.querySelectorAll("[data-master-filter]").forEach(control=>control.addEventListener(control.tagName==="INPUT"?"input":"change",()=>{
    masterUi.filters[control.dataset.masterFilter]=control.value;
    applyMasterFilters();
  }));
  content.querySelectorAll("[data-master-tab]").forEach(button=>button.addEventListener("click",()=>selectMasterTab(button.dataset.masterTab)));
  selectMasterTab(masterUi.tab);
}

function selectMasterTab(tab) {
  const allowed=["wbs","activity","personnel","equipment"];
  masterUi.tab=allowed.includes(tab)?tab:"wbs";
  $("#contentPanel").querySelectorAll("[data-master-tab]").forEach(button=>{
    const active=button.dataset.masterTab===masterUi.tab;
    button.classList.toggle("active",active);button.setAttribute("aria-selected",String(active));button.tabIndex=active?0:-1;
  });
  $("#contentPanel").querySelectorAll("[data-master-panel]").forEach(panel=>panel.hidden=panel.dataset.masterPanel!==masterUi.tab);
  applyMasterFilters();
}

function applyMasterFilters() {
  const configurations={
    wbs:{query:"wbsSearch"},
    activity:{query:"activitySearch",secondary:"activityWbs",attribute:"wbs"},
    personnel:{query:"personnelSearch",secondary:"personnelStatus",attribute:"active"},
    equipment:{query:"equipmentSearch",secondary:"equipmentCategory",attribute:"category",tertiary:"equipmentStatus",tertiaryAttribute:"active"}
  };
  $("#contentPanel").querySelectorAll("[data-master-panel]").forEach(panel=>{
    const config=configurations[panel.dataset.masterPanel],query=masterSearchText(masterUi.filters[config.query]),secondary=config.secondary?masterUi.filters[config.secondary]:"",tertiary=config.tertiary?masterUi.filters[config.tertiary]:"",rows=[...panel.querySelectorAll("[data-master-row]")];
    let visible=0;
    rows.forEach(row=>{
      const matchesQuery=!query||row.dataset.search.includes(query),matchesSecondary=!secondary||row.dataset[config.attribute]===secondary,matchesTertiary=!tertiary||row.dataset[config.tertiaryAttribute]===tertiary,show=matchesQuery&&matchesSecondary&&matchesTertiary;
      row.hidden=!show;if(show)visible++;
    });
    const count=panel.querySelector("[data-master-count]"),empty=panel.querySelector(".master-filter-empty");
    if(count)count.textContent=`${visible} dari ${rows.length} data`;
    if(empty)empty.hidden=visible!==0;
  });
}

async function saveMasterForm(event) {
  event.preventDefault();
  event.stopPropagation();
  const form=event.currentTarget,formId=form.getAttribute("id"),button=form.querySelector('[type="submit"]');
  if(button)button.disabled=true;
  state.view="master";
  try {
    const isWbs=formId==="masterWbsForm";
    await api(isWbs?"master_wbs":"master_activity",{method:"POST",body:Object.fromEntries(new FormData(form))});
    await loadMasterData();
    state.view="master";
    render();
    toast(isWbs?"Master WBS disimpan.":"Master Activity ID disimpan.");
  } catch(error) {
    toast(error.message,"error");
    if(button&&button.isConnected)button.disabled=false;
  }
}

function setupMasterIdAutomation() {
  const wbsForm=$("#masterWbsForm"),activityForm=$("#masterActivityForm");
  if(wbsForm){
    const name=wbsForm.elements.name,prefix=wbsForm.elements.prefix;
    name.addEventListener("input",()=>{if(prefix.dataset.edited!=="true")prefix.value=suggestWbsPrefix(name.value);});
    prefix.addEventListener("input",()=>{prefix.dataset.edited="true";prefix.value=prefix.value.toUpperCase().replace(/[^A-Z0-9]/g,"").slice(0,8);});
  }
  if(activityForm){
    const updateSuggestion=()=>{
      if(activityForm.elements.id.value)return;
      const wbs=state.master.wbs.find(item=>item.id===Number(activityForm.elements.wbsId.value));
      activityForm.elements.activityCode.value=wbs?.prefix?nextActivityId(wbs.prefix,state.master.activities):"";
    };
    activityForm.elements.wbsId.addEventListener("change",updateSuggestion);
  }
}

function resetMasterForm(type){const form=document.getElementById(type==="wbs"?"masterWbsForm":"masterActivityForm");if(form){form.reset();form.elements.id.value="";if(type==="wbs")form.elements.prefix.dataset.edited="false";if(type==="activity")form.elements.defaultDuration.value=1;}}

const deploymentRateFormMap={deploymentDailyRate:"daily_rate",deploymentStandbyRate:"standby_rate",deploymentTravelRate:"travel_rate",deploymentFieldAllowance:"field_allowance",deploymentAccommodationRate:"accommodation_rate",deploymentMealRate:"meal_rate",deploymentTransportRate:"transport_rate",deploymentOvertimeRate:"overtime_rate",deploymentMobilizationCost:"mobilization_cost",deploymentDemobilizationCost:"demobilization_cost",deploymentFuelDaily:"fuel_daily"};
function formatCurrencyDigits(value){const digits=String(value??"").replace(/\D/g,"").replace(/^0+(?=\d)/,"");return digits?digits.replace(/\B(?=(\d{3})+(?!\d))/g,"."):"";}
function parseCurrencyAmount(value){const digits=String(value??"").replace(/\D/g,"");const amount=Number(digits||0);return Number.isFinite(amount)?Math.max(0,amount):0;}
function setDeploymentCurrencyValue(input,value){const amount=Math.max(0,Number(value||0));input.value=amount>0?formatCurrencyDigits(String(amount)):"";}
function setupDeploymentCurrencyInputs(){const form=$("#deploymentForm");if(!form.elements.deploymentTravelRate){const label=document.createElement("label");label.innerHTML='Travel / hari<input name="deploymentTravelRate" type="number" min="0" step="1" value="0">';$("#deploymentRateFields .deployment-rate-grid").children[1].after(label);}for(const field of Object.keys(deploymentRateFormMap)){const input=form.elements[field];if(!input||input.closest(".currency-field"))continue;input.type="text";input.inputMode="numeric";input.placeholder="-";input.autocomplete="off";const wrapper=document.createElement("div");wrapper.className="currency-field";const prefix=document.createElement("span");prefix.textContent="Rp";input.before(wrapper);wrapper.append(prefix,input);input.addEventListener("input",()=>{input.value=formatCurrencyDigits(input.value);input.setSelectionRange(input.value.length,input.value.length);});input.addEventListener("blur",()=>{input.value=formatCurrencyDigits(input.value);});}}
function setupDeploymentResourceSearch(){const form=$("#deploymentForm"),select=form.elements.resourceId;if(form.elements.resourceSearch)return;select.required=false;select.classList.add("native-resource-select");const combo=document.createElement("div");combo.className="resource-combobox";combo.innerHTML='<button class="resource-combobox-button" type="button" aria-haspopup="listbox" aria-expanded="false"><span>Pilih resource</span><i>⌄</i></button><div class="resource-combobox-panel" hidden><div class="resource-combobox-search"><span>⌕</span><input type="search" name="resourceSearch" placeholder="Cari resource..." autocomplete="off"></div><div class="resource-combobox-list" role="listbox"></div></div>';select.before(combo);combo.append(select);const button=combo.querySelector(".resource-combobox-button"),panel=combo.querySelector(".resource-combobox-panel"),search=form.elements.resourceSearch;button.addEventListener("click",()=>{const open=panel.hidden;panel.hidden=!open;button.setAttribute("aria-expanded",String(open));if(open){search.focus();search.select();}});search.addEventListener("input",()=>updateDeploymentResourceOptions(select.value));combo.querySelector(".resource-combobox-list").addEventListener("click",event=>{const option=event.target.closest("[data-resource-value]");if(!option)return;const value=option.dataset.resourceValue;search.value="";updateDeploymentResourceOptions(value);panel.hidden=true;button.setAttribute("aria-expanded","false");select.dispatchEvent(new Event("change",{bubbles:true}));});document.addEventListener("click",event=>{if(combo.contains(event.target))return;panel.hidden=true;button.setAttribute("aria-expanded","false");});}
function renderDeploymentResourceDropdown(){const form=$("#deploymentForm"),select=form.elements.resourceId,combo=select.closest(".resource-combobox");if(!combo)return;const list=combo.querySelector(".resource-combobox-list"),selected=select.selectedOptions[0];combo.querySelector(".resource-combobox-button span").textContent=selected&&selected.value?selected.textContent:`Pilih ${form.elements.type.value==="equipment"?"peralatan":"personel"}`;list.innerHTML=[...select.children].map(group=>{if(group.tagName==="OPTION")return group.value?`<button type="button" role="option" data-resource-value="${esc(group.value)}">${esc(group.textContent)}</button>`:"";const items=[...group.children].map(option=>`<button type="button" role="option" data-resource-value="${esc(option.value)}" class="${option.selected?"selected":""}">${esc(option.textContent)}</button>`).join("");return`<div class="resource-combobox-group"><strong>${esc(group.label)}</strong>${items}</div>`;}).join("")||'<p class="resource-combobox-empty">Resource tidak ditemukan.</p>';}
function selectedDeploymentResource(){const form=$("#deploymentForm");return deploymentResources(form.elements.type.value).find(item=>item.id===Number(form.elements.resourceId.value))||null;}
function populateDeploymentRateFields(rateOverride=null){const form=$("#deploymentForm"),type=form.elements.type.value,visible=Boolean(state.master.canViewRates);$("#deploymentRateFields").hidden=!visible;$("#deploymentPersonnelRates").hidden=type==="equipment";$("#deploymentEquipmentRates").hidden=type!=="equipment";if(!visible)return;const source=rateOverride||selectedDeploymentResource()||{};for(const [field,key] of Object.entries(deploymentRateFormMap))setDeploymentCurrencyValue(form.elements[field],source[key]);}
function readDeploymentRateOverride(data){return normalizeDeploymentRateOverride(Object.fromEntries(Object.entries(deploymentRateFormMap).map(([field,key])=>[key,parseCurrencyAmount(data[field])])));}
function updateDeploymentResourceOptions(preferredId=null,rateOverride=null){const form=$("#deploymentForm"),type=form.elements.type.value,query=masterSearchText(form.elements.resourceSearch?.value),matches=item=>!query||masterSearchText([item.code,item.role_name,item.name,item.category_name,item.category,item.brand,item.model].join(" ")).includes(query)||String(item.id)===String(preferredId),list=deploymentResources(type).filter(matches),legacy=list.filter(item=>item.source!=="cost_estimator"&&item.active!==false),catalog=list.filter(item=>item.source==="cost_estimator"&&item.active!==false),options=items=>items.map(item=>{const detail=type==="equipment"&&(`${item.brand||""} ${item.model||""}`.trim())?` · ${item.brand||""} ${item.model||""}`:"";return`<option value="${item.id}">${esc(item.code)} — ${esc(item.role_name||item.name)}${esc(detail)}</option>`;}).join(""),catalogGroups=Object.entries(Object.groupBy?Object.groupBy(catalog,item=>item.category_name||"Lainnya"):catalog.reduce((groups,item)=>{const key=item.category_name||"Lainnya";(groups[key]||=[]).push(item);return groups;},{})).map(([category,items])=>`<optgroup label="Cost Estimator — ${esc(category)}">${options(items)}</optgroup>`).join("");form.elements.resourceId.innerHTML=`<option value="">${query&&!catalog.length&&!legacy.length?"Resource tidak ditemukan":`Pilih ${type==="equipment"?"peralatan":"personel"}`}</option>${catalogGroups}${legacy.length?`<optgroup label="Belum tersinkron">${options(legacy)}</optgroup>`:""}`;if(preferredId!==null&&preferredId!==undefined)form.elements.resourceId.value=String(preferredId);renderDeploymentResourceDropdown();$("#deploymentOvertimeLabel").hidden=type==="equipment";populateDeploymentRateFields(rateOverride);}
function updateDeploymentPeriodFields(){const form=$("#deploymentForm"),automatic=form.elements.periodMode.value==="activities",ids=[...form.elements.activityIds.selectedOptions].map(option=>option.value),range=deploymentActivityRange(ids);form.elements.activityIds.required=automatic;form.elements.startDay.readOnly=automatic;form.elements.endDay.readOnly=automatic;if(automatic&&range){form.elements.startDay.value=range.startDay;form.elements.endDay.value=range.endDay;}form.elements.startDay.title=automatic?"Mengikuti aktivitas paling awal":"Input manual";form.elements.endDay.title=automatic?"Mengikuti aktivitas paling akhir":"Input manual";}
function openDeployment(id=null,type="personnel"){
  if(!canEdit())return;
  state.editingDeploymentId=id;
  const form=$("#deploymentForm"),deployment=(state.project.deployments||[]).find(item=>item.id===id);
  form.reset();form.elements.id.value=id||"";form.elements.type.value=deployment?.type||type;updateDeploymentResourceOptions(deployment?.resourceId,deployment?.rateOverride);
  form.elements.quantity.value=deployment?.quantity||1;form.elements.wbs.value=deployment?.wbs||state.project.activities[0]?.wbs||"1.0";form.elements.periodMode.value=deployment?.periodMode||"activities";
  form.elements.startDay.value=deployment?.startDay||1;form.elements.endDay.value=deployment?.endDay||1;form.elements.standbyDays.value=deployment?.standbyDays||0;form.elements.travelDays.value=deployment?.travelDays||0;form.elements.overtimeHours.value=deployment?.overtimeHours||0;form.elements.notes.value=deployment?.notes||"";
  form.elements.activityIds.innerHTML=state.project.activities.map(activity=>`<option value="${esc(activity.id)}" ${deployment?.activityIds.includes(activity.id)?"selected":""}>${esc(activity.id)} · ${esc(activity.name)}</option>`).join("");
  updateDeploymentPeriodFields();$("#deploymentDialogTitle").textContent=id?"Edit deployment":`Tambah deployment ${type==="equipment"?"peralatan":"personel"}`;$("#deleteDeploymentButton").hidden=!id;$("#deploymentError").textContent="";$("#deploymentDialog").showModal();
}

function setupActivityMasterSelectors(activity=null) {
  const wbsSelect=$("#formWbsSelect"),manualWbs=$("#formWbsManual");
  wbsSelect.innerHTML=`<option value="__manual__">＋ Input WBS manual</option>${state.master.wbs.map(item=>`<option value="${item.id}">${esc(item.code)} — ${esc(item.name)}</option>`).join("")}`;
  const matchedWbs=activity?state.master.wbs.find(item=>item.code.toUpperCase()===String(activity.wbs).toUpperCase()):null;
  wbsSelect.value=matchedWbs?String(matchedWbs.id):"__manual__"; manualWbs.value=activity?.wbs||"";
  updateWbsMasterSelection(activity?.id||null,true);
}

function updateWbsMasterSelection(preferredActivityCode=null,preserveValues=false) {
  const selected=$("#formWbsSelect").value,manual=selected==="__manual__",wbs=state.master.wbs.find(item=>item.id===Number(selected));
  $("#formWbsManualLabel").hidden=!manual; $("#formWbsManual").required=true;
  if(!manual&&wbs)$("#formWbsManual").value=wbs.code;else if(!preserveValues)$("#formWbsManual").value="";
  const available=manual?[]:state.master.activities.filter(item=>item.wbs_id===Number(selected));
  $("#formActivitySelect").innerHTML=`<option value="__manual__">Buat aktivitas baru</option>${available.map(item=>`<option value="${item.id}">${esc(item.activity_code)} — ${esc(item.name)}</option>`).join("")}`;
  const matched=available.find(item=>item.activity_code.toUpperCase()===String(preferredActivityCode||"").toUpperCase());
  $("#formActivitySelect").value=matched?String(matched.id):"__manual__";
  updateActivityMasterSelection(preserveValues);
}

function updateActivityMasterSelection(preserveValues=false) {
  const selected=$("#formActivitySelect").value,manual=selected==="__manual__",item=state.master.activities.find(activity=>activity.id===Number(selected)),form=$("#activityForm"),wbs=state.master.wbs.find(value=>value.id===Number($("#formWbsSelect").value));
  $("#formActivityManualLabel").hidden=!manual; $("#formActivityManual").required=true;
  let masterSyncHint=$("#activityMasterSyncHint");
  if(!masterSyncHint){masterSyncHint=document.createElement("p");masterSyncHint.id="activityMasterSyncHint";masterSyncHint.className="activity-master-sync-hint full";masterSyncHint.textContent="Aktivitas baru ini akan disimpan ke schedule sekaligus didaftarkan ke Master Activity.";form.elements.name.closest("label").before(masterSyncHint);}
  const willSync=manual&&!state.editingId&&Boolean(wbs)&&state.user?.role==="admin";
  masterSyncHint.hidden=!willSync;
  if(item){$("#formActivityManual").value=item.activity_code;$("#formActivityIdLabel").textContent="Activity ID dari master";if(!preserveValues){form.elements.name.value=item.name;form.elements.duration.value=item.default_duration;if(item.default_pic)form.elements.pic.value=item.default_pic;}}
  else {
    $("#formActivityIdLabel").textContent=wbs?.prefix&&!state.editingId?"Activity ID baru otomatis (dapat diedit)":"Activity ID manual";
    if(!preserveValues)$("#formActivityManual").value=!state.editingId&&wbs?.prefix?nextActivityId(wbs.prefix,[...(state.project?.activities||[]),...state.master.activities]):"";
  }
}

function openActivity(id=null) {
  if(!canEdit())return;
  state.editingId=id; const form=$("#activityForm"); form.reset(); form.querySelector('[type="submit"]').disabled=false; $("#activityError").textContent=""; $("#activityDialogTitle").textContent=id?"Edit aktivitas":"Tambah aktivitas"; $("#deleteActivityButton").hidden=!id;
  const relative=state.project.displayMode==="relative"; $("#formStartDayLabel").hidden=!relative; $("#formStartDateLabel").hidden=relative; form.elements.startDay.required=relative; form.elements.start.required=!relative;
  const a=state.project.activities.find(x=>x.id===id); if(a){for(const [key,value] of Object.entries(a)){if(form.elements[key] && !["predecessors","finish","critical","totalFloat"].includes(key))form.elements[key].value=value;}form.elements.startDay.value=activityStartDay(a);}else{form.elements.start.value=state.project.projectStartDate||isoToday();form.elements.startDay.value=1;form.elements.wbs.value="";form.elements.pic.value=state.user.display_name;}
  setupActivityMasterSelectors(a);
  $("#activityDialog").showModal();
}

async function saveActivityForm(event) {
  event.preventDefault();
  const form=event.currentTarget,button=form.querySelector('[type="submit"]'),data=Object.fromEntries(new FormData(form)),id=data.id.trim().toUpperCase();
  $("#activityError").textContent="";
  if(state.project.activities.some(activity=>activity.id===id&&activity.id!==state.editingId)){$("#activityError").textContent=`ID ${id} sudah digunakan.`;return;}
  const relative=state.project.displayMode==="relative",start=relative?toISO(addWorkdays(scheduleAnchor(),Math.max(1,Number(data.startDay||1))-1)):data.start;
  if(!start){$("#activityError").textContent="Tanggal mulai wajib diisi.";return;}
  const old=state.project.activities.find(activity=>activity.id===state.editingId),selectedWbsId=Number($("#formWbsSelect").value),selectedWbs=state.master.wbs.find(item=>item.id===selectedWbsId),manualActivity=$("#formActivitySelect").value==="__manual__",existingMaster=state.master.activities.find(item=>String(item.activity_code).toUpperCase()===id),shouldCreateMaster=!old&&manualActivity&&Boolean(selectedWbs)&&!existingMaster&&state.user?.role==="admin",activity={id,wbs:data.wbs.trim(),name:data.name.trim(),duration:Number(data.duration),pic:data.pic.trim(),predecessors:old?.predecessors||[],start,startConstraint:old?.startConstraint||null,status:data.status,progress:Number(data.progress)};
  if(button)button.disabled=true;
  try {
    let masterSaved=false;
    if(shouldCreateMaster){
      await api("master_activity",{method:"POST",body:{wbsId:selectedWbsId,activityCode:id,name:activity.name,defaultDuration:activity.duration,defaultPic:activity.pic}});
      await loadMasterData();
      masterSaved=true;
    }
    if(old){
      const oldId=old.id;Object.assign(old,activity);
      if(oldId!==id)for(const item of state.project.activities)for(const relationship of item.predecessors)if(relationship.id===oldId)relationship.id=id;
    }else state.project.activities.push(activity);
    const check=scheduleProject();
    if(!check.ok)throw new Error(check.error);
    state.selectedId=id;
    await saveProject(true);
    $("#activityDialog").close();
    render();
    toast(masterSaved?"Aktivitas disimpan ke schedule dan Master Activity.":"Aktivitas disimpan.");
  } catch(error) {
    $("#activityError").textContent=error.message;
  } finally {
    if(button)button.disabled=false;
  }
}

function openDraggedDependency(predecessorId,successorId){if(!canEdit()||predecessorId===successorId)return;const predecessor=state.project.activities.find(item=>item.id===predecessorId),successor=state.project.activities.find(item=>item.id===successorId);if(!predecessor||!successor)return;state.pendingDragDependency={predecessorId,successorId};const existing=successor.predecessors.find(item=>item.id===predecessorId),form=$("#dragDependencyForm");form.reset();form.elements.type.value=existing?.type||"FS";form.elements.lag.value=Number(existing?.lag||0);$("#dragDependencyPreview").innerHTML=`<strong>${esc(predecessor.id)} · ${esc(predecessor.name)}</strong><span>menjadi predecessor untuk</span><strong>${esc(successor.id)} · ${esc(successor.name)}</strong>`;$("#dragDependencyError").textContent="";$("#dragDependencyDialog").showModal();}
function openRelationships(id) { if(!canEdit())return; state.selectedId=id; const a=state.project.activities.find(x=>x.id===id); if(!a)return; const successors=state.project.activities.flatMap(activity=>activity.predecessors.filter(rel=>rel.id===a.id).map(rel=>({id:activity.id,type:rel.type,lag:rel.lag}))); $("#relationshipLabel").textContent=`${a.id} — ${a.name}`; $("#relationshipError").textContent=""; $("#relationshipRows").innerHTML=""; $("#successorRows").innerHTML=""; (a.predecessors.length?a.predecessors:[{id:"",type:"FS",lag:0}]).forEach(rel=>addRelationshipRow("#relationshipRows",rel)); (successors.length?successors:[{id:"",type:"FS",lag:0}]).forEach(rel=>addRelationshipRow("#successorRows",rel)); $("#relationshipDialog").showModal(); }
function addRelationshipRow(containerSelector,rel={id:"",type:"FS",lag:0}) { const a=state.project.activities.find(x=>x.id===state.selectedId); const row=document.createElement("div"); row.className="relationship-row"; row.innerHTML=`<select class="rel-id"><option value="">Pilih aktivitas</option>${state.project.activities.filter(x=>x.id!==a.id).map(x=>`<option value="${esc(x.id)}" ${x.id===rel.id?"selected":""}>${esc(x.id)} · ${esc(x.name)}</option>`).join("")}</select><select class="rel-type">${["FS","SS","FF","SF"].map(v=>`<option ${v===rel.type?"selected":""}>${v}</option>`).join("")}</select><input class="rel-lag" type="number" value="${Number(rel.lag||0)}" title="Lag/lead hari kerja"><button class="icon-remove" type="button">×</button>`; row.querySelector(".icon-remove").onclick=()=>row.remove(); $(containerSelector).append(row); }
function hasCycle(activities) { const map=new Map(activities.map(a=>[a.id,a])), visiting=new Set(),done=new Set(); function visit(id){if(visiting.has(id))return true;if(done.has(id))return false;visiting.add(id);for(const r of map.get(id)?.predecessors||[])if(visit(r.id))return true;visiting.delete(id);done.add(id);return false;} return activities.some(a=>visit(a.id)); }

async function saveProject(immediate=false) {
  if(!state.project || state.user?.role==="viewer")return; clearTimeout(state.saveTimer);
  const perform=async()=>{ $("#syncStatus").textContent="Menyimpan..."; try { const result=await api("project",{method:"POST",body:{id:state.activeId,name:state.project.name,data:state.project}}); state.activeId=result.id; const listed=state.projects.find(p=>p.id===result.id); if(listed){listed.name=state.project.name;listed.data=clone(state.project);} else state.projects.unshift({id:result.id,name:state.project.name,data:clone(state.project),archived:false,owner_name:state.user.display_name}); $("#syncStatus").textContent="Tersimpan"; }catch(error){$("#syncStatus").textContent="Gagal sync";toast(error.message,"error");}};
  if(immediate) await perform(); else state.saveTimer=setTimeout(perform,500);
}

async function loadProjects(selectId=null) {
  const result=await api("projects"); state.projects=result.projects;
  if(!state.projects.length){state.project=null;state.activeId=null;state.selectedId=null;render();return;}
  const target=state.projects.find(p=>p.id===(selectId||state.activeId))||state.projects.find(p=>!p.archived)||state.projects[0]; state.activeId=target.id; state.project=normalizeProject(target.data,target.name);state.project.name=target.name;state.selectedId=state.project.activities[0]?.id||null;const migrated=migrateStartConstraintsToRelationshipLags();render();$("#syncStatus").textContent="Tersimpan";if(migrated)saveProject();
}

function legacyProject(){try{const activities=JSON.parse(localStorage.getItem("eserScheduler.activities.v1"));if(!Array.isArray(activities)||!activities.length)return null;const name=localStorage.getItem("eserScheduler.projectName.v1")||"Migrasi proyek lama";toast("Data local storage lama dimigrasikan ke database.");return normalizeProject({name,dataDate:isoToday(),calendar:{name:"Kalender 7 Hari",workingDays:[0,1,2,3,4,5,6]},baseline:null,activities},name);}catch{return null;}}
function renderProjectList(){ $("#newProjectButton").hidden=!canEdit(); $(".example-projects").hidden=!canEdit(); $("#projectList").innerHTML=state.projects.length?state.projects.map(p=>`<article class="project-item ${p.id===state.activeId?"active":""} ${p.archived?"archived":""}"><button data-project="${p.id}" type="button"><strong>${esc(p.name)}</strong><small>${p.data?.activities?.length||0} aktivitas · ${esc(p.owner_name||"")}${p.archived?" · Archived":""}</small></button>${canEdit()?`<div class="project-tools"><button class="text-button" data-project-action="rename" data-id="${p.id}" type="button">Rename</button><button class="text-button" data-project-action="duplicate" data-id="${p.id}" type="button">Duplicate</button><button class="text-button" data-project-action="archive" data-id="${p.id}" type="button">${p.archived?"Restore":"Archive"}</button><button class="text-button danger-text" data-delete-project="${p.id}" type="button">Hapus</button></div>`:""}</article>`).join(""):`<div class="empty-project-list"><strong>Belum ada proyek</strong><span>Klik “Proyek baru” untuk memulai dari schedule kosong.</span></div>`; }
function confirmAction(title,message,action){state.confirmAction=action;$("#confirmTitle").textContent=title;$("#confirmMessage").textContent=message;$("#confirmDialog").showModal();}

async function createExampleProject(slug) {
  if(!canEdit())return;
  try {
    const response=await fetch(`examples/${encodeURIComponent(slug)}.json`,{cache:"no-store"});
    if(!response.ok)throw new Error("Template contoh tidak ditemukan.");
    const raw=await response.json();state.project=normalizeProject(raw.project||raw,raw.project?.name||raw.name||"Contoh Proyek");state.activeId=null;state.selectedId=state.project.activities[0]?.id||null;
    await saveProject(true);$("#projectDialog").close();await loadProjects(state.activeId);toast("Contoh proyek berhasil dibuat.");
  } catch(error) { toast(error.message,"error"); }
}

function backupJson(){const blob=new Blob([JSON.stringify({format:"EserScheduler",version:3,exportedAt:new Date().toISOString(),project:state.project},null,2)],{type:"application/json"});downloadBlob(blob,`EserScheduler-${safeName(state.project.name)}-${isoToday()}.json`);toast("Backup JSON dibuat.");}
function downloadBlob(blob,name){const url=URL.createObjectURL(blob),a=document.createElement("a");a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
function safeName(v){return String(v).replace(/[^a-z0-9_-]+/gi,"-").replace(/^-|-$/g,"").slice(0,80)||"project";}
function printText(value,limit=44){const text=String(value||"");return text.length>limit?`${text.slice(0,limit-1)}…`:text;}
function printActivityBounds(activity,relative,rangeStart){return relative?{start:activityStartDay(activity),end:activityFinishDay(activity)}:{start:dayDiff(rangeStart,activity.start)+1,end:dayDiff(rangeStart,finishDate(activity))+1};}
function printDayLabel(day,relative,rangeStart){if(relative)return String(day);const value=addCalendarDays(rangeStart,day-1);return new Intl.DateTimeFormat("id-ID",{day:"2-digit",month:"short",timeZone:"UTC"}).format(value);}
function printGanttSvg(rows,allRows,windowStart,windowEnd,relative,rangeStart,blockId,sequenceMap){
  const width=1000,labelWidth=290,chartWidth=width-labelWidth,rowHeight=38,headerHeight=28,height=headerHeight+rows.length*rowHeight,days=windowEnd-windowStart+1,cellWidth=chartWidth/days,positions=new Map();
  const parts=[`<svg class="print-gantt-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Timeline ${esc(printDayLabel(windowStart,relative,rangeStart))} sampai ${esc(printDayLabel(windowEnd,relative,rangeStart))}"><defs><marker id="printArrow-${blockId}" markerWidth="12" markerHeight="12" refX="11" refY="6" orient="auto" markerUnits="userSpaceOnUse"><path d="M1,1 L11,6 L1,11 Z" fill="#075985" stroke="#fff" stroke-width="1"/></marker><marker id="printArrowCritical-${blockId}" markerWidth="12" markerHeight="12" refX="11" refY="6" orient="auto" markerUnits="userSpaceOnUse"><path d="M1,1 L11,6 L1,11 Z" fill="#f59e0b" stroke="#fff" stroke-width="1"/></marker></defs><rect width="${width}" height="${height}" fill="#fff"/><rect width="${width}" height="${headerHeight}" fill="#f2f6f9"/><text x="12" y="19" class="print-svg-header">No / Activity</text>`];
  for(let day=0;day<days;day++){const x=labelWidth+day*cellWidth;parts.push(`<line x1="${x}" y1="0" x2="${x}" y2="${headerHeight}" class="print-svg-grid print-svg-grid-vertical"/><text x="${x+cellWidth/2}" y="19" text-anchor="middle" class="print-svg-day">${esc(printDayLabel(windowStart+day,relative,rangeStart))}</text>`);}
  parts.push(`<line x1="${width-1}" y1="0" x2="${width-1}" y2="${height}" class="print-svg-grid"/>`);
  rows.forEach((activity,index)=>{const top=headerHeight+index*rowHeight,bounds=printActivityBounds(activity,relative,rangeStart),start=Math.max(bounds.start,windowStart),end=Math.min(bounds.end,windowEnd);parts.push(`<rect x="0" y="${top}" width="${width}" height="${rowHeight}" fill="${index%2?"#f8fbfd":"#fff"}"/><line x1="0" y1="${top}" x2="${width}" y2="${top}" class="print-svg-grid"/><text x="12" y="${top+15}" class="print-svg-name">${sequenceMap.get(activity.id)}. ${esc(printText(activity.name))}</text><text x="12" y="${top+29}" class="print-svg-id">${esc(activity.id)}</text>`);for(let day=0;day<days;day++){const gridX=labelWidth+day*cellWidth;parts.push(`<line x1="${gridX}" y1="${top}" x2="${gridX}" y2="${top+rowHeight}" class="print-svg-grid print-svg-grid-vertical"/>`);}if(start<=end){const x=labelWidth+(start-windowStart)*cellWidth+2,barWidth=Math.max(5,(end-start+1)*cellWidth-4),y=top+11;positions.set(activity.id,{x,right:x+barWidth,y:y+8});parts.push(`<rect x="${x}" y="${y}" width="${barWidth}" height="16" rx="4" fill="${activity.critical?"#cf4352":"#2a79c8"}"/><text x="${x+5}" y="${y+11.5}" class="print-svg-bar-label">${esc(activity.id)}</text>`);}});
  rows.forEach(activity=>{const target=positions.get(activity.id);if(!target)return;for(const rel of activity.predecessors){const source=positions.get(rel.id);if(!source)continue;const route=dependencyPath(source.right,source.y,target.x,target.y),critical=activity.critical;parts.push(`<path d="${route}" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/><path d="${route}" fill="none" stroke="${critical?"#f59e0b":"#075985"}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" marker-end="url(#${critical?`printArrowCritical-${blockId}`:`printArrow-${blockId}`})"/>`);}});
  parts.push(`<line x1="0" y1="${height-1}" x2="${width}" y2="${height-1}" class="print-svg-grid"/><rect x=".5" y=".5" width="${width-1}" height="${height-1}" fill="none" stroke="#cbd8e2"/> </svg>`);return parts.join("");
}
function buildSchedulePrintReport(){
  const filtered=filteredActivities(),activities=filtered.length?filtered:state.project.activities,relative=state.project.displayMode==="relative",range=projectRange(activities),bounds=activities.map(activity=>printActivityBounds(activity,relative,range.start)),minimum=activities.length?(relative?Math.min(...bounds.map(item=>item.start)):1):1,maximum=activities.length?(relative?Math.max(...bounds.map(item=>item.end)):range.days):0,sequenceMap=new Map(activities.map((activity,index)=>[activity.id,index+1])),tableRows=activities.map((activity,index)=>`<tr><td>${index+1}</td><td><strong>${esc(activity.id)}</strong></td><td><strong>${esc(activity.name)}</strong><small>WBS ${esc(activity.wbs)} · ${activity.progress}%</small></td><td>${activity.duration}d</td><td>${relative?`Hari ke-${activityStartDay(activity)}`:formatDate(activity.start)}</td><td>${relative?`Hari ke-${activityFinishDay(activity)}`:formatDate(finishDate(activity))}</td><td>${esc(activity.predecessors.map(formatRel).join(", ")||"—")}</td></tr>`).join(""),blocks=[];let blockId=0;
  for(let windowStart=minimum;windowStart<=maximum;windowStart+=30){const windowEnd=Math.min(maximum,windowStart+29);for(let offset=0;offset<activities.length;offset+=14){const rows=activities.slice(offset,offset+14);blocks.push(`<section class="print-gantt-block"><h2>Timeline Gantt <span>${esc(printDayLabel(windowStart,relative,range.start))} - ${esc(printDayLabel(windowEnd,relative,range.start))}${activities.length>14?` · Aktivitas ${offset+1}-${offset+rows.length}`:""}</span></h2>${printGanttSvg(rows,activities,windowStart,windowEnd,relative,range.start,++blockId,sequenceMap)}</section>`);}}
  const report=document.createElement("main"),rangeLabel=activities.length?`${printDayLabel(minimum,relative,range.start)} - ${printDayLabel(maximum,relative,range.start)}`:"Belum ada aktivitas";report.id="schedulePrintReport";report.innerHTML=`<header class="print-report-header"><div><span>EserScheduler</span><h1>${esc(state.project.name)}</h1><p>Activity Schedule</p></div><dl><div><dt>Jumlah aktivitas</dt><dd>${activities.length}</dd></div><div><dt>Rentang</dt><dd>${esc(rangeLabel)}</dd></div><div><dt>Kalender</dt><dd>${esc(state.project.calendar.name)}</dd></div></dl></header><section class="print-activity-section"><h2>Daftar Aktivitas</h2><table><thead><tr><th>No.</th><th>Activity ID</th><th>Activity / WBS</th><th>Durasi</th><th>Mulai</th><th>Selesai</th><th>Predecessor</th></tr></thead><tbody>${tableRows||'<tr><td colspan="7">Belum ada aktivitas.</td></tr>'}</tbody></table></section>${blocks.join("")}<footer class="print-report-footer">Dicetak ${new Intl.DateTimeFormat("id-ID",{dateStyle:"long"}).format(new Date())}</footer>`;document.body.append(report);return report;
}
function exportPdf(){const previousTitle=document.title,report=state.view==="activities"?buildSchedulePrintReport():null;document.title=`EserScheduler-${safeName(state.project?.name||"Schedule")}-${isoToday()}`;if(report)document.body.classList.add("schedule-print-mode");try{window.print();}finally{document.title=previousTitle;document.body.classList.remove("schedule-print-mode");report?.remove();}}

function exportCostExcel(){if(!window.XLSX){toast("SheetJS tidak tersedia.","error");return;}const plan=calculateCostPlan(),deploymentRows=plan.rows.map(({deployment,resource,cost})=>{const rates=deployment.rateOverride||resource||{};return{Code:deployment.resourceCode||resource?.code,Resource:deployment.resourceName||resource?.role_name||resource?.name,Type:deployment.type,Quantity:cost.quantity,WBS:deployment.wbs,"Start Day":deployment.startDay,"End Day":deployment.endDay,"Onsite Days":cost.onsite,"Working Days":cost.working,"Standby Days":cost.standby,"Travel Days":cost.travel,Activities:deployment.activityIds.join(", "),"Working Rate / Day":Number(rates.daily_rate||0),"Standby Rate / Day":Number(rates.standby_rate||0),"Field Allowance / Day":Number(rates.field_allowance||0),"Accommodation / Day":Number(rates.accommodation_rate||0),"Meal / Day":Number(rates.meal_rate||0),"Local Transport / Day":Number(rates.transport_rate||0),"Overtime Rate / Hour":Number(rates.overtime_rate||0),Mobilization:Number(rates.mobilization_cost||0),Demobilization:Number(rates.demobilization_cost||0),"Fuel / Active Day":Number(rates.fuel_daily||0),"Base Cost":cost.base,"Standby Cost":cost.standbyCost,"Travel Cost":cost.travelCost,Allowances:cost.allowances,Overtime:cost.overtime,"Fuel Cost":cost.fuel,"Mob Demob":cost.mobDemob,Total:cost.total};}),wb=XLSX.utils.book_new();XLSX.utils.book_append_sheet(wb,XLSX.utils.json_to_sheet(deploymentRows),"Resource Deployment");XLSX.utils.book_append_sheet(wb,XLSX.utils.json_to_sheet(Object.entries(plan.byWbs).map(([WBS,Total])=>({WBS,Total}))),"Cost by WBS");XLSX.utils.book_append_sheet(wb,XLSX.utils.json_to_sheet(Object.entries(plan.byActivity).map(([Activity,Total])=>({Activity,Total}))),"Cost by Activity");XLSX.writeFile(wb,`EserScheduler-Basic-Cost-${safeName(state.project.name)}-${isoToday()}.xlsx`,{compression:true});}

function exportExcel(){if(!window.XLSX){toast("SheetJS tidak tersedia.","error");return;}const relative=state.project.displayMode==="relative",hasDates=Boolean(state.project.projectStartDate);const rows=state.project.activities.map(a=>({ID:a.id,WBS:a.wbs,Activity:a.name,Duration:a.duration,PIC:a.pic,Predecessors:a.predecessors.map(formatRel).join(", "),"Start Day":activityStartDay(a),"Finish Day":activityFinishDay(a),...((!relative||hasDates)?{"Start Date":a.start,"Finish Date":finishDate(a)}:{}),Status:a.status,Progress:a.progress,Float:a.totalFloat,Critical:a.critical?"Yes":"No"}));const wb=XLSX.utils.book_new(),ws=XLSX.utils.json_to_sheet(rows);ws["!autofilter"]={ref:ws["!ref"]};XLSX.utils.book_append_sheet(wb,ws,"Activities");let headers;if(relative){const last=Math.max(1,...state.project.activities.map(activityFinishDay));headers=Array.from({length:last},(_,i)=>`Hari ke-${i+1}`);}else{const range=projectRange();headers=Array.from({length:range.days},(_,i)=>toISO(addCalendarDays(range.start,i)));}const daily=[["ID","Activity",...headers],...state.project.activities.map(a=>[a.id,a.name,...headers.map((header,index)=>{const active=relative?(index+1>=activityStartDay(a)&&index+1<=activityFinishDay(a)):(header>=a.start&&header<=finishDate(a));return active?(a.critical?"C":"■"):"";})])];XLSX.utils.book_append_sheet(wb,XLSX.utils.aoa_to_sheet(daily),"Daily Gantt");XLSX.writeFile(wb,`EserScheduler-${safeName(state.project.name)}-${isoToday()}.xlsx`,{compression:true});}

function openRestore(accept=".json,.rpj"){state.pendingRestore=null;$("#restoreFile").value="";$("#restoreFile").accept=accept;$("#restorePreview").innerHTML="";$("#restoreError").textContent="";$("#restoreDialog").showModal();}
async function previewRestore(file){state.pendingRestore=null;$("#restoreError").textContent="";try{let project;if(file.name.toLowerCase().endsWith(".rpj"))project=parseRpjFile(await file.arrayBuffer(),file.name);else{const parsed=JSON.parse(await file.text());project=normalizeProject(parsed.project||parsed,parsed.project?.name||file.name.replace(/\.json$/i,""));}const ids=project.activities.map(a=>a.id);if(new Set(ids).size!==ids.length)throw new Error("File memiliki Activity ID duplikat.");if(hasCycle(project.activities))throw new Error("File memiliki dependency cycle.");state.pendingRestore=project;$("#restorePreview").innerHTML=`<strong>${esc(project.name)}</strong><span>${project.activities.length} aktivitas</span><span>${formatDate(projectRange(project.activities).start)} – ${formatDate(projectRange(project.activities).end)}</span>`;}catch(error){$("#restoreError").textContent=error.message;}}

function bytesMatch(bytes,offset,pattern){return pattern.every((v,i)=>bytes[offset+i]===v);}
function decodeRpjString(bytes,offset,length){const chars=[];for(let i=0;i<length;i++){const code=bytes[offset+i*2]|(bytes[offset+i*2+1]<<8);if(code)chars.push(String.fromCharCode(code));}return chars.join("").trim();}
function rpjUnixDate(seconds){return new Date(seconds*1000).toISOString().slice(0,10);}
function parseRpjFile(buffer,fileName){const bytes=new Uint8Array(buffer),view=new DataView(buffer);if(decodeRpjString(bytes,4,12)!=="RILLSOFT-PRJ")throw new Error("Signature RPJ tidak valid.");const tasks=[];for(let offset=0;offset<bytes.length-28;offset++){if(!bytesMatch(bytes,offset,[255,254,255]))continue;const length=bytes[offset+3],end=offset+4+length*2;if(length<1||end+24>bytes.length)continue;const name=decodeRpjString(bytes,offset+4,length),sourceId=view.getUint32(end,true),start=view.getUint32(end+8,true),finish=view.getUint32(end+16,true),startHigh=view.getUint32(end+12,true),finishHigh=view.getUint32(end+20,true);if(name&&sourceId>0&&sourceId<1000000&&!startHigh&&!finishHigh&&start>=946684800&&finish>=start&&finish<=4102444800)tasks.push({sourceId,name,start,finish,end});}const unique=[...new Map(tasks.map(t=>[t.sourceId,t])).values()];if(!unique.length)throw new Error("Aktivitas RPJ tidak dapat dibaca.");const ids=new Set(unique.map(t=>t.sourceId)),prefix=[255,255,255,0,0,0,0,0];let best=-1,countBest=0,search=Math.max(...unique.map(t=>t.end));for(let o=search;o<bytes.length-48;o++){if(!bytesMatch(bytes,o,prefix))continue;let count=0;while(o+count*48<bytes.length-24&&bytesMatch(bytes,o+count*48,prefix))count++;if(count>countBest){countBest=count;best=o;}}const rels=new Map();if(best>=0)for(let i=0;i<countBest;i++){const o=best+i*48,p=view.getUint32(o+8,true),s=view.getUint32(o+16,true),type=view.getUint32(o+24,true)===2?"SS":"FS";if(p!==s&&ids.has(p)&&ids.has(s)){const list=rels.get(s)||[];if(!list.some(r=>r.id===`RPJ-${p}`))list.push({id:`RPJ-${p}`,type,lag:0});rels.set(s,list);}}const activities=unique.map(t=>({id:`RPJ-${t.sourceId}`,wbs:"1",name:t.name,duration:Math.max(1,dayDiff(rpjUnixDate(t.start),rpjUnixDate(t.finish))+1),pic:"Belum ditentukan",predecessors:rels.get(t.sourceId)||[],start:rpjUnixDate(t.start),status:"Not started",progress:0}));return normalizeProject({name:fileName.replace(/\.rpj$/i,""),dataDate:isoToday(),calendar:{name:"Kalender 7 Hari",workingDays:[0,1,2,3,4,5,6]},baseline:null,activities});}

function bindEvents(){
  setupDeploymentCurrencyInputs();
  setupDeploymentResourceSearch();
  const activityRows=$("#activityRows");
  activityRows.addEventListener("dragstart",event=>{const handle=event.target.closest("[data-drag-activity]");if(!handle||!canEdit())return;state.draggedActivityId=handle.dataset.dragActivity;event.dataTransfer.effectAllowed="move";event.dataTransfer.setData("text/plain",state.draggedActivityId);handle.closest(".activity-row").classList.add("activity-drag-source");});
  activityRows.addEventListener("dragover",event=>{const row=event.target.closest(".activity-row[data-select]");if(!row||!state.draggedActivityId||row.dataset.select===state.draggedActivityId)return;event.preventDefault();event.dataTransfer.dropEffect="move";const after=event.clientY>=row.getBoundingClientRect().top+row.getBoundingClientRect().height/2;$$('.activity-row.activity-drop-before,.activity-row.activity-drop-after').forEach(item=>item.classList.remove('activity-drop-before','activity-drop-after'));row.classList.add(after?"activity-drop-after":"activity-drop-before");});
  activityRows.addEventListener("dragleave",event=>{const row=event.target.closest(".activity-row[data-select]");if(row&&!row.contains(event.relatedTarget))row.classList.remove("activity-drop-before","activity-drop-after");});
  activityRows.addEventListener("drop",event=>{const row=event.target.closest(".activity-row[data-select]"),sourceId=event.dataTransfer.getData("text/plain")||state.draggedActivityId;if(!row||!sourceId||row.dataset.select===sourceId)return;event.preventDefault();const placeAfter=row.classList.contains("activity-drop-after");reorderActivities(state.project.activities,sourceId,row.dataset.select,placeAfter);state.draggedActivityId=null;$$('.activity-row.activity-drop-before,.activity-row.activity-drop-after,.activity-row.activity-drag-source').forEach(item=>item.classList.remove('activity-drop-before','activity-drop-after','activity-drag-source'));saveProject();render();toast("Urutan aktivitas diperbarui.");});
  activityRows.addEventListener("dragend",()=>{$$('.activity-row.activity-drop-before,.activity-row.activity-drop-after,.activity-row.activity-drag-source').forEach(item=>item.classList.remove('activity-drop-before','activity-drop-after','activity-drag-source'));state.draggedActivityId=null;});
  const timelineBody=$("#timelineBody");
  const ganttTooltip=document.createElement("div");ganttTooltip.className="gantt-hover-tooltip";ganttTooltip.hidden=true;document.body.append(ganttTooltip);
  timelineBody.addEventListener("mousemove",event=>{if(state.ganttEdit){ganttTooltip.hidden=true;return;}const bar=event.target.closest(".task-bar[data-activity-tooltip]");if(!bar){ganttTooltip.hidden=true;return;}ganttTooltip.textContent=bar.dataset.activityTooltip;ganttTooltip.hidden=false;const rect=ganttTooltip.getBoundingClientRect(),left=Math.min(event.clientX+14,window.innerWidth-rect.width-10),above=event.clientY-rect.height-12;ganttTooltip.style.left=`${Math.max(10,left)}px`;ganttTooltip.style.top=`${above>8?above:event.clientY+18}px`;});
  timelineBody.addEventListener("mouseleave",()=>{ganttTooltip.hidden=true;});
  timelineBody.addEventListener("pointerdown",event=>{if(!canEdit()||event.button!==0||event.target.closest("[data-gantt-link]"))return;const bar=event.target.closest(".task-bar[data-bar]");if(!bar)return;const activity=state.project.activities.find(item=>item.id===bar.dataset.bar);if(!activity)return;const handle=event.target.closest("[data-gantt-resize]"),mode=handle?.dataset.ganttResize||"move",bodyWidth=timelineBody.getBoundingClientRect().width,domainDays=Math.max(1,Number(timelineBody.dataset.domainDays||1)),scroller=$(".schedule-scroller");state.ganttEdit={id:activity.id,mode,startX:event.clientX,startScrollLeft:scroller.scrollLeft,lastDelta:null,moved:false,pixelsPerUnit:bodyWidth/domainDays,relative:timelineBody.dataset.relative==="true",start:activity.start,finish:finishDate(activity),duration:activity.duration,activities:clone(state.project.activities)};state.selectedId=activity.id;document.body.classList.add("gantt-schedule-editing");bar.classList.add("gantt-editing");ganttTooltip.hidden=true;event.preventDefault();});
  document.addEventListener("pointermove",event=>{const edit=state.ganttEdit;if(!edit)return;event.preventDefault();const scroller=$(".schedule-scroller"),rect=scroller.getBoundingClientRect();if(event.clientX>rect.right-28)scroller.scrollLeft+=18;else if(event.clientX<rect.left+28)scroller.scrollLeft-=18;const deltaPixels=event.clientX-edit.startX+(scroller.scrollLeft-edit.startScrollLeft),delta=Math.round(deltaPixels/Math.max(.1,edit.pixelsPerUnit));if(delta===edit.lastDelta)return;edit.lastDelta=delta;edit.moved=edit.moved||delta!==0;const scrollLeft=scroller.scrollLeft,activity=applyGanttScheduleEdit(edit,delta);renderActivities();scroller.scrollLeft=scrollLeft;const bar=timelineBody.querySelector(`[data-bar="${CSS.escape(edit.id)}"]`);bar?.classList.add("gantt-editing");if(activity){const relationshipInfo=activity.predecessors.length?` · ${activity.predecessors.map(formatRel).join(", ")}`:"";ganttTooltip.textContent=`${activity.id} · ${scheduleLabel(activity)} · ${activity.duration} hari${relationshipInfo}`;ganttTooltip.hidden=false;const tipRect=ganttTooltip.getBoundingClientRect();ganttTooltip.style.left=`${Math.min(event.clientX+14,window.innerWidth-tipRect.width-10)}px`;ganttTooltip.style.top=`${Math.max(8,event.clientY-tipRect.height-12)}px`;}});
  document.addEventListener("pointerup",()=>{const edit=state.ganttEdit;if(!edit)return;state.ganttEdit=null;document.body.classList.remove("gantt-schedule-editing");ganttTooltip.hidden=true;if(!edit.moved){state.project.activities=clone(edit.activities);scheduleProject();renderActivities();return;}const activity=state.project.activities.find(item=>item.id===edit.id),relationshipInfo=activity?.predecessors.length?` Lag/lead: ${activity.predecessors.map(formatRel).join(", ")}.`:"";saveProject();render();toast(edit.mode==="move"?`${edit.id} dipindahkan.${relationshipInfo} Successor otomatis disesuaikan.`:`Durasi ${edit.id} menjadi ${activity?.duration||1} hari.${relationshipInfo} Successor otomatis disesuaikan.`);});
  timelineBody.addEventListener("dragstart",event=>{const port=event.target.closest("[data-gantt-link]");if(!port||!canEdit())return;const bar=port.closest(".task-bar"),bodyRect=timelineBody.getBoundingClientRect(),barRect=bar.getBoundingClientRect();state.draggedActivityId=port.dataset.ganttLink;state.dragLinkStart={x:barRect.right-bodyRect.left,y:barRect.top+barRect.height/2-bodyRect.top};bar.classList.add("dependency-bar-source");event.dataTransfer.effectAllowed="link";event.dataTransfer.setData("text/plain",state.draggedActivityId);drawGanttDependencyDraft(event.clientX,event.clientY);});
  timelineBody.addEventListener("dragover",event=>{if(!state.dragLinkStart)return;event.preventDefault();const target=event.target.closest(".task-bar[data-bar]"),validTarget=target&&target.dataset.bar!==state.draggedActivityId?target:null;$$('.task-bar.dependency-bar-target').forEach(item=>item.classList.remove('dependency-bar-target'));if(validTarget)validTarget.classList.add("dependency-bar-target");event.dataTransfer.dropEffect=validTarget?"link":"none";drawGanttDependencyDraft(event.clientX,event.clientY,validTarget);});
  timelineBody.addEventListener("drop",event=>{if(!state.dragLinkStart)return;event.preventDefault();const target=event.target.closest(".task-bar[data-bar]"),predecessorId=event.dataTransfer.getData("text/plain")||state.draggedActivityId,successorId=target?.dataset.bar;clearGanttDependencyDrag();if(predecessorId&&successorId&&predecessorId!==successorId)openDraggedDependency(predecessorId,successorId);});
  timelineBody.addEventListener("dragend",clearGanttDependencyDrag);
  document.addEventListener("click",async event=>{
    const close=event.target.closest("[data-close]");if(close){document.getElementById(close.dataset.close).close();return;}
    const example=event.target.closest("[data-example]");if(example){await createExampleProject(example.dataset.example);return;}
    const addDeployment=event.target.closest("[data-add-deployment]");if(addDeployment){openDeployment(null,addDeployment.dataset.addDeployment);return;}
    const editDeployment=event.target.closest("[data-edit-deployment]");if(editDeployment){openDeployment(editDeployment.dataset.editDeployment);return;}
    const deleteSnapshot=event.target.closest("[data-delete-cost-snapshot]");if(deleteSnapshot){confirmAction("Hapus snapshot biaya","Snapshot historis ini akan dihapus.",async()=>{await api("cost_snapshots",{method:"DELETE",query:`&projectId=${state.activeId}&id=${deleteSnapshot.dataset.deleteCostSnapshot}`});toast("Snapshot biaya dihapus.");loadCostSnapshots();});return;}
    const resetMaster=event.target.closest("[data-reset-master]");if(resetMaster){resetMasterForm(resetMaster.dataset.resetMaster);return;}
    const resetRate=event.target.closest("[data-reset-rate-form]");if(resetRate){const form=$(resetRate.dataset.resetRate==="personnel"?"#masterPersonnelForm":"#masterEquipmentForm");form.reset();form.elements.id.value="";form.elements.active.checked=true;return;}
    const editPersonnel=event.target.closest("[data-edit-personnel]");if(editPersonnel){const item=state.master.personnel.find(value=>value.id===Number(editPersonnel.dataset.editPersonnel)),form=$("#masterPersonnelForm");if(item){for(const [field,key] of [["id","id"],["code","code"],["roleName","role_name"],["dailyRate","daily_rate"],["standbyRate","standby_rate"],["fieldAllowance","field_allowance"],["accommodationRate","accommodation_rate"],["mealRate","meal_rate"],["transportRate","transport_rate"],["overtimeRate","overtime_rate"]])form.elements[field].value=item[key]??0;form.elements.active.checked=item.active;form.scrollIntoView({behavior:"smooth",block:"center"});}return;}
    const editEquipment=event.target.closest("[data-edit-equipment]");if(editEquipment){const item=state.master.equipment.find(value=>value.id===Number(editEquipment.dataset.editEquipment)),form=$("#masterEquipmentForm");if(item){for(const [field,key] of [["id","id"],["code","code"],["name","name"],["category","category"],["costCategoryId","cost_category_id"],["brand","brand"],["model","model"],["assetTag","asset_tag"],["specification","specification"],["dailyRate","daily_rate"],["standbyRate","standby_rate"],["mobilizationCost","mobilization_cost"],["demobilizationCost","demobilization_cost"],["fuelDaily","fuel_daily"]])form.elements[field].value=item[key]??"";form.elements.operatorIncluded.checked=item.operator_included;form.elements.active.checked=item.active;form.scrollIntoView({behavior:"smooth",block:"center"});}return;}
    const deletePersonnel=event.target.closest("[data-delete-personnel]");if(deletePersonnel){confirmAction("Hapus master personel","Deployment yang sudah dibuat tetap menyimpan kode dan nama, tetapi tidak dapat dihitung sampai resource diganti.",async()=>{await api("master_personnel",{method:"DELETE",query:`&id=${deletePersonnel.dataset.deletePersonnel}`});await loadMasterData();renderMasterData();toast("Master personel dihapus.");});return;}
    const deleteEquipment=event.target.closest("[data-delete-equipment]");if(deleteEquipment){confirmAction("Hapus master peralatan","Deployment yang sudah dibuat tetap menyimpan kode dan nama, tetapi tidak dapat dihitung sampai resource diganti.",async()=>{await api("master_equipment",{method:"DELETE",query:`&id=${deleteEquipment.dataset.deleteEquipment}`});await loadMasterData();renderMasterData();toast("Master peralatan dihapus.");});return;}
    const editWbs=event.target.closest("[data-edit-wbs]");if(editWbs){const item=state.master.wbs.find(value=>value.id===Number(editWbs.dataset.editWbs)),form=$("#masterWbsForm");if(item&&form){form.elements.id.value=item.id;form.elements.code.value=item.code;form.elements.name.value=item.name;form.elements.prefix.value=item.prefix||"";form.elements.prefix.dataset.edited="true";form.elements.description.value=item.description||"";form.elements.code.focus();}return;}
    const deleteWbs=event.target.closest("[data-delete-wbs]");if(deleteWbs){const item=state.master.wbs.find(value=>value.id===Number(deleteWbs.dataset.deleteWbs));confirmAction("Hapus master WBS",`Hapus ${item.code} — ${item.name}?`,async()=>{try{await api("master_wbs",{method:"DELETE",query:`&id=${item.id}`});await loadMasterData();renderMasterData();toast("Master WBS dihapus.");}catch(error){toast(error.message,"error");}});return;}
    const editMasterActivity=event.target.closest("[data-edit-master-activity]");if(editMasterActivity){const item=state.master.activities.find(value=>value.id===Number(editMasterActivity.dataset.editMasterActivity)),form=$("#masterActivityForm");if(item&&form){form.elements.id.value=item.id;form.elements.wbsId.value=item.wbs_id;form.elements.activityCode.value=item.activity_code;form.elements.name.value=item.name;form.elements.defaultDuration.value=item.default_duration;form.elements.defaultPic.value=item.default_pic||"";form.elements.activityCode.focus();}return;}
    const deleteMasterActivity=event.target.closest("[data-delete-master-activity]");if(deleteMasterActivity){const item=state.master.activities.find(value=>value.id===Number(deleteMasterActivity.dataset.deleteMasterActivity));confirmAction("Hapus master aktivitas",`Hapus ${item.activity_code} — ${item.name}? Aktivitas yang sudah masuk schedule tidak ikut terhapus.`,async()=>{try{await api("master_activity",{method:"DELETE",query:`&id=${item.id}`});await loadMasterData();renderMasterData();toast("Master aktivitas dihapus.");}catch(error){toast(error.message,"error");}});return;}
    const view=event.target.closest("[data-view]");if(view){event.preventDefault();state.view=view.dataset.view;if(window.matchMedia("(max-width: 760px)").matches){state.sidebarCollapsed=true;saveSidebarPreference();}render();return;}
    const select=event.target.closest("[data-select]");if(select){state.selectedId=select.dataset.select;renderActivities();return;}
    const edit=event.target.closest("[data-edit]");if(edit){openActivity(edit.dataset.edit);return;}
    const rel=event.target.closest("[data-rel]");if(rel){openRelationships(rel.dataset.rel);return;}
    const project=event.target.closest("[data-project]");if(project){await loadProjects(Number(project.dataset.project));$("#projectDialog").close();return;}
    const projectAction=event.target.closest("[data-project-action]");if(projectAction){const p=state.projects.find(x=>x.id===Number(projectAction.dataset.id));if(projectAction.dataset.projectAction==="rename"){const name=prompt("Nama proyek",p.name)?.trim();if(name){await api("project",{method:"POST",body:{id:p.id,name,data:p.data,archived:p.archived}});await loadProjects(p.id);renderProjectList();toast("Nama proyek diperbarui.");}}if(projectAction.dataset.projectAction==="duplicate"){const copy=clone(p.data);copy.name=`${p.name} - Copy`;const saved=await api("project",{method:"POST",body:{name:copy.name,data:copy}});await loadProjects(saved.id);renderProjectList();toast("Proyek diduplikasi.");}if(projectAction.dataset.projectAction==="archive"){await api("project",{method:"POST",body:{id:p.id,name:p.name,data:p.data,archived:!p.archived}});await loadProjects(p.id);renderProjectList();toast(p.archived?"Proyek dipulihkan.":"Proyek diarsipkan.");}return;}
    const remove=event.target.closest("[data-delete-project]");if(remove){const p=state.projects.find(x=>x.id===Number(remove.dataset.deleteProject));confirmAction("Hapus proyek",`Hapus proyek ${p.name}? Data tidak dapat dipulihkan kecuali dari backup.`,async()=>{await api("project",{method:"DELETE",query:`&id=${p.id}`});await loadProjects();renderProjectList();toast("Proyek dihapus.");});return;}
    const layout=event.target.closest("[data-layout]");if(layout){state.layout=layout.dataset.layout;$$("[data-layout]").forEach(b=>b.classList.toggle("selected",b===layout));renderActivities();}
  });
  $("#loginForm").addEventListener("submit",async e=>{e.preventDefault();$("#loginError").textContent="";const data=Object.fromEntries(new FormData(e.currentTarget));try{const result=await api("login",{method:"POST",body:data});state.user=result.user;state.csrf=result.csrf;$("#loginDialog").close();await loadMasterData();await loadProjects();toast(`Selamat datang, ${state.user.display_name}.`);}catch(error){$("#loginError").textContent=error.message;}});
  $("#projectPicker").onclick=()=>{renderProjectList();$("#projectDialog").showModal();};$("#profileButton").onclick=()=>{renderProjectList();$("#projectDialog").showModal();};
  $("#sidebarToggle").onclick=()=>{if(state.ganttFocus){state.ganttFocus=false;try{localStorage.setItem("eserSchedulerGanttFocus","false");}catch{}state.sidebarCollapsed=false;}else state.sidebarCollapsed=!state.sidebarCollapsed;saveSidebarPreference();render();requestAnimationFrame(drawDependencies);};
  $("#sidebarBackdrop").onclick=()=>{state.sidebarCollapsed=true;saveSidebarPreference();applySidebarState();};
  $("#logoutButton").onclick=async()=>{await api("logout",{method:"POST"});location.reload();};
  $("#passwordButton").onclick=()=>{$("#passwordForm").reset();$("#passwordError").textContent="";$("#projectDialog").close();$("#passwordDialog").showModal();};
  $("#securityWarning").onclick=()=>{$("#passwordForm").reset();$("#passwordError").textContent="";$("#passwordDialog").showModal();};
  $("#passwordForm").onsubmit=async e=>{e.preventDefault();$("#passwordError").textContent="";try{await api("password",{method:"POST",body:Object.fromEntries(new FormData(e.currentTarget))});state.user.must_change_password=0;$("#securityWarning").hidden=true;$("#passwordDialog").close();toast("Password berhasil diperbarui.");}catch(error){$("#passwordError").textContent=error.message;}};
  $("#newProjectButton").onclick=()=>{$("#projectForm").reset();$("#projectDialog").close();$("#projectFormDialog").showModal();};
  $("#projectForm").onsubmit=async e=>{e.preventDefault();const name=new FormData(e.currentTarget).get("name").trim();state.project=emptyProject(name);state.activeId=null;await saveProject(true);$("#projectFormDialog").close();await loadProjects(state.activeId);toast("Proyek kosong berhasil dibuat.");};
  $("#activitySearch").oninput=e=>{state.query=e.target.value;renderActivities();};$("#wbsFilter").onchange=e=>{state.wbs=e.target.value;renderActivities();};$("#statusFilter").onchange=e=>{state.status=e.target.value;renderActivities();};$("#scaleSelect").onchange=e=>{state.scale=e.target.value;renderActivities();};$("#zoomSelect").onchange=e=>{state.zoom=e.target.value;if(state.zoom==="fit")state.ganttFocus=true;render();};
  const changeGanttZoom=direction=>{const levels=["compact","normal","large"],current=levels.includes(state.zoom)?levels.indexOf(state.zoom):1;state.zoom=levels[Math.max(0,Math.min(levels.length-1,current+direction))];renderActivities();};
  $("#zoomOutButton").onclick=()=>changeGanttZoom(-1);$("#zoomInButton").onclick=()=>changeGanttZoom(1);$("#zoomFitButton").onclick=()=>{state.zoom="fit";state.ganttFocus=true;render();$(".schedule-scroller").scrollLeft=0;};
  $("#toggleGanttFocus").onclick=()=>{state.ganttFocus=!state.ganttFocus;try{localStorage.setItem("eserSchedulerGanttFocus",JSON.stringify(state.ganttFocus));}catch{}render();$(".schedule-scroller").scrollLeft=0;};
  $("#togglePicColumn").onclick=()=>{state.columns.pic=!state.columns.pic;saveColumnPreference();renderActivities();};$("#togglePredecessorColumn").onclick=()=>{state.columns.predecessor=!state.columns.predecessor;saveColumnPreference();renderActivities();};
  $("#displayModeSelect").onchange=e=>{if(e.target.value==="date"&&!state.project.projectStartDate){state.view="calendar";render();toast("Isi tanggal mulai proyek untuk mengaktifkan tampilan tanggal.","error");return;}state.project.displayMode=e.target.value;saveProject();render();$(".schedule-scroller").scrollLeft=0;};
  $("#addActivityButton").onclick=()=>openActivity();
  $("#formWbsSelect").onchange=()=>updateWbsMasterSelection(null,false);
  $("#formActivitySelect").onchange=()=>updateActivityMasterSelection(false);
  $("#activityForm").onsubmit=saveActivityForm;
  $("#deleteActivityButton").onclick=()=>{const a=state.project.activities.find(x=>x.id===state.editingId);confirmAction("Hapus aktivitas",`Hapus ${a.id} — ${a.name}?`,()=>{state.project.activities=state.project.activities.filter(x=>x.id!==a.id);for(const x of state.project.activities)x.predecessors=x.predecessors.filter(r=>r.id!==a.id);state.selectedId=state.project.activities[0]?.id||null;$("#activityDialog").close();scheduleProject();saveProject();render();toast("Aktivitas dihapus.");});};
  $("#addRelationshipButton").onclick=()=>addRelationshipRow("#relationshipRows");$("#addSuccessorButton").onclick=()=>addRelationshipRow("#successorRows");$("#saveRelationshipButton").onclick=()=>{const a=state.project.activities.find(x=>x.id===state.selectedId),readRows=selector=>$$(selector+" .relationship-row").map(row=>({id:row.querySelector(".rel-id").value,type:row.querySelector(".rel-type").value,lag:Number(row.querySelector(".rel-lag").value||0)})).filter(r=>r.id),predecessors=readRows("#relationshipRows"),successors=readRows("#successorRows");if(new Set(predecessors.map(r=>r.id)).size!==predecessors.length){$("#relationshipError").textContent="Predecessor tidak boleh duplikat.";return;}if(new Set(successors.map(r=>r.id)).size!==successors.length){$("#relationshipError").textContent="Successor tidak boleh duplikat.";return;}const previous=state.project.activities.map(activity=>({activity,predecessors:clone(activity.predecessors)}));a.predecessors=predecessors;for(const activity of state.project.activities){if(activity.id===a.id)continue;activity.predecessors=activity.predecessors.filter(rel=>rel.id!==a.id);}for(const rel of successors){const successor=state.project.activities.find(activity=>activity.id===rel.id);if(successor)successor.predecessors.push({id:a.id,type:rel.type,lag:rel.lag});}if(hasCycle(state.project.activities)){for(const item of previous)item.activity.predecessors=item.predecessors;$("#relationshipError").textContent="Hubungan membentuk siklus.";return;}scheduleProject();$("#relationshipDialog").close();saveProject();render();toast("Predecessor dan successor diperbarui.");};
  $("#dragDependencyForm").onsubmit=e=>{e.preventDefault();const pending=state.pendingDragDependency;if(!pending)return;const successor=state.project.activities.find(item=>item.id===pending.successorId);if(!successor)return;const previous=clone(successor.predecessors),data=Object.fromEntries(new FormData(e.currentTarget)),relationship={id:pending.predecessorId,type:data.type,lag:Number(data.lag||0)},existing=successor.predecessors.find(item=>item.id===relationship.id);if(existing)Object.assign(existing,relationship);else successor.predecessors.push(relationship);if(hasCycle(state.project.activities)){successor.predecessors=previous;$("#dragDependencyError").textContent="Hubungan ini membentuk dependency cycle.";return;}state.selectedId=successor.id;state.pendingDragDependency=null;scheduleProject();$("#dragDependencyDialog").close();saveProject();render();toast(`${relationship.id} menjadi predecessor ${successor.id} (${relationship.type}).`);};
  $("#deploymentForm").elements.type.onchange=()=>{$("#deploymentForm").elements.resourceSearch.value="";updateDeploymentResourceOptions();};
  $("#deploymentForm").elements.resourceId.onchange=()=>{renderDeploymentResourceDropdown();populateDeploymentRateFields();};
  $("#deploymentForm").elements.periodMode.onchange=updateDeploymentPeriodFields;$("#deploymentForm").elements.activityIds.onchange=updateDeploymentPeriodFields;
  $("#deploymentForm").onsubmit=e=>{
    e.preventDefault();const form=e.currentTarget,data=Object.fromEntries(new FormData(form)),activityIds=[...form.elements.activityIds.selectedOptions].map(option=>option.value),periodMode=data.periodMode==="manual"?"manual":"activities",existing=state.project.deployments.find(item=>item.id===state.editingDeploymentId);
    if(periodMode==="activities"&&!activityIds.length){$("#deploymentError").textContent="Pilih minimal satu aktivitas untuk periode otomatis.";return;}
    const automaticRange=periodMode==="activities"?deploymentActivityRange(activityIds):null,startDay=Math.max(1,Number(automaticRange?.startDay??data.startDay)),endDay=Math.max(1,Number(automaticRange?.endDay??data.endDay)),standbyDays=Math.max(0,Number(data.standbyDays||0)),travelDays=Math.max(0,Number(data.travelDays||0)),onsite=endDay-startDay+1;
    if(endDay<startDay){$("#deploymentError").textContent="Hari keluar tidak boleh sebelum hari masuk.";return;}if(standbyDays+travelDays>onsite){$("#deploymentError").textContent="Total standby dan perjalanan melebihi durasi onsite.";return;}
    const resource=deploymentResources(data.type).find(item=>item.id===Number(data.resourceId));if(!resource){$("#deploymentError").textContent="Pilih resource dari master.";return;}
    const rateOverride=state.master.canViewRates?readDeploymentRateOverride(data):(existing?.rateOverride||null),deployment={id:state.editingDeploymentId||`DEP-${Date.now()}`,type:data.type,resourceId:resource.id,resourceCode:resource.code,resourceName:resource.role_name||resource.name,resourceSource:resource.source==="cost_estimator"?"cost_estimator":"scheduler",costItemIds:resource.cost_item_ids||existing?.costItemIds||{},quantity:Math.max(.01,Number(data.quantity||1)),wbs:data.wbs.trim(),periodMode,startDay,endDay,standbyDays,travelDays,overtimeHours:Math.max(0,Number(data.overtimeHours||0)),rateOverride,activityIds,notes:data.notes.trim()};
    const index=state.project.deployments.findIndex(item=>item.id===deployment.id);if(index>=0)state.project.deployments[index]=deployment;else state.project.deployments.push(deployment);$("#deploymentDialog").close();saveProject();render();toast("Deployment disimpan dan disinkronkan dengan aktivitas.");
  };
  $("#deleteDeploymentButton").onclick=()=>{const deployment=state.project.deployments.find(item=>item.id===state.editingDeploymentId);if(!deployment)return;confirmAction("Hapus deployment",`Hapus deployment ${deployment.resourceCode}?`,()=>{state.project.deployments=state.project.deployments.filter(item=>item.id!==deployment.id);$("#deploymentDialog").close();saveProject();render();toast("Deployment dihapus.");});};
  $("#backupButton").onclick=backupJson;$("#restoreButton").onclick=()=>openRestore(".json,.rpj");$("#importRpjButton").onclick=()=>openRestore(".rpj");$("#restoreFile").onchange=e=>previewRestore(e.target.files?.[0]);
  $("#restoreForm").onsubmit=e=>{e.preventDefault();if(!state.pendingRestore){$("#restoreError").textContent="Pilih file yang valid.";return;}confirmAction("Ganti data proyek",`Terapkan ${state.pendingRestore.activities.length} aktivitas dari file?`,()=>{const previous=state.project;state.project=state.pendingRestore;state.project.name=state.projects.find(p=>p.id===state.activeId)?.name||state.project.name;const result=scheduleProject();if(!result.ok){state.project=previous;toast(result.error,"error");return;}migrateStartConstraintsToRelationshipLags();state.pendingRestore=null;$("#restoreDialog").close();saveProject();render();toast("Data berhasil dipulihkan.");});};
  $("#exportExcelButton").onclick=exportExcel;$("#exportPdfButton").onclick=exportPdf;$("#exportCostButton").onclick=exportCostExcel;
  $("#contentPanel").addEventListener("click",async e=>{if(e.target.id==="createBasicCost"){if(!(state.project.deployments||[]).length){toast("Tambahkan deployment terlebih dahulu.","error");return;}confirmAction("Buat draft Basic Cost","Resource deployment akan disalin sebagai item draft. Form Basic Cost tetap dapat diedit dan ditambah manual.",async()=>{try{await saveProject(true);const result=await api("basic_cost_create",{method:"POST",body:{projectId:state.activeId}});toast(`${result.itemCount} item berhasil dikirim ke Basic Cost.`);window.parent.postMessage({type:"eser-basic-cost-created",id:result.basicCostId},"*");}catch(error){toast(error.message,"error");}});return;}if(e.target.id!=="saveCostSnapshot")return;try{const plan=calculateCostPlan(),name=`Basic Cost ${new Date().toLocaleString("id-ID")}`;await api("cost_snapshots",{method:"POST",query:`&projectId=${state.activeId}`,body:{name,data:{total:plan.total,personnel:plan.personnel,equipment:plan.equipment,byWbs:plan.byWbs,byActivity:plan.byActivity,deployments:state.project.deployments,createdAt:plan.createdAt}}});toast("Snapshot basic cost disimpan.");loadCostSnapshots();}catch(error){toast(error.message,"error");}});
  $("#confirmCancel").onclick=()=>{$("#confirmDialog").close();state.confirmAction=null;};$("#confirmOk").onclick=async()=>{const action=state.confirmAction;$("#confirmDialog").close();state.confirmAction=null;if(action)await action();};
  $("#contentPanel").addEventListener("click",e=>{if(e.target.id==="emptyNewProject"){$("#projectForm").reset();$("#projectFormDialog").showModal();return;}if(e.target.id==="saveCalendar"){const days=$$("#contentPanel .day-check input:checked").map(x=>Number(x.value));if(!days.length){toast("Pilih minimal satu hari kerja.","error");return;}const nextMode=$("#calendarDisplayMode").value,nextStart=$("#projectStartDate").value||null;if(nextMode==="date"&&!nextStart){toast("Tanggal mulai proyek wajib diisi untuk mode Tanggal.","error");return;}const rootDays=new Map(state.project.activities.filter(a=>!a.predecessors.length).map(a=>[a.id,activityStartDay(a)]));state.project.calendar={name:$("#calendarName").value.trim()||"Kalender kerja",workingDays:days};state.project.projectStartDate=nextStart;state.project.displayMode=nextMode;for(const a of state.project.activities.filter(item=>!item.predecessors.length))a.start=toISO(addWorkdays(scheduleAnchor(),(rootDays.get(a.id)||1)-1));scheduleProject();saveProject();render();toast(nextStart?"Hari ke-1 berhasil dipetakan ke tanggal proyek.":"Schedule disimpan dalam format Hari ke.");}if(e.target.id==="createBaseline"){state.project.baseline={name:`Baseline ${new Date().toLocaleDateString("id-ID")}`,createdAt:new Date().toISOString(),activities:state.project.activities.map(a=>({id:a.id,start:a.start,finish:finishDate(a)}))};saveProject();render();toast("Baseline dibuat.");}if(e.target.id==="clearBaseline")confirmAction("Hapus baseline","Hapus snapshot baseline proyek ini?",()=>{state.project.baseline=null;saveProject();render();toast("Baseline dihapus.");});});
  $("#contentPanel").addEventListener("submit",async e=>{const form=e.target;if(form.getAttribute("id")!=="userForm")return;e.preventDefault();try{await api("users",{method:"POST",body:Object.fromEntries(new FormData(form))});toast("Pengguna ditambahkan.");renderUsers();}catch(error){toast(error.message,"error");}});
  $("#contentPanel").addEventListener("submit",async e=>{const form=e.target,formId=form.getAttribute("id");if(!["masterPersonnelForm","masterEquipmentForm"].includes(formId))return;e.preventDefault();try{const data=Object.fromEntries(new FormData(form));if(formId==="masterPersonnelForm")await api("master_personnel",{method:"POST",body:data});else await api("master_equipment",{method:"POST",body:data});await loadMasterData();renderMasterData();toast(formId==="masterPersonnelForm"?"Personel disimpan ke Master Cost Estimator.":"Peralatan disimpan ke Master Cost Estimator.");}catch(error){toast(error.message,"error");}});
  document.addEventListener("keydown",event=>{if(event.key!=="Escape")return;if(state.ganttEdit){cancelGanttScheduleEdit();toast("Perubahan Gantt dibatalkan.");return;}if(!state.sidebarCollapsed&&window.matchMedia("(max-width: 760px)").matches){state.sidebarCollapsed=true;saveSidebarPreference();applySidebarState();$("#sidebarToggle").focus();}});
  window.addEventListener("resize",()=>{applySidebarState();requestAnimationFrame(drawDependencies);});
}

async function start(){try{const saved=JSON.parse(localStorage.getItem("eserSchedulerColumns")||"{}");if(typeof saved.pic==="boolean")state.columns.pic=saved.pic;if(typeof saved.predecessor==="boolean")state.columns.predecessor=saved.predecessor;}catch{}try{state.ganttFocus=JSON.parse(localStorage.getItem("eserSchedulerGanttFocus")||"false")===true;}catch{}try{const savedSidebar=localStorage.getItem("eserSchedulerSidebarCollapsed");state.sidebarCollapsed=savedSidebar===null?window.matchMedia("(max-width: 760px)").matches:JSON.parse(savedSidebar)===true;}catch{state.sidebarCollapsed=window.matchMedia("(max-width: 760px)").matches;}applySidebarState();setupEmbeddedMode();bindEvents();try{const session=await api("session");state.user=session.user;state.csrf=session.csrf;if(!state.user){window.parent.postMessage({type:"eser-auth-required"},"*");return;}await loadMasterData();await loadProjects();}catch(error){window.parent.postMessage({type:"eser-auth-required"},"*");$("#loginError").textContent="Sesi Cost Estimator berakhir. Silakan login kembali.";$("#loginDialog").showModal();}}
if (typeof module !== "undefined" && module.exports) {
  module.exports = {state, sampleProject, emptyProject, normalizeProject, scheduleProject, finishDate, activityStartDay, activityFinishDay, relativeTimelineRange, hasCycle, projectRange, dependencyRequiredStart, ganttOffsetDate, alignActivityRelationshipsToStart, migrateStartConstraintsToRelationshipLags, applyGanttScheduleEdit, deploymentActivityRange, deploymentDays, deploymentCost, deploymentConflictIds, calculateCostPlan, formatCurrencyDigits, parseCurrencyAmount, suggestWbsPrefix, nextActivityId, reorderActivities, dependencyPath, buildSchedulePrintReport, printGanttSvg};
} else {
  start();
}
