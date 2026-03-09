(function () {
  'use strict';

  var layouts = Array.prototype.slice.call(document.querySelectorAll('.wf-shop-layout'));
  if (!layouts.length) {
    return;
  }

  var mobileBreakpoint = window.matchMedia('(max-width: 860px)');
  var requestNonce =
    typeof window.wfShopFilters === 'object' && window.wfShopFilters && window.wfShopFilters.nonce
      ? String(window.wfShopFilters.nonce)
      : '';
  var controllers = [];

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
    if (requestNonce && !query.get('wf_nonce')) {
      query.set('wf_nonce', requestNonce);
    }

    destination.search = query.toString();
    return destination.toString();
  }

  function getIncomingLayout(html, layoutIndex) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    var incomingLayouts = doc.querySelectorAll('.wf-shop-layout');

    if (!incomingLayouts.length) {
      throw new Error('Missing shop layout in response.');
    }

    return incomingLayouts[layoutIndex] || incomingLayouts[0];
  }

  function syncBodyScrollState() {
    var hasOpenDrawer = document.querySelector('.wf-shop-layout.wf-drawer-open') !== null;
    document.body.classList.toggle('wf-no-scroll', hasOpenDrawer);
  }

  function initLayout(layout, layoutIndex) {
    var priceDebounceTimer = null;
    var currentController = null;
    var isDrawerOpen = false;

    function setLoading(isLoading) {
      layout.classList.toggle('wf-is-loading', !!isLoading);
    }

    function setDrawerState(nextState) {
      isDrawerOpen = !!nextState;
      layout.classList.toggle('wf-drawer-open', isDrawerOpen);

      var toggle = layout.querySelector('.wf-filter-toggle');
      if (toggle) {
        toggle.setAttribute('aria-expanded', isDrawerOpen ? 'true' : 'false');
      }

      syncBodyScrollState();
    }

    function closeDrawer() {
      setDrawerState(false);
    }

    function initMobileDrawer() {
      if (!mobileBreakpoint.matches) {
        closeDrawer();
      }
    }

    function swapFromResponse(html) {
      var incomingLayout = getIncomingLayout(html, layoutIndex);
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

      initPriceSliders();
      initOptionSearch();
    }

    function initOptionSearch() {
      var searchInputs = layout.querySelectorAll('.wf-option-search');
      searchInputs.forEach(function (input) {
        var listId = input.getAttribute('data-list-id') || '';
        var list = listId ? document.getElementById(listId) : null;
        if (!list) {
          return;
        }

        function filterItems() {
          var query = String(input.value || '')
            .trim()
            .toLowerCase();
          var items = list.querySelectorAll('li');

          items.forEach(function (item) {
            var label = item.querySelector('label span');
            var text = label ? String(label.textContent || '').trim().toLowerCase() : '';
            item.classList.toggle('wf-option-hidden', query !== '' && text.indexOf(query) === -1);
          });
        }

        input.addEventListener('input', filterItems);
        filterItems();
      });
    }

    function parseFloatSafe(value, fallback) {
      var numeric = Number.parseFloat(value);
      return Number.isFinite(numeric) ? numeric : fallback;
    }

    function clamp(value, min, max) {
      return Math.min(max, Math.max(min, value));
    }

    function formatDecimal(value) {
      return String(Number(value.toFixed(2)));
    }

    function dispatchInputEvent(target) {
      target.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function initPriceSliders() {
      var forms = layout.querySelectorAll('.wf-filter-form');
      forms.forEach(function (form) {
        var slider = form.querySelector('.wf-price-slider');
        var minInput = form.querySelector('input[name="min_price"]');
        var maxInput = form.querySelector('input[name="max_price"]');

        if (!slider || !minInput || !maxInput) {
          return;
        }

        var rangeMin = slider.querySelector('.wf-price-range-min');
        var rangeMax = slider.querySelector('.wf-price-range-max');
        var trackFill = slider.querySelector('.wf-price-track-fill');

        if (!rangeMin || !rangeMax || !trackFill) {
          return;
        }

        var sliderMin = parseFloatSafe(slider.dataset.min, 0);
        var sliderMax = parseFloatSafe(slider.dataset.max, sliderMin + 100);
        if (sliderMax <= sliderMin) {
          sliderMax = sliderMin + 100;
        }

        function updateTrack(minValue, maxValue) {
          var range = sliderMax - sliderMin;
          if (range <= 0) {
            trackFill.style.left = '0%';
            trackFill.style.width = '100%';
            return;
          }

          var minPercent = ((minValue - sliderMin) / range) * 100;
          var maxPercent = ((maxValue - sliderMin) / range) * 100;
          trackFill.style.left = minPercent + '%';
          trackFill.style.width = Math.max(0, maxPercent - minPercent) + '%';
        }

        function syncFromNumberInputs(source) {
          var minValue = parseFloatSafe(minInput.value, sliderMin);
          var maxValue = parseFloatSafe(maxInput.value, sliderMax);

          minValue = clamp(minValue, sliderMin, sliderMax);
          maxValue = clamp(maxValue, sliderMin, sliderMax);

          if (minValue > maxValue) {
            if (source === 'min') {
              maxValue = minValue;
            } else {
              minValue = maxValue;
            }
          }

          rangeMin.value = formatDecimal(minValue);
          rangeMax.value = formatDecimal(maxValue);
          updateTrack(minValue, maxValue);
        }

        function syncFromRangeInputs(source) {
          var minValue = parseFloatSafe(rangeMin.value, sliderMin);
          var maxValue = parseFloatSafe(rangeMax.value, sliderMax);

          if (minValue > maxValue) {
            if (source === 'min') {
              maxValue = minValue;
              rangeMax.value = formatDecimal(maxValue);
            } else {
              minValue = maxValue;
              rangeMin.value = formatDecimal(minValue);
            }
          }

          minInput.value = formatDecimal(minValue);
          maxInput.value = formatDecimal(maxValue);
          updateTrack(minValue, maxValue);

          dispatchInputEvent(minInput);
          dispatchInputEvent(maxInput);
        }

        rangeMin.addEventListener('input', function () {
          syncFromRangeInputs('min');
        });

        rangeMax.addEventListener('input', function () {
          syncFromRangeInputs('max');
        });

        minInput.addEventListener('input', function () {
          syncFromNumberInputs('min');
        });

        maxInput.addEventListener('input', function () {
          syncFromNumberInputs('max');
        });

        syncFromNumberInputs('');
      });
    }

    function requestAndSwap(url, options) {
      var opts = options || {};
      var requestedUrl = new URL(url, window.location.origin);

      if (requestNonce && !requestedUrl.searchParams.get('wf_nonce')) {
        requestedUrl.searchParams.set('wf_nonce', requestNonce);
      }
      url = requestedUrl.toString();

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
          closeDrawer();

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
        if (target.classList.contains('wf-option-search')) {
          return;
        }

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
      var target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      if (target.closest('.wf-filter-toggle')) {
        event.preventDefault();
        setDrawerState(!isDrawerOpen);
        return;
      }

      if (target.closest('.wf-sidebar-close') || target.closest('.wf-sidebar-overlay')) {
        event.preventDefault();
        closeDrawer();
        return;
      }

      var link = event.target.closest('a');
      if (!link || !isPrimaryClick(event) || !isNavigableLink(link)) {
        return;
      }

      var allowedArea = link.closest('.woocommerce-pagination, .wf-per-page, .wf-active-filters, .wf-top-active-filters, .wf-actions, .wf-empty-actions');
      if (!allowedArea) {
        return;
      }

      event.preventDefault();
      requestAndSwap(link.href, { scrollToTop: true });
    });

    initPriceSliders();
    initOptionSearch();
    initMobileDrawer();

    return {
      reload: function () {
        return requestAndSwap(window.location.href, { pushState: false });
      },
      close: closeDrawer
    };
  }

  layouts.forEach(function (layout, index) {
    controllers.push(initLayout(layout, index));
  });

  window.addEventListener('popstate', function () {
    controllers.forEach(function (controller) {
      controller.reload();
    });
  });

  document.addEventListener('keydown', function (event) {
    if ('Escape' === event.key) {
      controllers.forEach(function (controller) {
        controller.close();
      });
    }
  });

  function handleBreakpointChange() {
    if (!mobileBreakpoint.matches) {
      controllers.forEach(function (controller) {
        controller.close();
      });
    }
  }

  if (typeof mobileBreakpoint.addEventListener === 'function') {
    mobileBreakpoint.addEventListener('change', handleBreakpointChange);
  } else if (typeof mobileBreakpoint.addListener === 'function') {
    mobileBreakpoint.addListener(handleBreakpointChange);
  }
})();
