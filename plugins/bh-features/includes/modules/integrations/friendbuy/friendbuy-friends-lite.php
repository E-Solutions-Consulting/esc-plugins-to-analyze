
<!doctype html>
<html lang="en-US" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Friends - BrelloHealth</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="https://www.brellohealth.com/wp-content/uploads/2026/01/cropped-Brello-Favicon-588-x-588-px-2-32x32.png" sizes="32x32">
<style>
    :root{
    --bh-purple:#1F0A59;
    --bh-purple-light:#6750A7;
    --bh-cream:#FEFBF4;
    --bh-yellow:#F9F8A2;
    --bh-yellow-hover:#F4D053;
  }
  *{box-sizing:border-box;}
  body{
    margin:0;
    font-family:"Switzer","Inter",Arial,sans-serif;
    color:var(--bh-purple);
    background:#fff;
  }
  a{text-decoration:none;color:inherit;}

  /* Header */
  .bh-header{
    display:flex;
    padding:1rem 1.5rem;
    border-bottom:1px solid #eee;
    position:relative;
  }
  .bh-header img{height:40px;display:block;}

  /* Desktop nav */
  .bh-nav ul{
    display:flex;
    gap:1.5rem;
    align-items:center;
    list-style:none;
    margin:0;
    padding:0;
  }
  .bh-nav li{position:relative;margin:0;}
  .bh-nav > ul > li > a{font-size: 18px;font-weight:500;padding:0.5rem 0;display:inline-block;}

  /* CTA-style buttons (matches bh-menu-button classes from the real menu) */
  .bh-nav li.bh-menu-button > a,
  .bh-nav li.get-started > a{
    background:var(--bh-yellow);
    color:var(--bh-purple);
    padding:0.6rem 1.2rem;
    border-radius:100px;
    font-weight:600;
    font-style:italic;
    font-family:"Gambetta",serif;
    display:inline-block;
  }
  .bh-nav li.bh-menu-button > a:hover,
  .bh-nav li.get-started > a:hover{background:var(--bh-yellow-hover);}

  /* Dropdown submenus - desktop (hover) */
  .bh-nav .sub-menu{
    display:none;
    position:absolute;
    top:100%;
    left:0;
    background:#fff;
    min-width:220px;
    box-shadow:0 8px 24px rgba(0,0,0,0.12);
    border-radius:10px;
    padding:0.5rem 0;
    flex-direction:column;
    gap:0;
    z-index:50;
  }
  .bh-nav li:hover > .sub-menu{display:flex;}
  .bh-nav .sub-menu li{width:100%;}
  .bh-nav .sub-menu a{
    display:block;
    padding:0.6rem 1.2rem;
    font-size:0.9rem;
    white-space:nowrap;
  }
  .bh-nav .sub-menu a:hover{background:#f7f5ff;}
  .bh-nav .menu-item-has-children > a:after{
    content:"";
    display:inline-block;
    width:6px;
    height:6px;
    border-right:1.5px solid currentColor;
    border-bottom:1.5px solid currentColor;
    transform:rotate(45deg);
    margin-left:6px;
  }

  /* Hamburger toggle - hidden on desktop */
  .bh-menu-toggle{
    display:none;
    background:none;
    border:none;
    cursor:pointer;
    padding:0.5rem;
    width:40px;
    height:40px;
    position:relative;
  }
  .bh-menu-toggle span,
  .bh-menu-toggle span:before,
  .bh-menu-toggle span:after{
    content:"";
    display:block;
    position:absolute;
    left:8px;
    right:8px;
    height:2px;
    background:var(--bh-purple);
    transition:transform 0.25s ease, opacity 0.25s ease;
  }
  .bh-menu-toggle span{top:19px;}
  .bh-menu-toggle span:before{top:-7px;}
  .bh-menu-toggle span:after{top:7px;}
  .bh-menu-toggle.is-open span{opacity:0;}
  .bh-menu-toggle.is-open span:before{transform:translateY(7px) rotate(45deg);}
  .bh-menu-toggle.is-open span:after{transform:translateY(-7px) rotate(-45deg);}

  /* Mobile collapsed panel */
  @media (max-width:768px){
    .bh-menu-toggle{display:block;}
    .bh-nav{
      position:fixed;
      top:0;
      right:-100%;
      width:min(85vw,360px);
      height:100vh;
      background:#fff;
      box-shadow:-8px 0 24px rgba(0,0,0,0.15);
      transition:right 0.3s ease;
      overflow-y:auto;
      padding:5rem 1.5rem 2rem;
      z-index:100;
    }
    .bh-nav.is-open{right:0;}
    .bh-nav ul{flex-direction:column;align-items:stretch;gap:0;}
    .bh-nav > ul > li{border-bottom:1px solid #f0f0f0;}
    .bh-nav > ul > li > a{padding:0.9rem 0;width:100%;}

    /* Submenus collapse as accordions instead of hover dropdowns */
    .bh-nav .sub-menu{
      display:none;
      position:static;
      box-shadow:none;
      border-radius:0;
      padding:0 0 0.5rem 1rem;
    }
    .bh-nav li.is-expanded > .sub-menu{display:flex;}
    .bh-nav .sub-menu a{padding:0.6rem 0;}
    .bh-nav li.menu-item-has-children > a{
      display:flex;
      justify-content:space-between;
      align-items:center;
    }

    .bh-nav-overlay{
      display:none;
      position:fixed;
      inset:0;
      background:rgba(0,0,0,0.35);
      z-index:90;
    }
    .bh-nav-overlay.is-open{display:block;}
  }

  /* Main content */
  main{
    max-width:1140px;
    margin:0 auto;
    padding:3rem 1.5rem 4rem;
    min-height:50vh;
  }

  /* Footer */
  .bh-footer{
    border-top:1px solid #eee;
    padding:2rem 1.5rem;
    text-align:center;
    font-size:0.85rem;
    color:#555;
  }
  .bh-footer .bh-footer-links{
    display:flex;
    gap:1.5rem;
    justify-content:center;
    margin-bottom:0.75rem;
    flex-wrap:wrap;
  }
  .bh-footer .bh-footer-links a{font-size:0.85rem;}

  @media (max-width:600px){
    .bh-header nav{display:none;}
  }

  a.bh-cta {
      border-radius: 25px;
      padding: 15px;
      justify-content: center;
      transition: all 0.4s;
  }
  .bh-nav.buttons a {
      background-color: #F9F8A2;
      color: #1F0A59 !important;
      font-family: 'Gambetta', serif;
      font-style: italic;
      font-weight: 600;
      font-size: 24px !important;
  }
  a.bh-cta.telehealth,
  .bh-cta.telehealth-login a {
      background-color: #1F0A59;
      color: #FEFBF4 !important;
      font-size: 20px !important;
  }
  .menu a{font-size: 18px;}
  .menu .get-started,
  .menu .telehealth-login {
      display: none;
  }

  nav#bhNav {
      display: flex;
      justify-content: space-between;
      flex: auto;
      gap: 50px;
      padding: 0 20px;
  }

  /* Custom CSS */
  body{
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";
  }
  .bh-header{
    align-items:center;
    justify-content:flex-start;
    gap:35px;
    min-height:76px;
    padding:0 max(24px,calc((100vw - 1440px) / 2));
    border-bottom:0;
    background:#fff;
    z-index:20;
  }
  .bh-header > a{flex:0 0 auto;}
  .bh-header img{width:140px;height:auto;}
  .bh-nav{align-items:center;}
  nav#bhNav{justify-content:flex-start;gap:0;padding:0;flex:0 1 auto;}
  .bh-nav .menu{gap:18px;flex-wrap:wrap;row-gap:10px;}
  .bh-nav > div > ul > li > a,
  .menu a{font-size:clamp(15px,2vw,18px);font-weight:400;color:var(--bh-purple);white-space:nowrap;}
  .bh-nav > div > ul > li > a{
    border-bottom:3px solid transparent;
    transition:border-color 0.3s ease;
  }
  .bh-nav > div > ul > li:hover > a{
    border-bottom-color:#3f444b;
  }
  .bh-nav .menu-item-has-children > a:after{
    width:0;
    height:0;
    margin-left:10px;
    border:5px solid transparent;
    border-top-color:currentColor;
    border-bottom:0;
    transform:none;
    vertical-align:middle;
  }
  .bh-nav .sub-menu{
    min-width:255px;
    top:calc(100% + 3px);
    padding:0;
    border-radius:0;
    box-shadow:none;
  }
  .bh-nav .sub-menu a{
    padding:14px 22px;
    font-size:clamp(15px,2vw,18px);
    color:var(--bh-purple);
  }
  .bh-nav .sub-menu a:hover{
    background:var(--bh-purple);
    color:#fff;
  }
  .bh-nav.buttons{display:flex;gap:20px;flex:0 0 auto;margin-left:auto;}
  .bh-nav.buttons a{
    font-size:18px!important;
    line-height:1.05;
    padding:13px 27px;
    border-radius:999px;
    text-align:center;
    white-space:normal;
    transition:background-color 0.3s ease,color 0.3s ease;
  }
  a.bh-cta:hover{background:#f4d053;}
  a.bh-cta.telehealth:hover{background:#6750a7;}
  a.bh-cta.telehealth{font-size:18px!important;font-family:"Switzer",sans-serif;font-style:normal;font-weight:500;}
  .bh-nav li.bh-menu-button > a{padding:13px 24px;border-radius:999px;}

  @media (max-width:1100px){
    .bh-header{padding:0 18px;}
    .bh-nav .menu{gap:12px;}
    .bh-nav.buttons{gap:10px;}
    .bh-nav.buttons a{padding:12px 18px;}
  }

  @media (min-width:769px) and (max-width:1024px){
    .bh-header{
      flex-wrap:wrap;
      align-content:center;
      gap:10px 24px;
      padding:16px 16px 24px;
    }
    .bh-header > a{order:1;}
    .bh-nav.buttons{order:2;margin-left:auto;}
    nav#bhNav{
      order:3;
      flex:0 0 100%;
    }
    .bh-nav .menu{
      justify-content:flex-start;
      gap:22px;
    }
  }

  @media (max-width:768px){
    .bh-header{
      justify-content:space-between;
      align-content:flex-start;
      flex-wrap:wrap;
      gap:0;
      min-height:72px;
      padding:14px 28px;
      position:relative;
    }
    .bh-header img{width:140px;height:auto;}
    .bh-menu-toggle{display:block;z-index:120;width:28px;height:28px;padding:0;}
    .bh-menu-toggle:focus,
    .bh-menu-toggle:focus-visible{
      outline:0;
      box-shadow:none;
    }
    .bh-menu-toggle span,
    .bh-menu-toggle span:before,
    .bh-menu-toggle span:after{height:3px;left:0;right:0;border-radius:4px;}
    .bh-menu-toggle span:before{top:-8px;}
    .bh-menu-toggle span:after{top:8px;}
    .bh-menu-toggle.is-open span{background:transparent;opacity:1;}
    .bh-menu-toggle.is-open span:before{transform:translateY(8px) rotate(45deg);}
    .bh-menu-toggle.is-open span:after{transform:translateY(-8px) rotate(-45deg);}
    .bh-nav.buttons{display:none;}
    .bh-header nav#bhNav{
      display:block;
      position:static;
      flex:0 0 100%;
      order:3;
      width:100%;
      height:auto;
      max-height:0;
      padding:0;
      background:#fff;
      box-shadow:none;
      overflow:hidden;
      transform:none;
      opacity:0;
      pointer-events:none;
      transition:max-height 0.35s ease,opacity 0.25s ease,padding 0.25s ease;
      z-index:110;
    }
    .bh-header nav#bhNav.is-open{
      max-height:1200px;
      padding:8px 0 0;
      opacity:1;
      pointer-events:auto;
    }
    .bh-nav .menu{display:flex;flex-direction:column;align-items:stretch;gap:0;}
    .bh-nav > div > ul > li{border-bottom:0;}
    .bh-nav > div > ul > li > a{
      display:block;
      width:100%;
      padding:8px 14px;
      font-size:18px;
      border-bottom:0;
      transition:background-color 0.3s ease,color 0.3s ease;
    }
    .bh-nav > div > ul > li:hover > a{border-bottom-color:transparent;}
    .bh-nav li.menu-item-has-children > a{display:flex;justify-content:flex-start;gap:8px;}
    .bh-nav li.is-expanded > a,
    .bh-nav > div > ul > li > a:active{
      background:var(--bh-purple);
      color:#fff;
    }
    .bh-nav .sub-menu{
      display:flex;
      position:static;
      max-height:0;
      overflow:hidden;
      box-shadow:none;
      border-radius:0;
      padding:0;
      transition:max-height 0.35s ease;
    }
    .bh-nav li:hover > .sub-menu{display:flex;}
    .bh-nav li.is-expanded > .sub-menu{max-height:420px;}
    .bh-nav .sub-menu a{
      padding:9px 22px;
      font-size:18px;
      transition:background-color 0.3s ease,color 0.3s ease;
    }
    .bh-nav .sub-menu a:hover,
    .bh-nav .sub-menu a:active,
    .bh-nav .sub-menu a:focus{
      background:var(--bh-purple);
      color:#fff;
    }
    .menu .telehealth-login,
    .menu .get-started{display:block;margin-top:12px;}
    .bh-nav li.bh-menu-button > a,
    .bh-nav li.get-started > a{
      display:block;
      width:100%;
      padding:13px 20px;
      text-align:center;
      font-size:18px!important;
    }
    .bh-nav li.telehealth-login > a{
      background:var(--bh-purple);
      color:#fff!important;
      font-family:inherit;
      font-style:normal;
      font-weight:400;
    }
    .bh-nav li.telehealth-login > a:hover,
    .bh-nav li.telehealth-login > a:active,
    .bh-nav li.telehealth-login > a:focus{
      background:#6750a7;
      color:#fff!important;
    }
  }
