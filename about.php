<?php
require_once __DIR__.'/config.php';
$title = 'About Us';
$content = '
<h3>Who We Are</h3>
<p><strong>SWARNA MEDIA NETWORK (PVT) Ltd.</strong> is a Sri Lanka-based technology company specializing in broadcast solutions and live event production software. We develop tools that empower sports broadcasters, clubs, and media organizations to deliver professional-quality live coverage.</p>

<h3>Our Product</h3>
<p><strong>CricketLive</strong> is a professional broadcast scoreboard system designed for live cricket production. It features a real-time scoring engine, glassmorphic broadcast overlays compatible with OBS Studio and vMix, player photo management, and ICC-compliant scoring rules.</p>

<h3>Key Features</h3>
<ul>
<li>Ball-by-ball scoring with touch-friendly interface</li>
<li>Real-time SSE (Server-Sent Events) live data streaming</li>
<li>Transparent 1920×1080 broadcast overlay with GSAP animations</li>
<li>Multi-tenant architecture with company-based data isolation</li>
<li>Dark and light theme with persistent preference</li>
<li>ICC rules compliant: strike rotation, extras, maiden overs, economy rates</li>
<li>Full match summary with batting/bowling cards and key performer highlights</li>
</ul>

<h3>Our Mission</h3>
<p>To make professional cricket broadcasting accessible to everyone — from local school matches to international tournaments. We believe every cricket match deserves a broadcast-quality presentation.</p>

<h3>Contact</h3>
<p>For business inquiries, partnerships, or support: <a href="https://wa.me/+94760717728">WhatsApp: +94 76 071 7728</a>.</p>
';
include __DIR__.'/policy_template.php';
