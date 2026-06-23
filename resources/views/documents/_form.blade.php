{{-- resources/views/documents/_form.blade.php --}}
@php
    $defaultUploadType = old('upload_type', isset($document) ? 'replace' : 'new');
@endphp

<style>
/* Modern scoped CSS for create document form */
.form-section-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);
    border: 1px solid #f3f4f6;
    margin-bottom: 24px;
}
.form-section-title {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
.grid-full {
    grid-column: span 2;
}
@media (max-width: 768px) {
    .grid-full {
        grid-column: span 1;
    }
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.form-group-label {
    font-size: 13px;
    font-weight: 600;
    color: #4b5563;
}
.required-star {
    color: #ef4444;
}
.modern-input {
    width: 100%;
    height: 44px;
    border-radius: 10px;
    border: 1px solid #d1d5db;
    background-color: #ffffff;
    padding: 0 16px;
    font-size: 14px;
    color: #1f2937;
    transition: all 0.2s ease;
}
.modern-input:hover {
    border-color: #9ca3af;
}
.modern-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.modern-select-wrapper {
    position: relative;
    display: block;
    width: 100%;
}
.modern-select {
    width: 100%;
    height: 44px;
    border-radius: 10px;
    border: 1px solid #d1d5db;
    background-color: #ffffff;
    padding: 0 40px 0 16px;
    font-size: 14px;
    color: #1f2937;
    appearance: none;
    -webkit-appearance: none;
    cursor: pointer;
    transition: all 0.2s ease;
}
.modern-select:hover {
    border-color: #9ca3af;
}
.modern-select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.modern-select-arrow {
    position: absolute;
    top: 50%;
    right: 16px;
    transform: translateY(-50%);
    pointer-events: none;
    width: 18px;
    height: 18px;
    color: #6b7280;
}
.modern-textarea {
    width: 100%;
    border-radius: 10px;
    border: 1px solid #d1d5db;
    background-color: #ffffff;
    padding: 12px 16px;
    font-size: 14px;
    color: #1f2937;
    transition: all 0.2s ease;
    resize: vertical;
}
.modern-textarea:hover {
    border-color: #9ca3af;
}
.modern-textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.textarea-helper {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
    line-height: 1.4;
}
.upload-card-wrapper {
    margin-bottom: 8px;
}
.upload-card-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 2px;
}
.upload-card-format {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.upload-card {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    background-color: #f9fafb;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.upload-card:hover {
    border-color: #2563eb;
    background-color: #eff6ff;
}
.upload-card.file-selected {
    border-color: #10b981;
    background-color: #ecfdf5;
}
.upload-card-icon {
    font-size: 32px;
    color: #6b7280;
    margin-bottom: 8px;
}
.upload-card:hover .upload-card-icon {
    color: #2563eb;
}
.upload-card.file-selected .upload-card-icon {
    color: #10b981;
}
.upload-card-btn-text {
    font-size: 14px;
    font-weight: 600;
    color: #2563eb;
    margin-bottom: 4px;
}
.upload-card.file-selected .upload-card-btn-text {
    color: #047857;
}
.upload-card-status {
    font-size: 13px;
    color: #6b7280;
}
.file-success-name {
    font-weight: 600;
    color: #065f46;
}
.version-label-container {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.version-badge-display {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
    font-size: 14px;
    font-weight: 700;
    border-radius: 8px;
    height: 44px;
    width: fit-content;
    padding: 0 20px;
    cursor: default;
    user-select: none;
}
.form-actions-bar {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
}
.btn-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 46px;
    padding: 0 24px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
}
.btn-modern-primary {
    background-color: #2563eb;
    color: #ffffff;
}
.btn-modern-primary:hover {
    background-color: #1d4ed8;
}
.btn-modern-primary:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
}
.btn-modern-primary:disabled {
    background-color: #9ca3af;
    cursor: not-allowed;
}
.btn-modern-secondary {
    background-color: #f3f4f6;
    color: #4b5563;
}
.btn-modern-secondary:hover {
    background-color: #e5e7eb;
    color: #1f2937;
}
.btn-modern-secondary:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.2);
}
</style>