</style>
<!-- Friendbuy -->
<script>
window["friendbuyAPI"] = friendbuyAPI = window["friendbuyAPI"] || [];
friendbuyAPI.merchantId = "ed5ba9c9-6754-48e0-af79-63d3c33e4030";
friendbuyAPI.push(["merchant", friendbuyAPI.merchantId]);
(function(f, r, n, d, b, u, y) {
    while ((u = n.shift())) {
        (b = f.createElement(r)), (y = f.getElementsByTagName(r)[0]);
        b.async = 1;
        b.src = u;
        y.parentNode.insertBefore(b, y);
    }
})(document, "script", [
    "https://static.fbot.me/friendbuy.js",
    "https://campaign.fbot.me/" + friendbuyAPI.merchantId + "/campaigns.js",
]);
</script>
<!-- /Friendbuy -->
<link rel="dns-prefetch" href="//fonts.googleapis.com" data-set-by="Speed Optimizer by SiteGround"/><link rel="dns-prefetch" href="//fonts.gstatic.com" data-set-by="Speed Optimizer by SiteGround"/><link rel="dns-prefetch" href="//connect.facebook.net" data-set-by="Speed Optimizer by SiteGround"/><link rel="dns-prefetch" href="//googletagmanager.com" data-set-by="Speed Optimizer by SiteGround"/><link rel="dns-prefetch" href="//www.google-analytics.com" data-set-by="Speed Optimizer by SiteGround"/></head>
<body>

