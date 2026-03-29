<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/page-definitions.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function absolute_url(string $path = '/'): string
{
    $base = rtrim(site_config()['site_url'], '/');
    $cleanPath = '/' . ltrim($path, '/');

    return $cleanPath === '/' ? $base . '/' : $base . $cleanPath;
}

function asset_url(string $path): string
{
    return absolute_url('/assets/' . ltrim($path, '/'));
}

function page_catalog(): array
{
    static $pages;

    if ($pages !== null) {
        return $pages;
    }

    $pages = [
        'home' => ['path' => '/', 'label' => 'Home'],
        'services' => ['path' => '/services.php', 'label' => 'Services'],
        'plans' => ['path' => '/monthly-it-support-plans.php', 'label' => 'Plans'],
        'projects' => ['path' => '/one-off-it-projects.php', 'label' => 'Projects'],
        'about' => ['path' => '/about.php', 'label' => 'About'],
        'service-area' => ['path' => '/service-area.php', 'label' => 'Service Area'],
        'contact' => ['path' => '/contact.php', 'label' => 'Contact'],
        'faq' => ['path' => '/faq.php', 'label' => 'FAQ'],
        'privacy-policy' => ['path' => '/privacy-policy.php', 'label' => 'Privacy Policy'],
        'terms' => ['path' => '/terms.php', 'label' => 'Terms'],
        'managed-it-services' => ['path' => '/managed-it-services.php', 'label' => 'Managed IT Services'],
        'network-security' => ['path' => '/network-security.php', 'label' => 'Network Security'],
        'microsoft-365-services' => ['path' => '/microsoft-365-services.php', 'label' => 'Microsoft 365 Services'],
        'backup-disaster-recovery' => ['path' => '/backup-disaster-recovery.php', 'label' => 'Backup & Disaster Recovery'],
        'network-cabling-wifi' => ['path' => '/network-cabling-wifi.php', 'label' => 'Network Cabling & Wi-Fi'],
        'voip-business-phone-systems' => ['path' => '/voip-business-phone-systems.php', 'label' => 'VoIP Business Phone Systems'],
        'security-risk-assessments' => ['path' => '/security-risk-assessments.php', 'label' => 'Security Risk Assessments'],
        'compliance-readiness' => ['path' => '/compliance-readiness.php', 'label' => 'Compliance Readiness'],
        'endpoint-management' => ['path' => '/endpoint-management.php', 'label' => 'Endpoint Management'],
        'monthly-it-support-plans' => ['path' => '/monthly-it-support-plans.php', 'label' => 'Monthly IT Support Plans'],
        'one-off-it-projects' => ['path' => '/one-off-it-projects.php', 'label' => 'One-Off IT Projects'],
        'it-services-salt-lake-city' => ['path' => '/it-services-salt-lake-city.php', 'label' => 'IT Services Salt Lake City'],
        'managed-it-support-salt-lake-city' => ['path' => '/managed-it-support-salt-lake-city.php', 'label' => 'Managed IT Support Salt Lake City'],
        'microsoft-365-support-salt-lake-city' => ['path' => '/microsoft-365-support-salt-lake-city.php', 'label' => 'Microsoft 365 Support Salt Lake City'],
        'cybersecurity-salt-lake-city' => ['path' => '/cybersecurity-salt-lake-city.php', 'label' => 'Cybersecurity Salt Lake City'],
    ];

    return $pages;
}

function page_href(string $slug): string
{
    $pages = page_catalog();
    return $pages[$slug]['path'] ?? '/';
}

function page_url(string $slug): string
{
    return absolute_url(page_href($slug));
}

function page_definition(string $slug): array
{
    $definitions = site_page_definitions();

    if (!isset($definitions[$slug])) {
        throw new InvalidArgumentException('Unknown page definition: ' . $slug);
    }

    return $definitions[$slug];
}

function current_year(): string
{
    return date('Y');
}

function set_flash(array $payload): void
{
    ensure_session_started();
    $_SESSION['_flash'] = $payload;
}

function pull_flash(): ?array
{
    ensure_session_started();

    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return null;
    }

    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);

    return $flash;
}

function remember_form_input(array $input): void
{
    ensure_session_started();
    $_SESSION['_old_input'] = $input;
}

function old_input(string $key, string $default = ''): string
{
    ensure_session_started();
    return isset($_SESSION['_old_input'][$key]) && is_string($_SESSION['_old_input'][$key])
        ? $_SESSION['_old_input'][$key]
        : $default;
}

