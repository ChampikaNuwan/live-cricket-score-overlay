<?php
require_once __DIR__.'/config.php';
$title = 'Terms of Service';
$content = '
<p><strong>Last Updated:</strong> '.date('F d, Y').'</p>
<p>By accessing or using the CricketLive Broadcast Scoreboard System ("the Service"), you agree to be bound by these Terms of Service. If you do not agree, do not use the Service.</p>

<h3>1. Account Responsibility</h3>
<p>You are responsible for maintaining the confidentiality of your login credentials. All activities under your account are your responsibility. Notify us immediately of any unauthorized use.</p>

<h3>2. License Usage</h3>
<p>The Service is provided under a subscription license model. Each company requires a valid license key. License keys are non-transferable and may not be shared across companies. Usage is limited to the number of scorer accounts specified in your license.</p>

<h3>3. Acceptable Use</h3>
<p>You agree not to: (a) reverse engineer, decompile, or disassemble the software; (b) resell, sublicense, or distribute the Service to third parties; (c) use the Service for any illegal purpose; (d) attempt to gain unauthorized access to other accounts or systems.</p>

<h3>4. Data Ownership</h3>
<p>All match data, team rosters, player information, and scoring records entered into the system belong to the account holder. We claim no ownership over your data.</p>

<h3>5. Service Availability</h3>
<p>We strive to maintain 99.9% uptime but do not guarantee uninterrupted service. Scheduled maintenance will be announced in advance when possible. We are not liable for losses caused by service interruptions.</p>

<h3>6. Termination</h3>
<p>We reserve the right to suspend or terminate accounts that violate these Terms. Upon termination, data may be retained for 30 days before permanent deletion. License fees are non-refundable upon termination for cause.</p>

<h3>7. Limitation of Liability</h3>
<p>SWARNA MEDIA NETWORK (PVT) Ltd. shall not be liable for any indirect, incidental, or consequential damages arising from the use of the Service. Our total liability is limited to the amount paid for the current license period.</p>

<h3>8. Contact</h3>
<p>For questions about these Terms, contact us at <a href="https://wa.me/+94760717728">WhatsApp: +94 76 071 7728</a>.</p>
';
include __DIR__.'/policy_template.php';
