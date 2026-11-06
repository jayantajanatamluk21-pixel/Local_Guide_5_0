(()=>{const b=document.querySelector('.mobile-nav-toggle'),n=document.querySelector('.main-nav');if(b&&n)b.addEventListener('click',()=>{const o=n.classList.toggle('nav-open');b.setAttribute('aria-expanded',o?'true':'false');b.innerHTML=o?'<i class="fa-solid fa-xmark"></i>':'<i class="fa-solid fa-bars"></i>';});})();if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
