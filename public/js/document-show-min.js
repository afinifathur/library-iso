// public/js/document-show-min.js
document.addEventListener("DOMContentLoaded", function () {
    const openBtn = document.getElementById("openPdfBtn");
    const pdfWrapper = document.getElementById("pdfWrapper");
    const pdfIframe = document.getElementById("pdfIframe");

    // optional approve buttons to enable after open
    const mrBtn = document.getElementById("mrApproveBtn");
    const dirBtn = document.getElementById("dirApproveBtn");

    if (!openBtn || !pdfWrapper || !pdfIframe) return;

    openBtn.addEventListener("click", function () {
        if (!window.DOC_PDF_URL) {
            alert("PDF URL tidak tersedia.");
            return;
        }

        // set iframe src (browser PDF viewer)
        pdfIframe.src = window.DOC_PDF_URL;
        pdfWrapper.style.display = "block";

        // enable approve buttons if present
        if (mrBtn) mrBtn.disabled = false;
        if (dirBtn) dirBtn.disabled = false;

        // smooth scroll to viewer
        pdfWrapper.scrollIntoView({ behavior: "smooth" });
    });
});
