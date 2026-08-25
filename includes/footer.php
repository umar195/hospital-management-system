        </main>
        <footer class="app-footer no-print">
            <span>&copy; <?= date('Y') ?> <?= sanitize(getSetting('hospital_name', 'City Care Hospital')) ?></span>
            <span class="text-muted"><?= APP_NAME ?> v<?= APP_VERSION ?></span>
        </footer>
    </div>
</div>
<!-- Bootstrap bundle. For offline use download bootstrap.bundle.min.js into assets/js/ (see README). -->
<script src="<?= asset_url('assets/js/bootstrap.bundle.min.js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js') ?>"></script>
<?php if (!empty($useCharts)): ?>
<script src="<?= asset_url('assets/js/chart.umd.min.js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js') ?>"></script>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
