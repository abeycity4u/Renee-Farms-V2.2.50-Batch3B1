<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renee Farms | Precision Agriculture & Livestock Intelligence</title>
    <meta name="theme-color" content="#1b4332">
    <meta name="description" content="Renee Farms blends sustainable livestock care with data-driven operations for healthier food systems.">

    <link rel="icon" href="assets/images/favicon.ico?v=2024.06.01" type="image/x-icon" sizes="any">
    <link rel="apple-touch-icon" href="assets/images/favicon.ico?v=2024.06.01">


    <link rel="stylesheet" href="assets/css/home-page.css">
</head>
<body>
    <header class="topbar">
        <div class="container nav">
            <a class="brand" href="index.php" aria-label="Renee Farms home">
                <img src="assets/images/logo.jpg?v=2024.06.01" alt="Renee Farms logo" width="42" height="42" decoding="async">
                <div>
                    <h1>RENEE FARMS LTD</h1>
                    <small>Eat Healthy With Renee Farms</small>
                </div>
            </a>
            <a class="btn btn-primary" href="sign.php">Launch farm portal</a>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <div>
                <span class="kicker" aria-label="Smart, sustainable, scalable"><span aria-hidden="true">🟢</span> Smart <span aria-hidden="true">•</span> Sustainable <span aria-hidden="true">•</span> Scalable</span>
                <h4>Welcome to Renee Smart System — where advanced farm operations meet intelligent livestock management.</h4>
                <p>
                    From poultry and ruminant performance to inventory forecasting and expense intelligence,
                    Renee Farms delivers a modern agricultural ecosystem built for productivity, transparency,
                    and long-term ecological stewardship.
                </p>
                <p style="margin-top:1.25rem;color:#365446;font-weight:500;">Use <strong>Launch farm portal</strong> in the top navigation to access your workspace.</p>
                <div class="metrics" role="list" aria-label="Farm highlights">
                    <article class="metric" role="listitem"><strong>24/7</strong><span>Operational visibility</span></article>
                    <article class="metric" role="listitem"><strong>Data-first</strong><span>Production decisions</span></article>
                    <article class="metric" role="listitem"><strong>Eco-led</strong><span>Farm sustainability model</span></article>
                </div>
            </div>

            <aside class="visual" aria-label="Featured farm visual">
                <div class="slideshow-frame">
                    <img
                        id="hero-slideshow-image-current"
                        class="is-active"
                        src="assets/images/chick.png?v=2024.06.01"
                        alt="Chick standing in farm grass"
                        width="420"
                        height="420"
                        fetchpriority="high"
                        decoding="async"
                    >
                    <img
                        id="hero-slideshow-image-next"
                        src="assets/images/chick.png?v=2024.06.01"
                        alt=""
                        width="420"
                        height="420"
                        loading="eager"
                        decoding="async"
                        aria-hidden="true"
                    >
                </div>
                <div class="floating-card" aria-hidden="true">
                    <div><strong>+18% Yield</strong><span>Feed optimization</span></div>
                    <div><strong>Live Dashboards</strong><span>Operational analytics</span></div>
                    <div><strong>Risk Alerts</strong><span>Faster interventions</span></div>
                </div>
            </aside>
        </section>

        <section class="section-grid" aria-label="Core capabilities">
            <article class="feature" data-index="01">
                <h3>Integrated Production Hub</h3>
                <p>Track broilers, layers, and ruminants from intake to output in one coherent digital workflow.</p>
            </article>
            <article class="feature" data-index="02">
                <h3>Financial Command Layer</h3>
                <p>Connect expense records, sales, and stock movement to understand true farm profitability in real time.</p>
            </article>
            <article class="feature" data-index="03">
                <h3>Operational Intelligence</h3>
                <p>Turn raw records into smart planning signals that strengthen consistency, quality, and growth.</p>
            </article>
        </section>
    </main>

    <footer class="footer">
        <div class="container">&copy; 2026 Renee Farms Ltd. All rights reserved.</div>
    </footer>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/homepage-slideshow.js'); ?>"></script>
</body>
</html>