<form method="POST"
      action="{{ $action }}"
      enctype="multipart/form-data">

    @csrf
    @if(!empty($method) && strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{-- Hidden metadata fields used by JS --}}
    <input type="hidden" name="mode" value="{{ old('mode', $defaultUploadType === 'replace' ? 'replace' : 'new') }}">
    <input type="hidden" name="submit_for" value="{{ old('submit_for', $defaultUploadType === 'replace' ? 'draft' : 'publish') }}">

    {{-- CARD 1: Informasi Dokumen --}}
    <div class="form-section-card">
        <div class="form-section-title">
            <span class="material-symbols-outlined" style="font-size: 20px;">info</span>
            Informasi Dokumen
        </div>
        <div class="form-grid">
            {{-- UPLOAD TYPE (New | Replace) --}}
            <div class="form-group grid-full">
                <label class="form-group-label">Jenis pengajuan <span class="required-star">*</span></label>
                <div class="modern-select-wrapper">
                    <select id="upload_type_select" name="upload_type" class="modern-select" required>
                        <option value="">-- pilih jenis pengajuan --</option>
                        <option value="new" {{ $defaultUploadType === 'new' ? 'selected' : '' }}>Dokumen Baru (Baseline v1)</option>
                        <option value="replace" {{ $defaultUploadType === 'replace' ? 'selected' : '' }}>Ganti Versi Lama (Buat Draft)</option>
                    </select>
                    <span class="modern-select-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </div>
                <div class="textarea-helper">
                    Pilih <strong>Dokumen Baru</strong> untuk membuat baseline v1. Pilih <strong>Ganti Versi Lama</strong> untuk membuat versi pengganti sebagai draft yang masuk Draft Container.
                </div>
            </div>

            {{-- CATEGORY --}}
            <div class="form-group">
                <label class="form-group-label">Category <span class="required-star">*</span></label>
                <div class="modern-select-wrapper">
                    <select name="category_id" class="modern-select" required>
                        <option value="">-- pilih kategori --</option>
                        @foreach($categories ?? [] as $cat)
                            @php
                                $selectedCategory = old('category_id', $document->category_id ?? null);
                            @endphp
                            <option value="{{ $cat->id }}" {{ $selectedCategory == $cat->id ? 'selected' : '' }}>
                                {{ $cat->code ?? $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    <span class="modern-select-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </div>
                @error('category_id')
                    <div class="textarea-helper text-danger" style="color: #ef4444;">{{ $message }}</div>
                @enderror
            </div>

            {{-- DEPARTMENT --}}
            <div class="form-group">
                <label class="form-group-label">Department <span class="required-star">*</span></label>
                <div class="modern-select-wrapper">
                    <select name="department_id" class="modern-select" required>
                        <option value="">-- pilih department --</option>
                        @foreach($departments as $d)
                            @php
                                $selectedDept = old('department_id', $document->department_id ?? null);
                            @endphp
                            <option value="{{ $d->id }}" {{ $selectedDept == $d->id ? 'selected' : '' }}>
                                {{ $d->code }} — {{ $d->name }}
                            </option>
                        @endforeach
                    </select>
                    <span class="modern-select-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </div>
                @error('department_id')
                    <div class="textarea-helper text-danger" style="color: #ef4444;">{{ $message }}</div>
                @enderror
            </div>

            {{-- DOC CODE --}}
            <div class="form-group">
                <label class="form-group-label">Kode Dokumen <span class="required-star">*</span></label>
                <input type="text"
                       name="doc_code"
                       id="doc_code_input"
                       value="{{ old('doc_code', $document->doc_code ?? '') }}"
                       class="modern-input"
                       placeholder="Contoh: IK.GUD-BHN.01"
                       required>
                <div class="textarea-helper">
                    Wajib dan harus unik. Contoh: IK.GUD-BHN.01
                </div>
                @error('doc_code')
                    <div class="textarea-helper text-danger" style="color: #ef4444;">{{ $message }}</div>
                @enderror
            </div>

            {{-- TITLE --}}
            <div class="form-group">
                <label class="form-group-label">Title <span class="required-star">*</span></label>
                <input type="text"
                       name="title"
                       value="{{ old('title', $document->title ?? '') }}"
                       class="modern-input"
                       required>
                @error('title')
                    <div class="textarea-helper text-danger" style="color: #ef4444;">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- CARD 2: Relasi Dokumen --}}
    <div class="form-section-card">
        <div class="form-section-title">
            <span class="material-symbols-outlined" style="font-size: 20px;">link</span>
            Relasi Dokumen
        </div>
        <div class="form-group">
            <label class="form-group-label">Dokumen terkait (satu URL per baris)</label>
            @php
                $relatedLinksDefault = '';
                if (isset($document) && is_array($document->related_links)) {
                    $relatedLinksDefault = implode("\n", $document->related_links);
                } elseif (old('related_links') !== null) {
                    $relatedLinksDefault = old('related_links');
                }
            @endphp
            <textarea name="related_links"
                      rows="3"
                      class="modern-textarea"
                      placeholder="http://..."
                      style="min-height: 80px;">{{ old('related_links', $relatedLinksDefault) }}</textarea>
            <div class="textarea-helper">
                Masukkan URL/hyperlink dokumen terkait, satu baris = satu link.
            </div>
            @error('related_links')
                <div class="textarea-helper text-danger" style="color: #ef4444;">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- CARD 3: Lampiran --}}
    <div class="form-section-card">
        <div class="form-section-title">
            <span class="material-symbols-outlined" style="font-size: 20px;">attachment</span>
            Lampiran
        </div>
        <div class="form-grid">
            {{-- MASTER FILE --}}
            <div class="upload-card-wrapper">
                <label class="upload-card-label">
                    Master File <span class="required-star">*</span>
                </label>
                <div class="upload-card-format">DOC / DOCX / XLS / XLSX</div>
                <div class="upload-card" onclick="document.getElementById('master_file_input').click()">
                    <input type="file"
                           id="master_file_input"
                           name="master_file"
                           accept=".doc,.docx,.xls,.xlsx"
                           style="display:none;"
                           {{ empty($document) ? 'required' : '' }}>
                    <span class="material-symbols-outlined upload-card-icon">cloud_upload</span>
                    <div class="upload-card-btn-text">[ Upload Master File ]</div>
                    <div id="master_file_status" class="upload-card-status">Belum ada file dipilih</div>
                </div>
                @if(!empty($document))
                    @php
                        $currentVersion = $document->relationLoaded('currentVersion')
                            ? $document->currentVersion
                            : ($document->currentVersion ?? null);
                    @endphp
                    @if($currentVersion && $currentVersion->file_path)
                        <div class="textarea-helper" style="margin-top: 8px;">
                            Master sebelumnya: <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">{{ basename($currentVersion->file_path) }}</code>
                        </div>
                    @endif
                @endif
                @error('master_file')
                    <div class="textarea-helper text-danger" style="color: #ef4444;">{{ $message }}</div>
                @enderror
            </div>

            {{-- PDF PREVIEW --}}
            <div class="upload-card-wrapper">
                <label class="upload-card-label">Upload PDF</label>
                <div class="upload-card-format">PDF format only (optional)</div>
                <div class="upload-card" onclick="document.getElementById('pdf_file_input').click()">
                    <input type="file"
                           id="pdf_file_input"
                           name="file"
                           accept="application/pdf"
                           style="display:none;">
                    <span class="material-symbols-outlined upload-card-icon">picture_as_pdf</span>
                    <div class="upload-card-btn-text">[ Upload PDF File ]</div>
                    <div id="pdf_file_status" class="upload-card-status">Belum ada file dipilih</div>
                </div>
                @error('file')
                    <div class="textarea-helper text-danger" style="color: #ef4444;">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- CARD 4: Konten & Revisi --}}
    <div class="form-section-card">
        <div class="form-section-title">
            <span class="material-symbols-outlined" style="font-size: 20px;">edit_note</span>
            Konten & Revisi
        </div>
        <div class="form-grid">
            {{-- VERSION LABEL --}}
            <div class="form-group grid-full">
                <div class="version-label-container">
                    <div class="version-label-title">Versi Sistem</div>
                    <div class="version-badge-display">
                        {{ old('version_label', $version_label ?? 'v1') }}
                    </div>
                    <input type="hidden" name="version_label" value="{{ old('version_label', $version_label ?? 'v1') }}">
                </div>
            </div>

            {{-- PASTED TEXT --}}
            <div class="form-group grid-full">
                <label class="form-group-label">Salin Isi Dokumen (Wajib)</label>
                @php
                    $pastedDefault = old('pasted_text', isset($document) ? optional($document->relationLoaded('currentVersion') ? $document->currentVersion : ($document->currentVersion ?? null))->pasted_text ?? '' : '');
                @endphp
                <textarea name="pasted_text"
                          rows="8"
                          class="modern-textarea"
                          style="min-height: 180px;">{{ $pastedDefault }}</textarea>
                <div class="textarea-helper">
                    Digunakan untuk pencarian isi dokumen.
                </div>
                @error('pasted_text')
                    <div class="textarea-helper text-danger" style="color: #ef4444;">{{ $message }}</div>
                @enderror
            </div>

            {{-- CHANGE NOTE --}}
            <div class="form-group grid-full">
                <label class="form-group-label">Catatan Perubahan <span class="required-star">*</span></label>
                <textarea name="change_note"
                          rows="3"
                          class="modern-textarea"
                          placeholder="Jelaskan perubahan yang dilakukan pada dokumen ini. Informasi ini digunakan untuk review dan approval."
                          style="min-height: 100px;"
                          required>{{ old('change_note') }}</textarea>
                <div class="textarea-helper">
                    Jelaskan tujuan perubahan dokumen ini.
                </div>
            </div>
        </div>
    </div>

    {{-- hidden metadata fields (optional) --}}
    <input type="hidden" name="doc_number" value="{{ old('doc_number', $document->doc_number ?? '') }}">
    <input type="hidden" name="approved_by" value="{{ old('approved_by', $document->approved_by ?? '') }}">

    {{-- BUTTON ACTIONS --}}
    <div class="form-actions-bar">
        <button type="button" class="btn-modern btn-modern-secondary" id="cancelFormBtn">Kembali</button>
        <button type="submit" class="btn-modern btn-modern-primary" id="mainSubmitBtn">
            Ajukan Dokumen
        </button>
    </div>
