// Toast notifications
function toast(msg, type = 'success') {
    const container = document.querySelector('.toast-container') || (() => {
        const el = document.createElement('div');
        el.className = 'toast-container';
        document.body.appendChild(el);
        return el;
    })();
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    container.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});

// Notitie opslaan
async function notitieOpslaan(parasjaId, parasjaSchemaId) {
    const tekst = document.getElementById('notitie-tekst')?.value?.trim();
    const titel = document.getElementById('notitie-titel')?.value?.trim();
    if (!tekst) { toast('Vul een notitie in', 'error'); return; }

    const res = await fetch(window.BASE_URL+'/api/notitie.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actie: 'opslaan', parasja_id: parasjaId, parasja_schema_id: parasjaSchemaId, tekst, titel })
    });
    const data = await res.json();
    if (data.success) {
        toast('Notitie opgeslagen');
        closeModal('modal-notitie');
        location.reload();
    } else {
        toast(data.error || 'Fout bij opslaan', 'error');
    }
}

async function notitieVerwijderen(id) {
    if (!confirm('Notitie verwijderen?')) return;
    const res = await fetch(window.BASE_URL+'/api/notitie.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actie: 'verwijderen', id })
    });
    const data = await res.json();
    if (data.success) { toast('Notitie verwijderd'); location.reload(); }
    else toast(data.error || 'Fout', 'error');
}

// Foto upload
function setupUploadZone() {
    const zone = document.getElementById('upload-zone');
    const input = document.getElementById('foto-input');
    if (!zone || !input) return;

    zone.addEventListener('click', () => input.click());
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files.length) uploadFoto(e.dataTransfer.files[0]);
    });
    input.addEventListener('change', () => { if (input.files.length) uploadFoto(input.files[0]); });
}

async function uploadFoto(file) {
    const parasjaId = document.getElementById('foto-parasja-id')?.value;
    const parasjaSchemaId = document.getElementById('foto-schema-id')?.value;
    const notitie = document.getElementById('foto-notitie')?.value?.trim();

    if (!file.type.startsWith('image/')) { toast('Alleen afbeeldingen toegestaan', 'error'); return; }

    // Preview
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('upload-preview');
        if (preview) { preview.src = e.target.result; preview.style.display = 'block'; }
    };
    reader.readAsDataURL(file);

    // OCR status
    const ocrBox = document.getElementById('ocr-status');
    if (ocrBox) { ocrBox.innerHTML = '<div class="ocr-loading"><div class="spinner"></div> Bezig met uploaden en tekst herkennen...</div>'; }

    const form = new FormData();
    form.append('foto', file);
    form.append('parasja_id', parasjaId);
    form.append('parasja_schema_id', parasjaSchemaId || '');
    form.append('notitie', notitie || '');

    try {
        const res = await fetch(window.BASE_URL+'/api/upload.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            if (ocrBox) {
                ocrBox.innerHTML = `<div class="form-group">
                    <label>Herkende tekst (bewerk indien nodig)</label>
                    <textarea id="ocr-tekst" rows="6">${data.ocr_tekst || ''}</textarea>
                </div>
                <button class="btn btn-green" onclick="ocrOpslaan(${data.id})">
                    <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg> Opslaan
                </button>`;
            }
            toast('Foto geüpload' + (data.ocr_tekst ? ', tekst herkend' : ''));
        } else {
            if (ocrBox) ocrBox.innerHTML = '';
            toast(data.error || 'Upload mislukt', 'error');
        }
    } catch (err) {
        if (ocrBox) ocrBox.innerHTML = '';
        toast('Upload mislukt', 'error');
    }
}

async function ocrOpslaan(fotoId) {
    const tekst = document.getElementById('ocr-tekst')?.value;
    const res = await fetch(window.BASE_URL+'/api/upload.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actie: 'ocr-opslaan', id: fotoId, ocr_bewerkt: tekst })
    });
    const data = await res.json();
    if (data.success) { toast('Tekst opgeslagen'); location.reload(); }
    else toast(data.error || 'Fout', 'error');
}

// Foto viewer
function openFoto(id, src, ocrTekst, datum) {
    document.getElementById('viewer-img').src = src;
    document.getElementById('viewer-tekst').value = ocrTekst || '';
    document.getElementById('viewer-datum').textContent = datum || '';
    document.getElementById('viewer-foto-id').value = id;
    openModal('modal-foto-viewer');
}

async function ocrBewerktOpslaan() {
    const id = document.getElementById('viewer-foto-id').value;
    const tekst = document.getElementById('viewer-tekst').value;
    const res = await fetch(window.BASE_URL+'/api/upload.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actie: 'ocr-opslaan', id, ocr_bewerkt: tekst })
    });
    const data = await res.json();
    if (data.success) { toast('Tekst bijgewerkt'); closeModal('modal-foto-viewer'); location.reload(); }
    else toast(data.error || 'Fout', 'error');
}

async function fotoVerwijderen(id) {
    if (!confirm('Foto verwijderen?')) return;
    const res = await fetch(window.BASE_URL+'/api/upload.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actie: 'verwijderen', id })
    });
    const data = await res.json();
    if (data.success) { toast('Foto verwijderd'); closeModal('modal-foto-viewer'); location.reload(); }
    else toast(data.error || 'Fout', 'error');
}

document.addEventListener('DOMContentLoaded', () => {
    setupUploadZone();
});