function clear_old_input(): void
{
    ensure_session_started();
    unset($_SESSION['_old_input']);
}

function normalize_page(array $page): array
{
    $page['path'] = $page['path'] ?? '/';
    $page['slug'] = $page['slug'] ?? trim($page['path'], '/');
    $page['nav_key'] = $page['nav_key'] ?? '';
    $page['title'] = $page['title'] ?? site_config()['site_name'];
    $page['description'] = $page['description'] ?? site_config()['tagline'];
    $page['canonical'] = absolute_url($page['path']);
    $page['og_type'] = $page['og_type'] ?? 'website';
    $page['body_class'] = $page['body_class'] ?? 'page-' . preg_replace('/[^a-z0-9\-]+/i', '-', $page['slug']);
    $page['contact_service_type'] = $page['contact_service_type'] ?? 'other';

    return $page;
}

function base_schemas(array $page): array
{
    $config = site_config();
    $schemas = [];

    $localBusiness = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $config['site_name'],
        'url' => $config['site_url'],
        'areaServed' => [
            '@type' => 'City',
            'name' => $config['city'] . ', ' . $config['region'],
        ],
        'serviceArea' => [
            '@type' => 'AdministrativeArea',
            'name' => $config['service_area'],
        ],
        'description' => $config['tagline'],
    ];

    if ($config['business_email'] !== '') {
        $localBusiness['email'] = $config['business_email'];
    }

    if ($config['business_phone'] !== '') {
        $localBusiness['telephone'] = $config['business_phone'];
    }

    $schemas[] = $localBusiness;
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $page['title'],
        'url' => $page['canonical'],
        'description' => $page['description'],
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $config['site_name'],
            'url' => $config['site_url'],
        ],
    ];

    if (!empty($page['faq_items'])) {
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(
                static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ],
                $page['faq_items']
            ),
        ];
    }

    if (in_array($page['template'] ?? '', ['service', 'local', 'commercial'], true)) {
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $page['schema_name'] ?? $page['hero_title'] ?? $page['title'],
            'serviceType' => $page['schema_service_type'] ?? ($page['hero_title'] ?? $page['title']),
            'provider' => [
                '@type' => 'LocalBusiness',
                'name' => $config['site_name'],
                'url' => $config['site_url'],
            ],
            'areaServed' => $config['city'] . ', ' . $config['region'],
            'url' => $page['canonical'],
            'description' => $page['description'],
        ];
    }

    return $schemas;
}

function render_schemas(array $page): void
{
    foreach (base_schemas($page) as $schema) {
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . PHP_EOL;
    }
}

function navigation_groups(): array
{
    return [
        'Services' => [
            'services',
            'managed-it-services',
            'network-security',
            'microsoft-365-services',
            'backup-disaster-recovery',
            'network-cabling-wifi',
            'voip-business-phone-systems',
            'security-risk-assessments',
            'compliance-readiness',
            'endpoint-management',
        ],
        'Engagements' => [
            'monthly-it-support-plans',
            'one-off-it-projects',
            'it-services-salt-lake-city',
            'managed-it-support-salt-lake-city',
            'microsoft-365-support-salt-lake-city',
            'cybersecurity-salt-lake-city',
        ],
        'Company' => [
            'about',
            'service-area',
            'faq',
            'contact',
        ],
    ];
}

function service_cards(array $slugs): array
{
    $content = [];
    foreach ($slugs as $slug) {
        $page = page_definition($slug);
        $content[] = [
            'title' => $page['card_title'] ?? $page['hero_title'] ?? $page['title'],
            'copy' => $page['card_copy'] ?? $page['description'],
            'href' => page_href($slug),
        ];
    }

    return $content;
}

