<?php
require_once __DIR__.'/config.php';
$title = 'Privacy Policy';
$content = '
<p><strong>Last Updated:</strong> '.date('F d, Y').'</p>
<p>SWARNA MEDIA NETWORK (PVT) Ltd. ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use the CricketLive Broadcast Scoreboard System.</p>

<h3>1. Information We Collect</h3>
<p><strong>Account Information:</strong> When you create an account, we collect your username, email address, and a securely hashed password. We do not store plain-text passwords.</p>
<p><strong>Match Data:</strong> All cricket match data — including team lineups, player statistics, ball-by-ball records, and scorecards — is stored securely and belongs to your organization.</p>
<p><strong>Usage Data:</strong> We may collect non-personal information such as browser type, device information, and pages visited to improve our service.</p>

<h3>2. How We Use Your Information</h3>
<p>We use the collected information solely for operating and improving the CricketLive platform. Your match data and personal information are never sold, rented, or shared with third parties for marketing purposes.</p>

<h3>3. Data Security</h3>
<p>We implement industry-standard security measures including BCRYPT password hashing, PDO prepared statements to prevent SQL injection, and session-based authentication with periodic ID regeneration. All data transmission occurs over standard HTTP protocols.</p>

<h3>4. Cookies</h3>
<p>We use session cookies strictly for authentication purposes. We do not use tracking cookies, advertising cookies, or any third-party cookies. You can disable cookies in your browser, but this may prevent you from logging in.</p>

<h3>5. Data Retention</h3>
<p>Your account data and match records are retained as long as your account is active. When a company license expires, access is suspended but data is preserved for 90 days before permanent deletion.</p>

<h3>6. Your Rights</h3>
<p>You have the right to access, correct, or request deletion of your personal data. Contact us via WhatsApp to exercise these rights. We will respond within 30 days.</p>

<h3>7. Contact</h3>
<p>For privacy-related inquiries, contact us at <a href="https://wa.me/+94760717728">WhatsApp: +94 76 071 7728</a>.</p>
';
include __DIR__.'/policy_template.php';
