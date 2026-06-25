<style>
.ftr{text-align:center;padding:24px 16px 32px;font-size:11px;color:var(--text-mute,#64748b);line-height:1.7;background:var(--bg2,#0b1020);border-top:1px solid var(--border,rgba(255,255,255,0.06));margin-top:20px}
.ftr-grid{display:flex;flex-wrap:wrap;justify-content:center;gap:8px 18px;margin-bottom:12px}
.ftr-grid a{color:var(--text-dim,#94a3b8);text-decoration:none;font-size:11px;transition:color 0.2s}
.ftr-grid a:hover{color:var(--accent,#f97316)}
.ftr-grid span{color:var(--text-mute,#475569)}
.ftr strong{color:var(--text,#0f172a)}
.ftr .heart{color:#ef4444}
@media(max-width:768px){
 .ftr{padding:18px 14px 26px;font-size:10px;margin-top:16px}
 .ftr-grid{gap:6px 12px;margin-bottom:10px}.ftr-grid a{font-size:10px}
}
@media(max-width:500px){
 .ftr{padding:16px 10px 24px;font-size:9px}
 .ftr-grid{gap:4px 10px}.ftr-grid a{font-size:9px}
}
/* Make footer visible on mobile admin pages */
@media(max-width:768px){
 body{overflow-y:auto!important}#app{min-height:100vh!important;height:auto!important}
 #content,#panel,#main{overflow-y:visible!important;max-height:none!important}
}
</style>
<div class="ftr">
    <div class="ftr-grid">
        <a href="privacy.php">Privacy Policy</a><span>|</span>
        <a href="terms.php">Terms of Service</a><span>|</span>
        <a href="refund.php">Refund Policy</a><span>|</span>
        <a href="copyright.php">Copyright</a><span>|</span>
        <a href="about.php">About</a><span>|</span>
        <a href="contact.php">Contact</a>
    </div>
    <div>&copy; <?=date('Y')?> <strong>SWARNA MEDIA NETWORK (PVT) Ltd.</strong> All rights reserved.</div>
    <div>Designed &amp; Developed with <span class="heart">&#10084;</span> by <a href="https://wa.me/+94766237857" target="_blank" rel="noopener" style="color:var(--accent,#f97316);font-weight:600;text-decoration:none">Champika Nuwan</a></div>
</div>
