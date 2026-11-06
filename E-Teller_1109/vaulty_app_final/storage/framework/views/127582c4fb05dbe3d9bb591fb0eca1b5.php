
<dialog class="et-dialog" id="etRejectDialog">
    <form method="POST" action="">
        <?php echo csrf_field(); ?>
        <div>
            <h3 data-reject-title>Tolak pengajuan</h3>
            <p>Tulis alasan penolakan. Alasan akan tercatat di sistem.</p>
        </div>
        <div class="field">
            <label for="etRejectReason">Alasan penolakan</label>
            <textarea id="etRejectReason" name="reason" rows="3" minlength="5" maxlength="500" required placeholder="Contoh: Nominal tidak sesuai bukti setoran"></textarea>
        </div>
        <div class="actions end">
            <button type="button" class="btn btn-soft" data-reject-close>Batal</button>
            <button type="submit" class="btn btn-danger-solid">Kirim Penolakan</button>
        </div>
    </form>
</dialog><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/components/reject-dialog.blade.php ENDPATH**/ ?>