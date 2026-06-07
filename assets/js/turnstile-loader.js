(function(){
  if (!window.nrfm_turnstile || !window.nrfm_turnstile.src) { return; }
  var s = document.createElement('script');
  s.src = window.nrfm_turnstile.src;
  s.async = true; s.defer = true;
  document.head.appendChild(s);
})();


