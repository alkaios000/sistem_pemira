<?php
/**
 * MODAL GLOBAL - Adminator 2026 Redesign Confirm Modal
 */
?>
<div class="modal-backdrop" id="globalConfirmModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-head">
            <h3 class="modal-title" id="globalConfirmTitle">Konfirmasi Tindakan</h3>
            <button type="button" class="modal-close" data-modal-close="globalConfirmModal">×</button>
        </div>
        <div class="modal-body">
            <p id="globalConfirmMessage">Apakah Anda yakin ingin melanjutkan tindakan ini?</p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn--ghost btn--sm" data-modal-close="globalConfirmModal">Batal</button>
            <button type="button" class="btn btn--primary btn--sm" id="globalConfirmOkBtn">Ya, Lanjutkan</button>
        </div>
    </div>
</div>
