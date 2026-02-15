(function () {
  'use strict';

  var layout = document.querySelector('.wf-shop-layout');
  if (!layout) {
    return;
  }

  var priceDebounceTimer = null;
  var currentController = null;

  function setLoading(isLoading) {
    layout.classList.toggle('wf-is-loading', !!isLoading);
  }

  function isPrimaryClick(event) {
    return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
  }

  function isSameOriginUrl(url) {
    try {
      var parsed = new URL(url, window.location.origin);
      return parsed.origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function isNavigableLink(link) {
    if (!link || !link.href) {
      return false;
    }

    if (link.target === '_blank' || link.hasAttribute('download')) {
      return false;
    }

    var href = link.getAttribute('href');
    if (!href || href.indexOf('#') === 0 || href.indexOf('javascript:') === 0) {
      return false;
    }

    return isSameOriginUrl(link.href);
  }

  function buildUrlFromForm(form) {
    var action = form.getAttribute('action') || window.location.pathname;
    var data = new FormData(form);
    var query = new URLSearchParams();

    data.forEach(function (value, key) {
      var normalized = String(value).trim();
      if (normalized !== '') {
        query.append(key, normalized);
      }
    });

    query.delete('paged');
    query.delete('product-page');

    var glue = action.indexOf('?') > -1 ? '&' : '?';
    var built = query.toString();

    return built ? action + glue + built : action;
  }

  function buildUrlFromOrderingForm(form) {
    var destination = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
    var query = new URLSearchParams(window.location.search);
    var data = new FormData(form);

    data.forEach(function (value, key) {
      query.set(key, String(value));
    });

    query.delete('paged');
    query.delete('product-page');

    destination.search = query.toString();
    return destination.toString();
  }

  function swapFromResponse(html) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    var incomingLayout = doc.querySelector('.wf-shop-layout');

    if (!incomingLayout) {
      throw new Error('Missing shop layout in response.');
    }

    var incomingSidebar = incomingLayout.querySelector('.wf-sidebar');
    var incomingProducts = incomingLayout.querySelector('.wf-products');
    var currentSidebar = layout.querySelector('.wf-sidebar');
    var currentProducts = layout.querySelector('.wf-products');

    if (incomingSidebar && currentSidebar) {
      currentSidebar.replaceWith(incomingSidebar);
    }

    if (incomingProducts && currentProducts) {
      currentProducts.replaceWith(incomingProducts);
    }
  }

  function requestAndSwap(url, options) {
    var opts = options || {};

    if (!isSameOriginUrl(url)) {
      window.location.href = url;
      return Promise.resolve();
    }

    if (currentController) {
      currentController.abort();
    }

    currentController = new AbortController();
    setLoading(true);

    return fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'text/html'
      },
      signal: currentController.signal
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Request failed with status ' + response.status);
        }

        return response.text();
      })
      .then(function (html) {
        swapFromResponse(html);

        if (opts.pushState !== false) {
          window.history.pushState({ wf: true }, '', url);
        }

        if (opts.scrollToTop) {
          layout.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      })
      .catch(function (error) {
        if (error && error.name === 'AbortError') {
          return;
        }

        window.location.href = url;
      })
      .finally(function () {
        setLoading(false);
      });
  }

  layout.addEventListener('submit', function (event) {
    var filterForm = event.target.closest('.wf-filter-form');
    if (filterForm) {
      event.preventDefault();
      requestAndSwap(buildUrlFromForm(filterForm));
      return;
    }

    var orderingForm = event.target.closest('form.woocommerce-ordering');
    if (orderingForm) {
      event.preventDefault();
      requestAndSwap(buildUrlFromOrderingForm(orderingForm));
    }
  });

  layout.addEventListener('change', function (event) {
    var target = event.target;
    if (!(target instanceof HTMLInputElement) && !(target instanceof HTMLSelectElement)) {
      return;
    }

    var filterForm = target.form;
    if (filterForm && filterForm.classList.contains('wf-filter-form')) {
      if (target.name === 'min_price' || target.name === 'max_price') {
        return;
      }

      requestAndSwap(buildUrlFromForm(filterForm));
      return;
    }

    if (target instanceof HTMLSelectElement && target.form && target.form.classList.contains('woocommerce-ordering')) {
      requestAndSwap(buildUrlFromOrderingForm(target.form));
    }
  });

  layout.addEventListener('input', function (event) {
    var target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }

    if (target.name !== 'min_price' && target.name !== 'max_price') {
      return;
    }

    var filterForm = target.form;
    if (!filterForm || !filterForm.classList.contains('wf-filter-form')) {
      return;
    }

    if (priceDebounceTimer) {
      window.clearTimeout(priceDebounceTimer);
    }

    priceDebounceTimer = window.setTimeout(function () {
      requestAndSwap(buildUrlFromForm(filterForm));
    }, 500);
  });

  layout.addEventListener('click', function (event) {
    var link = event.target.closest('a');
    if (!link || !isPrimaryClick(event) || !isNavigableLink(link)) {
      return;
    }

    var allowedArea = link.closest('.woocommerce-pagination, .wf-per-page, .wf-active-filters, .wf-actions');
    if (!allowedArea) {
      return;
    }

    event.preventDefault();
    requestAndSwap(link.href, { scrollToTop: true });
  });

  window.addEventListener('popstate', function () {
    requestAndSwap(window.location.href, { pushState: false });
  });
})();
