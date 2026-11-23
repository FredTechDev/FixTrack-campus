        </div><!-- /.container -->
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <span><i class="fas fa-tools mr-1" aria-hidden="true"></i> <?= e(APP_NAME) ?> &middot; <?= e(APP_TAGLINE) ?></span>
                <span class="text-muted">&copy; <?= date('Y') ?> &middot; Version <?= e(APP_VERSION) ?> &middot; Built for campus facilities teams</span>
            </div>
        </div>
    </footer>
</div><!-- /.app-wrapper -->

<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(url('assets/js/validate.js')) ?>" defer></script>
<?php if (!empty($page_scripts)): ?>
    <?php foreach ((array)$page_scripts as $script): ?>
        <script src="<?= e(url($script)) ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
