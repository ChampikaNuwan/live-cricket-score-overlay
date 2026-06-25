<?php
require_once __DIR__.'/config.php';
$title = 'Refund Policy';
$content = '
<p><strong>Last Updated:</strong> '.date('F d, Y').'</p>

<h3>1. License Fees</h3>
<p>License fees for CricketLive are generally non-refundable once a license key has been generated and activated. We encourage all customers to thoroughly evaluate the system before purchasing a license.</p>

<h3>2. Trial Period</h3>
<p>We offer a demonstration and testing period. Please use this opportunity to verify that the system meets your requirements before committing to a license purchase.</p>

<h3>3. Eligible Refunds</h3>
<p>Refunds may be considered on a case-by-case basis under the following circumstances:</p>
<ul>
<li>Technical issues that prevent the core functionality from working and cannot be resolved within 14 days.</li>
<li>Duplicate payments made in error.</li>
<li>License purchased for the wrong company (must be reported within 48 hours).</li>
</ul>

<h3>4. Non-Refundable Cases</h3>
<p>The following are not eligible for refunds:</p>
<ul>
<li>Change of mind after purchase.</li>
<li>Failure to use the system during the license period.</li>
<li>Incompatibility with third-party software not listed in our requirements.</li>
<li>Accounts terminated for violation of Terms of Service.</li>
</ul>

<h3>5. Refund Process</h3>
<p>To request a refund, contact us via WhatsApp with your license key and reason for the request. We will review and respond within 5 business days. Approved refunds will be processed within 14 business days.</p>

<h3>6. Contact</h3>
<p>For refund inquiries, contact us at <a href="https://wa.me/+94760717728">WhatsApp: +94 76 071 7728</a>.</p>
';
include __DIR__.'/policy_template.php';
