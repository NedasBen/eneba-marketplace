// Starts the landing page's first API requests while the app bundle is still
// downloading, instead of after it has run (the LCP image waits on this data).
// lib/api/client.ts picks the responses up by exact URL, once, within 10 s.
// Public catalog GETs only: no credentials, no auth header.
;(function () {
  var meta = document.querySelector('meta[name="api-base-url"]')
  var base = (meta && meta.content ? meta.content : '').replace(/\/+$/, '') + '/api/v1'
  var path = location.pathname
  var paths = ['/catalog/filters?in_stock=1'] // header navigation, on every page

  if (path === '/') {
    paths.push('/homepage')
  } else if (path === '/store' && location.search === '') {
    paths.push('/products?in_stock=1&page=1&per_page=24')
  } else {
    var product = /^\/product\/([a-z0-9-]+)$/.exec(path)
    if (product) paths.push('/products/' + product[1])
  }

  var requests = {}
  for (var i = 0; i < paths.length; i++) {
    var url = base + paths[i]
    requests[url] = fetch(url, { headers: { Accept: 'application/json' }, credentials: 'omit' }).catch(function () {
      return null
    })
  }
  window.__apiPrefetch = requests
})()
