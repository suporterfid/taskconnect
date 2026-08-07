/* Blocking, external pre-paint bootstrap. The typed controller continues synchronization after startup. */
;(function () {
  var preference = null
  try {
    var stored = window.localStorage.getItem('taskconnect.theme')
    if (stored === 'light' || stored === 'dark' || stored === 'system') preference = stored
  } catch (_) {
    // Storage may be unavailable; system preference remains the safe fallback.
  }

  var dark = preference === 'dark' || ((preference === null || preference === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches)
  var theme = dark ? 'dark' : 'light'
  var root = document.documentElement
  root.dataset.theme = theme
  root.classList.toggle('dark', dark)
  root.style.colorScheme = theme

  var themeColor = document.querySelector('meta[name="theme-color"]')
  if (themeColor) themeColor.setAttribute('content', dark ? '#191919' : '#FFFFFF')
})()