<header class="bh-header">
  <a href="https://www.brellohealth.com">
    <img src="https://www.brellohealth.com/wp-content/uploads/2026/01/cropped-brello-logo-2026.png" alt="BrelloHealth">
  </a>

  <button class="bh-menu-toggle" id="bhMenuToggle" aria-label="Toggle menu" aria-expanded="false">
    <span></span>
  </button>

  <nav class="bh-nav" id="bhNav">
    <?php
      wp_nav_menu([
          'menu' => 'New Menu (2)',
          'depth' => 3,
      ]);
    ?>
  </nav>
  <nav class="bh-nav buttons">
    <a class="bh-cta telehealth" href="https://app.brellohealth.com/login">Telehealth Login</a>
    <a class="bh-cta" href="https://www.brellohealth.com/start-wellness">Let's Get Started</a>
  </nav>
</header>

<main>
  <div id="friendbuyfriendpage"></div>
</main>

<footer class="bh-footer">
  <p>&copy; 2026 Brello. All Rights Reserved.</p>
</footer>

<!-- Mobile menu toggle -->
<script>
(function(){
  var toggle  = document.getElementById('bhMenuToggle');
  var nav     = document.getElementById('bhNav');

  toggle.addEventListener('click', function(){
    var open = nav.classList.toggle('is-open');
    toggle.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Accordion behaviour for dropdown items, mobile only.
  var parents = nav.querySelectorAll('.menu-item-has-children > a');
  parents.forEach(function(link){
    link.addEventListener('click', function(e){
      if (window.innerWidth > 768) return; // desktop uses hover, do nothing
      e.preventDefault();
      var li = link.parentElement;
      li.classList.toggle('is-expanded');
    });
  });
})();
</script>

</body>
</html>