function render_contact_form(string $context = 'general', string $heading = 'Book IT & Security Review', string $copy = 'Tell us what is unstable, exposed, or overdue. We will respond with a clear next step.'): void
{
    $flash = pull_flash();
    $statusClass = 'form-response';
    $statusMessage = '';

    if ($flash !== null) {
        $statusClass .= !empty($flash['success']) ? ' is-success' : ' is-error';
        $statusMessage = (string) ($flash['message'] ?? '');
    }

    $serviceType = old_input('service_type', old_input('prefill_service_type', ''));
    if ($serviceType === '' && isset($_GET['service'])) {
        $candidate = trim((string) $_GET['service']);
        if (array_key_exists($candidate, site_config()['allowed_service_types'])) {
            $serviceType = $candidate;
        }
    }
    ?>
    <section class="contact-panel" id="contact-form">
      <div class="contact-panel__intro">
        <span class="eyebrow">Request a Review</span>
        <h2><?= e($heading) ?></h2>
        <p><?= e($copy) ?></p>
      </div>
      <form class="site-form" action="<?= e(asset_url('contact-handler.php')) ?>" method="post" data-async-form novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form_context" value="<?= e($context) ?>">
        <div class="form-honeypot" aria-hidden="true">
          <label for="website">Leave this field blank</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" autocomplete="name" value="<?= e(old_input('name')) ?>" required>
            <p class="field-error" data-field-error="name"></p>
          </div>
          <div class="field">
            <label for="company">Company</label>
            <input id="company" name="company" type="text" autocomplete="organization" value="<?= e(old_input('company')) ?>" required>
            <p class="field-error" data-field-error="company"></p>
          </div>
          <div class="field">
            <label for="email">Work Email</label>
            <input id="email" name="email" type="email" autocomplete="email" value="<?= e(old_input('email')) ?>" required>
            <p class="field-error" data-field-error="email"></p>
          </div>
          <div class="field">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= e(old_input('phone')) ?>">
            <p class="field-error" data-field-error="phone"></p>
          </div>
          <div class="field field--full">
            <label for="service_type">Service Needed</label>
            <select id="service_type" name="service_type" required>
              <option value="">Select a service</option>
              <?php foreach (site_config()['allowed_service_types'] as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $serviceType === $value ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field-error" data-field-error="service_type"></p>
          </div>
          <div class="field field--full">
            <label for="message">What needs attention</label>
            <textarea id="message" name="message" rows="5" required><?= e(old_input('message')) ?></textarea>
            <p class="field-error" data-field-error="message"></p>
          </div>
        </div>
        <div class="form-actions">
          <button class="button button--primary" type="submit" data-submit-button>Book IT &amp; Security Review</button>
          <p class="form-meta">Salt Lake metro only. Monthly support and focused project work.</p>
        </div>
        <p class="<?= e($statusClass) ?>" aria-live="polite" data-form-status><?= e($statusMessage) ?></p>
      </form>
    </section>
    <?php
    clear_old_input();
}

function render_card_grid(array $items, string $modifier = ''): void
{
    $className = 'card-grid' . ($modifier !== '' ? ' ' . $modifier : '');
    echo '<div class="' . e($className) . '">';
    foreach ($items as $item) {
        echo '<article class="card">';
        echo '<h3>' . e($item['title']) . '</h3>';
        echo '<p>' . e($item['copy']) . '</p>';
        if (!empty($item['href']) && !empty($item['link_label'])) {
            echo '<a class="text-link" href="' . e($item['href']) . '">' . e($item['link_label']) . '</a>';
        } elseif (!empty($item['href'])) {
            echo '<a class="text-link" href="' . e($item['href']) . '">Review service</a>';
        }
        echo '</article>';
    }
    echo '</div>';
}

function render_feature_list(array $items, string $className = 'check-list'): void
{
    echo '<ul class="' . e($className) . '">';
    foreach ($items as $item) {
        echo '<li>' . e($item) . '</li>';
    }
    echo '</ul>';
}

function render_home(array $page): void
{
    ?>
    <section class="hero hero--home">
      <div class="hero__mesh" aria-hidden="true"></div>
      <div class="container hero__layout">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <div class="hero__actions">
            <a class="button button--primary" href="<?= e(page_href('contact')) ?>">Book IT &amp; Security Review</a>
            <a class="button button--ghost" href="<?= e(page_href('services')) ?>">Review Services</a>
          </div>
        </div>
        <aside class="hero__panel">
          <p class="hero__panel-label">Built for</p>
          <ul class="hero__panel-list">
            <?php foreach ($page['trust_points'] as $point): ?>
              <li><?= e($point) ?></li>
            <?php endforeach; ?>
          </ul>
        </aside>
      </div>
    </section>

    <section class="trust-strip">
      <div class="container trust-strip__inner">
        <?php foreach ($page['trust_strip'] as $item): ?>
          <span><?= e($item) ?></span>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Core Services</span>
          <h2>Practical support with a security-first operating standard.</h2>
        </div>
        <?php render_card_grid($page['core_services']); ?>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container split">
        <div>
          <span class="eyebrow">Why Bald Eagle</span>
          <h2>Measured response, disciplined execution, and no vague hand-waving.</h2>
        </div>
        <div class="stack">
          <?php foreach ($page['why_cards'] as $item): ?>
            <article class="statement">
              <h3><?= e($item['title']) ?></h3>
              <p><?= e($item['copy']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Engagement Models</span>
          <h2>Monthly support when systems need steady oversight. Projects when a specific problem needs to get done.</h2>
        </div>
        <div class="comparison">
          <?php foreach ($page['engagements'] as $item): ?>
            <article class="comparison__card">
              <h3><?= e($item['title']) ?></h3>
              <p><?= e($item['copy']) ?></p>
              <?php render_feature_list($item['points'], 'mini-list'); ?>
              <a class="button button--ghost button--small" href="<?= e($item['href']) ?>"><?= e($item['link_label']) ?></a>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section section--accent">
      <div class="container split">
        <div>
          <span class="eyebrow">Microsoft 365</span>
          <h2><?= e($page['m365']['title']) ?></h2>
          <p><?= e($page['m365']['copy']) ?></p>
        </div>
        <div class="card card--tall">
          <?php render_feature_list($page['m365']['points']); ?>
          <a class="button button--primary button--small" href="<?= e(page_href('microsoft-365-services')) ?>">See Microsoft 365 Services</a>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <div>
          <span class="eyebrow">Salt Lake Focus</span>
          <h2><?= e($page['service_area']['title']) ?></h2>
          <p><?= e($page['service_area']['copy']) ?></p>
        </div>
        <div class="card card--tall">
          <?php render_feature_list($page['service_area']['points']); ?>
          <a class="text-link" href="<?= e(page_href('service-area')) ?>">Review service area details</a>
        </div>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Process</span>
          <h2>Assess. Stabilize. Secure. Support.</h2>
        </div>
        <div class="process-grid">
          <?php foreach ($page['process'] as $item): ?>
            <article class="process-step">
              <span class="process-step__index"><?= e($item['step']) ?></span>
              <h3><?= e($item['title']) ?></h3>
              <p><?= e($item['copy']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <div>
          <span class="eyebrow">FAQ</span>
          <h2>Questions owners usually ask before they hand over critical systems.</h2>
        </div>
        <div class="faq-list">
          <?php foreach ($page['faq_items'] as $item): ?>
            <details class="faq-item">
              <summary><?= e($item['question']) ?></summary>
              <p><?= e($item['answer']) ?></p>
            </details>
          <?php endforeach; ?>
          <a class="text-link" href="<?= e(page_href('faq')) ?>">Read the full FAQ</a>
        </div>
      </div>
    </section>

    <section class="section section--accent">
      <div class="container">
        <?php render_contact_form('homepage', $page['final_cta']['title'], $page['final_cta']['copy']); ?>
      </div>
    </section>
    <?php
}

function render_service_like_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <div class="hero__actions">
            <a class="button button--primary" href="<?= e(page_href('contact')) . '?service=' . urlencode($page['contact_service_type']) ?>">Book IT &amp; Security Review</a>
            <?php if (!empty($page['secondary_cta'])): ?>
              <a class="button button--ghost" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container two-up">
        <article class="card card--tall">
          <span class="card__eyebrow">Pain Points</span>
          <h2>Where businesses usually feel the strain.</h2>
          <?php render_feature_list($page['pain_points']); ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Included</span>
          <h2>What the work actually covers.</h2>
          <?php render_feature_list($page['included']); ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container split">
        <article>
          <span class="eyebrow">Who It Is For</span>
          <h2><?= e($page['who_for_title']) ?></h2>
          <p><?= e($page['who_for_copy']) ?></p>
        </article>
        <article>
          <span class="eyebrow">Why It Matters</span>
          <h2><?= e($page['why_title']) ?></h2>
          <?php foreach ($page['why_paragraphs'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article class="card card--tall">
          <span class="card__eyebrow">Deliverables</span>
          <h2>Clear outputs, not vague promises.</h2>
          <?php render_feature_list($page['deliverables']); ?>
        </article>
        <article class="cta-box">
          <span class="eyebrow">Next Step</span>
          <h2><?= e($page['cta_title']) ?></h2>
          <p><?= e($page['cta_copy']) ?></p>
          <a class="button button--primary" href="<?= e(page_href('contact')) . '?service=' . urlencode($page['contact_service_type']) ?>">Book IT &amp; Security Review</a>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Internal Links</span>
          <h2>Related pages for the next decision.</h2>
        </div>
        <?php render_card_grid(service_cards($page['related_links']), 'card-grid--compact'); ?>
      </div>
    </section>
    <?php
}

function render_services_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Service Stack</span>
          <h2>Coverage for day-to-day support, infrastructure, and security work.</h2>
        </div>
        <?php render_card_grid($page['service_cards']); ?>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container split">
        <div>
          <span class="eyebrow">How Engagements Start</span>
          <h2>Every engagement begins with an assessment of risk, operational friction, and immediate priorities.</h2>
        </div>
        <div class="card card--tall">
          <?php render_feature_list($page['approach']); ?>
          <a class="button button--primary button--small" href="<?= e(page_href('contact')) ?>">Book IT &amp; Security Review</a>
        </div>
      </div>
    </section>
    <?php
}

function render_about_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">Operating Standard</span>
          <h2>Direct communication. Tight scope control. Security built into the work.</h2>
          <?php foreach ($page['paragraphs'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">What Clients Value</span>
          <?php render_feature_list($page['principles']); ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Where We Fit Best</span>
          <h2>Small teams that need fast resolution and competent oversight.</h2>
        </div>
        <?php render_card_grid($page['fit_cards']); ?>
      </div>
    </section>
    <?php
}

function render_service_area_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container two-up">
        <article class="card card--tall">
          <span class="card__eyebrow">Coverage</span>
          <h2>Salt Lake metro only.</h2>
          <?php render_feature_list($page['coverage_points']); ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Why This Matters</span>
          <h2>Local travel discipline keeps project windows predictable.</h2>
          <?php render_feature_list($page['why_local']); ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Typical Work</span>
          <h2>Most local engagements fall into these categories.</h2>
        </div>
        <?php render_card_grid($page['local_cards']); ?>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <?php render_contact_form('service-area', 'Need Salt Lake IT coverage that stays inside a clear operating radius?', 'Tell us where your office is, what is unstable, and what outcome you need.'); ?>
      </div>
    </section>
    <?php
}

function render_contact_page(array $page): void
{
    $config = site_config();
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
        <aside class="hero__panel">
          <p class="hero__panel-label">Service Limits</p>
          <ul class="hero__panel-list">
            <li><?= e($config['service_area']) ?></li>
            <li>Monthly support retainers</li>
            <li>Focused project engagements</li>
            <li>Microsoft 365 and security work</li>
          </ul>
        </aside>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">What To Send</span>
          <h2>Enough detail to understand urgency, exposure, and business impact.</h2>
          <?php render_feature_list($page['contact_points']); ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Response Standard</span>
          <?php render_feature_list($page['response_points']); ?>
        </article>
      </div>
    </section>

    <section class="section section--accent">
      <div class="container">
        <?php render_contact_form('contact-page', $page['form_title'], $page['form_copy']); ?>
      </div>
    </section>
    <?php
}

function render_faq_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container faq-list faq-list--full">
        <?php foreach ($page['faq_items'] as $item): ?>
          <details class="faq-item">
            <summary><?= e($item['question']) ?></summary>
            <p><?= e($item['answer']) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
}

function render_legal_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container legal-copy">
        <?php foreach ($page['sections'] as $section): ?>
          <section class="legal-copy__section">
            <h2><?= e($section['title']) ?></h2>
            <?php foreach ($section['paragraphs'] as $paragraph): ?>
              <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
}

function render_not_found_page(array $page): void
{
    ?>
    <section class="hero hero--interior hero--not-found">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <div class="hero__actions">
            <a class="button button--primary" href="<?= e(page_href('home')) ?>">Return Home</a>
            <a class="button button--ghost" href="<?= e(page_href('contact')) ?>">Contact Bald Eagle</a>
          </div>
        </div>
      </div>
    </section>
    <?php
}

function render_site_page(array $page): void
{
    $page = normalize_page($page);
    include __DIR__ . '/header.php';

    echo '<main id="main-content" class="site-main">';
    switch ($page['template']) {
        case 'home':
            render_home($page);
            break;
        case 'service':
        case 'commercial':
        case 'local':
            render_service_like_page($page);
            break;
        case 'services':
            render_services_page($page);
            break;
        case 'about':
            render_about_page($page);
            break;
        case 'service-area':
            render_service_area_page($page);
            break;
        case 'contact':
            render_contact_page($page);
            break;
        case 'faq':
            render_faq_page($page);
            break;
        case 'legal':
            render_legal_page($page);
            break;
        case '404':
            http_response_code(404);
            render_not_found_page($page);
            break;
        default:
            render_service_like_page($page);
            break;
    }
    echo '</main>';

    include __DIR__ . '/footer.php';
}
