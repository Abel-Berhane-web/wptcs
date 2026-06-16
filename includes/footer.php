<?php if (isLoggedIn()): ?>
        </div><!-- /.page-content -->
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->
<?php else: ?>
</div><!-- /.auth-wrapper -->
<?php endif; ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

</body>
</html>