</form>

<script>
(function () {
    try {
        const form = document.currentScript && document.currentScript.previousElementSibling && document.currentScript.previousElementSibling.tagName === 'FORM'
            ? document.currentScript.previousElementSibling
            : document.querySelector('form.page-card') || document.querySelector('form');

        if (!form) return;

        const uploadSelect = form.querySelector('select[name="upload_type"]') || document.getElementById('upload_type_select');
        const modeHidden = form.querySelector('input[name="mode"]');
        const submitHidden = form.querySelector('input[name="submit_for"]');
        const mainBtn = form.querySelector('#mainSubmitBtn');
        const cancelBtn = form.querySelector('#cancelFormBtn');

        function setStateByType(type) {
            if (!type || type === '') {
                if (mainBtn) mainBtn.textContent = 'Ajukan Dokumen';
                if (modeHidden) modeHidden.value = 'new';
                if (submitHidden) submitHidden.value = 'publish';
                return;
            }
            if (type === 'new') {
                if (mainBtn) mainBtn.textContent = 'Simpan Dokumen Baru';
                if (modeHidden) modeHidden.value = 'new';
                if (submitHidden) submitHidden.value = 'publish';
            } else if (type === 'replace') {
                if (mainBtn) mainBtn.textContent = 'Kirim Revisi ke Draft Container';
                if (modeHidden) modeHidden.value = 'replace';
                if (submitHidden) submitHidden.value = 'draft';
            } else {
                if (mainBtn) mainBtn.textContent = 'Ajukan Dokumen';
                if (modeHidden) modeHidden.value = type;
                if (submitHidden) submitHidden.value = (type === 'replace' ? 'draft' : 'publish');
            }
        }

        // JS listeners for upload cards
        const masterInput = document.getElementById('master_file_input');
        const masterStatus = document.getElementById('master_file_status');
        if (masterInput && masterStatus) {
            const masterCard = masterInput.closest('.upload-card');
            masterInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    masterStatus.innerHTML = `✓ <span class="file-success-name">${this.files[0].name}</span>`;
                    if (masterCard) masterCard.classList.add('file-selected');
                } else {
                    masterStatus.textContent = 'Belum ada file dipilih';
                    if (masterCard) masterCard.classList.remove('file-selected');
                }
            }, { passive: true });
        }

        const pdfInput = document.getElementById('pdf_file_input');
        const pdfStatus = document.getElementById('pdf_file_status');
        if (pdfInput && pdfStatus) {
            const pdfCard = pdfInput.closest('.upload-card');
            pdfInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    pdfStatus.innerHTML = `✓ <span class="file-success-name">${this.files[0].name}</span>`;
                    if (pdfCard) pdfCard.classList.add('file-selected');
                } else {
                    pdfStatus.textContent = 'Belum ada file dipilih';
                    if (pdfCard) pdfCard.classList.remove('file-selected');
                }
            }, { passive: true });
        }

        // AJAX duplicate doc_code checker
        const docCodeInput = document.getElementById('doc_code_input');
        if (docCodeInput) {
            docCodeInput.addEventListener('blur', function () {
                const code = docCodeInput.value.trim();
                const uploadSelectEl = form.querySelector('select[name="upload_type"]') || document.getElementById('upload_type_select');
                if (code && uploadSelectEl && uploadSelectEl.value === 'new') {
                    fetch('{{ route('documents.checkCode') }}?code=' + encodeURIComponent(code))
                        .then(res => res.json())
                        .then(data => {
                            if (data.exists) {
                                if (window.Swal) {
                                    Swal.fire({
                                        title: 'Kode Dokumen Sudah Digunakan',
                                        html: `Kode dokumen <strong>${data.doc_code}</strong> sudah terdaftar sebagai:<br><br><strong>${data.title}</strong><br><br><span style="color:#d33;font-weight:bold;">Silakan gunakan menu "Ganti Versi Lama (Buat Draft)"</span>`,
                                        icon: 'warning',
                                        confirmButtonText: 'OK',
                                        confirmButtonColor: '#1d4ed8'
                                    });
                                } else {
                                    alert(`Kode dokumen sudah digunakan:\n${data.doc_code}\n${data.title}\n\nSilakan gunakan menu "Ganti Versi Lama (Buat Draft)"`);
                                }
                            }
                        })
                        .catch(err => console.error('Error checking code:', err));
                }
            });
        }

        // init state
        const initialType = (uploadSelect && uploadSelect.value) || (modeHidden && modeHidden.value) || '';
        setStateByType(initialType);

        if (uploadSelect) {
            uploadSelect.addEventListener('change', function (e) {
                setStateByType(e.target.value);
            }, { passive: true });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                try {
                    if (window.history && window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = document.referrer || '{{ url()->current() }}';
                    }
                } catch (e) {
                    window.location.reload();
                }
            }, { passive: true });
        }

        // final guard
        form.addEventListener('submit', function (ev) {
            const type = (uploadSelect && uploadSelect.value) || (modeHidden && modeHidden.value) || '';
            if (!type) {
                ev.preventDefault();
                alert('Silakan pilih jenis pengajuan: Dokumen Baru atau Ganti Versi Lama.');
                if (uploadSelect) uploadSelect.focus();
                return false;
            }
            return true;
        });
    } catch (err) {
        console.error('form script error', err);
    }
})();
</script>
