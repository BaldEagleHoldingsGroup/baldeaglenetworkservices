<?php
declare(strict_types=1);

ensure_session_started();

$page = normalize_page($page ?? []);
$config = site_config();
$navItems = [
    'services' => 'Services',
    'plans' => 'Plans',
    'projects' => 'Projects',
    'about' => 'About',
    'service-area' => 'Service Area',
    'contact' => 'Contact',
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page['title']) ?></title>
  <meta name="description" content="<?= e($page['description']) ?>">
  <meta name="robots" content="<?= e($page['robots'] ?? 'index,follow') ?>">
  <link rel="canonical" href="<?= e($page['canonical']) ?>">
  <meta name="theme-color" content="#0c1524">
  <meta property="og:site_name" content="<?= e($config['site_name']) ?>">
  <meta property="og:type" content="<?= e($page['og_type']) ?>">
  <meta property="og:title" content="<?= e($page['title']) ?>">
  <meta property="og:description" content="<?= e($page['description']) ?>">
  <meta property="og:url" content="<?= e($page['canonical']) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($page['title']) ?>">
  <meta name="twitter:description" content="<?= e($page['description']) ?>">
  <link rel="icon" href="<?= e(absolute_url('/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset_url('css/baldeagle.css')) ?>">
  <?php render_schemas($page); ?>
</head>
<body class="<?= e($page['body_class']) ?>">
  <a class="skip-link" href="#main-content">Skip to content</a>
  <div class="site-shell">
    <header class="site-header" data-site-header>
      <div class="container header-bar">
        <a class="brand" href="<?= e(page_href('home')) ?>" aria-label="<?= e($config['site_name']) ?> home">
          <span class="brand__mark" aria-hidden="true">BE</span>
          <span class="brand__text">
            <strong><?= e($config['site_name']) ?></strong>
            <small><?= e($config['tagline']) ?></small>
          </span>
        </a>

        <nav class="desktop-nav" aria-label="Primary">
          <ul>
            <?php foreach ($navItems as $slug => $label): ?>
              <li><a href="<?= e(page_href($slug)) ?>"<?= $page['nav_key'] === $slug ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>

        <div class="header-actions">
          <a class="button button--primary button--small desktop-cta" href="<?= e(page_href('contact')) ?>">Book IT &amp; Security Review</a>
          <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" data-nav-toggle>
            <span class="nav-toggle__label">Menu</span>
            <span class="nav-toggle__bars" aria-hidden="true">
              <span></span>
              <span></span>
              <span></span>
            </span>
          </button>
        </div>
      </div>
    </header>

    <div class="mobile-menu-backdrop" hidden data-mobile-backdrop></div>
    <aside class="mobile-menu" id="mobile-menu" aria-label="Mobile navigation" aria-hidden="true" data-mobile-menu inert>
      <div class="mobile-menu__inner">
        <div class="mobile-menu__top">
          <p class="mobile-menu__title"><?= e($config['site_name']) ?></p>
          <button class="mobile-menu__close" type="button" data-nav-close aria-label="Close navigation">Close</button>
        </div>
        <?php foreach (navigation_groups() as $group => $slugs): ?>
          <div class="mobile-menu__group">
            <p><?= e($group) ?></p>
            <ul>
              <?php foreach ($slugs as $slug): ?>
                <li><a href="<?= e(page_href($slug)) ?>"><?= e(page_catalog()[$slug]['label']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
        <a class="button button--primary mobile-menu__cta" href="<?= e(page_href('contact')) ?>">Book IT &amp; Security Review</a>
      </div>
    </aside>
