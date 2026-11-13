<?php
/**
 * FOOTER LAYOUT - Adminator 2026 Redesign Footer
 */
$isFullScreenLayout = $isFullScreenLayout ?? false;
$extraScripts = $extraScripts ?? [];
?>
<?php if (!$isFullScreenLayout): ?>
        </main>
        <footer class="d-footer">
            <div>© 2026 · Sistem E-Voting PEMIRA Kampus (BEM & BPM)</div>
            <div class="d-footer-meta">
                <span>One Token · One Session · One Device</span>
                <span>Adminator 2026 UI</span>
            </div>
        </footer>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/views/layouts/modal_global.php'; ?>

<script src="<?= e(asset_url('js/app.js')) ?>?v=7"></script>
<?php foreach ($extraScripts as $scriptPath): ?>
    <script src="<?= e($scriptPath) ?><?= str_contains($scriptPath, 'http') ? '' : '?v=7' ?>"></script>
<?php endforeach; ?>
</body>
</html>
