// public/js/upload-type-toggle.js
document.addEventListener("DOMContentLoaded", function () {
    const uploadType = document.getElementById("upload_type");
    const submitBtn = document.getElementById("submitBtn");
    const submitNote = document.getElementById("submitNote");

    if (!uploadType || !submitBtn) return;

    function updateButton() {
        const val = uploadType.value;
        if (!val) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Simpan";
            submitNote.textContent = "Pilih jenis pengajuan terlebih dahulu.";
            return;
        }

        submitBtn.disabled = false;
        if (val === "new") {
            submitBtn.textContent = "Save & Publish as Baseline (v1)";
            submitNote.textContent =
                "Dokumen akan langsung dipublish sebagai baseline v1.";
        } else if (val === "replace") {
            submitBtn.textContent = "Save as Draft (New Version)";
            submitNote.textContent =
                "Versi baru akan disimpan sebagai draft dan masuk ke Draft container.";
        } else {
            submitBtn.textContent = "Simpan";
            submitNote.textContent = "";
        }
    }

    // initial update
    updateButton();

    uploadType.addEventListener("change", updateButton);

    // Extra: prevent form submit if upload_type empty (defense in depth)
    const uploadForm = document.getElementById("uploadForm");
    if (uploadForm) {
        uploadForm.addEventListener("submit", function (ev) {
            if (!uploadType.value) {
                ev.preventDefault();
                alert("Silahkan pilih jenis pengajuan (wajib).");
                uploadType.focus();
            }
        });
    }
});
