(() => {
  'use strict';
  let zoom = 1;
  document.addEventListener('click', async (event) => {
    const control = event.target.closest('[data-action]');
    if (!control) return;
    const action = control.dataset.action;
    if (action === 'sidebar') document.getElementById('sidebar')?.classList.toggle('open');
    if (action === 'dismiss') control.closest('.notice')?.remove();
    if (action === 'refresh') location.reload();
    if (action === 'fullscreen') { const viewer = document.getElementById('media-viewer'); try { if (document.fullscreenElement) await document.exitFullscreen(); else await viewer?.requestFullscreen(); } catch { control.textContent = 'Fullscreen unavailable'; } }
    if (action.startsWith('zoom-')) { zoom = Math.max(.25, Math.min(4, zoom + (action === 'zoom-in' ? .25 : -.25))); const img = document.getElementById('preview-image'); if (img) img.style.transform = `scale(${zoom})`; }
  });
  document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', e => { if (!confirm(form.dataset.confirm)) e.preventDefault(); }));
  document.querySelector('[data-file-input]')?.addEventListener('change', e => { const file = e.target.files[0]; document.querySelector('.file-selection').textContent = file ? `${file.name} · ${(file.size / 1048576).toFixed(2)} MB` : 'No file selected'; });
  document.querySelector('[data-upload-submit]')?.closest('form').addEventListener('submit', e => { if (e.target.checkValidity()) { const btn = e.target.querySelector('[data-upload-submit]'); btn.disabled = true; btn.textContent = 'Uploading securely…'; } });
  document.querySelector('[data-private-viewer]')?.addEventListener('contextmenu', e => e.preventDefault());
  document.querySelectorAll('[data-series]').forEach(root => {
    const series = JSON.parse(root.dataset.series); const canvas = root.querySelector('canvas'); const ctx = canvas.getContext('2d'); const w = 700, h = 220, max = Math.max(1, ...series.map(s => s.files));
    ctx.clearRect(0,0,w,h); ctx.font = '12px system-ui'; ctx.fillStyle = '#94a3b8';
    for (let i=0;i<4;i++) { const y=20+i*48; ctx.beginPath(); ctx.strokeStyle='#edf0f4'; ctx.moveTo(35,y); ctx.lineTo(685,y); ctx.stroke(); ctx.fillText(String(Math.round(max*(3-i)/3)),5,y+4); }
    series.forEach((s,i) => { const x=60+i*91, bar=s.files/max*145; ctx.fillStyle='#fff0e5'; ctx.fillRect(x-7,20,45,146); ctx.fillStyle='#f97316'; ctx.fillRect(x-7,166-bar,45,bar); ctx.fillStyle='#64748b'; ctx.fillText(s.day,x,198); });
    canvas.addEventListener('mousemove', e => { const x=(e.clientX-canvas.getBoundingClientRect().left)/canvas.clientWidth*w; const i=Math.max(0,Math.min(6,Math.round((x-75)/91))); root.nextElementSibling.textContent=`${series[i].day}: ${series[i].files} upload${series[i].files===1?'':'s'}`; });
    root.nextElementSibling.textContent=series.map(s=>`${s.day}: ${s.files}`).join(' · ');
  });
})();
