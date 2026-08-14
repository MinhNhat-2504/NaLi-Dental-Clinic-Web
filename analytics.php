<?php
$gaId=getenv('GA4_MEASUREMENT_ID')?:'';
if(preg_match('/^G-[A-Z0-9]+$/',$gaId)){
  $safe=htmlspecialchars($gaId,ENT_QUOTES,'UTF-8');
  echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$safe}\"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','{$safe}');document.addEventListener('click',function(e){var x=e.target.closest('a,button,form');if(!x)return;var t=(x.innerText||x.id||'').slice(0,80);if(/đặt lịch|booking|chat|gửi/i.test(t))gtag('event','nali_interaction',{label:t})})</script>";
}
?>
