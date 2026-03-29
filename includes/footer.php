<?php
declare(strict_types=1);

$config = site_config();
?>
    <footer class="site-footer">
      <div class="container footer-grid">
        <div>
          <p class="footer-title"><?= e($config['site_name']) ?></p>
          <p class="footer-copy">Security-first IT support, Microsoft 365 administration, infrastructure projects, and risk reduction for Salt Lake businesses with 5 to 20 employees.</p>
        </div>
        <div>
          <p class="footer-title">Core Pages</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('services')) ?>">Services</a></li>
            <li><a href="<?= e(page_href('monthly-it-support-plans')) ?>">Plans</a></li>
            <li><a href="<?= e(page_href('one-off-it-projects')) ?>">Projects</a></li>
            <li><a href="<?= e(page_href('service-area')) ?>">Service Area</a></li>
          </ul>
        </div>
        <div>
          <p class="footer-title">Security &amp; Cloud</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('network-security')) ?>">Network Security</a></li>
            <li><a href="<?= e(page_href('microsoft-365-services')) ?>">Microsoft 365</a></li>
            <li><a href="<?= e(page_href('security-risk-assessments')) ?>">Risk Assessments</a></li>
            <li><a href="<?= e(page_href('compliance-readiness')) ?>">Compliance</a></li>
          </ul>
        </div>
        <div>
          <p class="footer-title">Company</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('about')) ?>">About</a></li>
            <li><a href="<?= e(page_href('faq')) ?>">FAQ</a></li>
            <li><a href="<?= e(page_href('privacy-policy')) ?>">Privacy Policy</a></li>
            <li><a href="<?= e(page_href('terms')) ?>">Terms</a></li>
          </ul>
        </div>
      </div>
      <div class="container footer-meta">
        <p>&copy; <?= e(current_year()) ?> <?= e($config['site_name']) ?>. Salt Lake metro only.</p>
      </div>
    </footer>
  </div>
  <script src="<?= e(asset_url('js/site.js')) ?>" defer></script>
</body>
</html>
