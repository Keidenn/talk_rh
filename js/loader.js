(function(){
  let refCount = 0;
  function getBackdrop(){ return document.getElementById('talkrhLoaderBackdrop'); }
  function update(){
    const el = getBackdrop();
    if (!el) return;
    if (refCount > 0) {
      el.style.display = 'flex';
    } else {
      el.style.display = 'none';
    }
  }
  window.talkrhLoader = {
    show: () => { refCount++; update(); },
    hide: () => { refCount = Math.max(0, refCount - 1); update(); },
    reset: () => { refCount = 0; update(); },
    _debugCount: () => refCount,
  };
})();
