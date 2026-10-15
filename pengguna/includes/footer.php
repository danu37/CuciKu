<footer class="page-footer">
    <span>© <?= date('Y') ?> CuciKu</span>
    <span>Laundry Bersih, Hidup Lebih Praktis.</span>
</footer>
        </div>
    </main>
</div>

<!-- Modal Konfirmasi Logout Pengguna Modern -->
<div class="modal fade" id="modalKonfirmasiLogout" tabindex="-1" aria-labelledby="modalKonfirmasiLogoutLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content confirm-modal-custom border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-body text-center p-4">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Tutup"></button>

                <div class="confirm-icon-bubble mx-auto mb-3" style="width: 58px; height: 58px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; background: #fee2e2; color: #dc2626;">
                    <i class="bi bi-box-arrow-right"></i>
                </div>

                <h5 class="fw-bold text-dark mb-1" id="modalKonfirmasiLogoutLabel">Konfirmasi Keluar Akun</h5>
                <p class="text-muted small mb-4 px-2">
                    Apakah Anda yakin ingin keluar dari akun pelanggan Anda? Anda perlu masuk kembali untuk melihat riwayat pesanan.
                </p>

                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light border px-4 flex-grow-1" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <a href="../logout.php" class="btn btn-danger px-4 flex-grow-1 fw-semibold d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-box-arrow-right me-1"></i> Ya, Keluar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
