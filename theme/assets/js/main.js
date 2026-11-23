/**
 * Front-end behaviour. Plain JavaScript, loaded deferred, no dependencies.
 *
 *  1. Navigation toggle (phones and tablets) and header shadow
 *  2. Sliders and carousels (scroll-snap tracks with arrows and dots)
 *  3. Map: loaded from Google only after a click
 *  4. Language switch remembers the chosen language and keeps the scroll position on
 *     pages that exist in both languages
 *  5. Chat widget (window.zbChat, set by Functions\Assets from content/site.json) and the
 *     note beside its button, shown on reaching the team section
 *  6. Services on the homepage: shows the illustration of the service in view
 *  7. Navigation: marks the link of the section in view
 *
 * Everything works without this file: the menu is reachable through the page,
 * sliders scroll by touch or keyboard, the map has a plain link.
 */
(function () {
  'use strict';

  var doc = document;
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };

  /* 1. Navigation -------------------------------------------------------- */

  var header = doc.querySelector('[data-header]');
  var toggle = doc.querySelector('[data-nav-toggle]');
  var menu = doc.querySelector('[data-nav]');

  function setNav(open, returnFocus) {
    if (!toggle || !menu) { return; }
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    menu.classList.toggle('is-open', open);
    if (!open && returnFocus) { toggle.focus(); }
  }

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      setNav(toggle.getAttribute('aria-expanded') !== 'true', false);
    });
    menu.addEventListener('click', function (event) {
      if (event.target.closest('a')) { setNav(false, false); }
    });
    doc.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') { setNav(false, true); }
    });
    doc.addEventListener('click', function (event) {
      if (header && !header.contains(event.target)) { setNav(false, false); }
    });
    window.matchMedia('(min-width: 64em)').addEventListener('change', function () { setNav(false, false); });
  }

  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* 2. Sliders ----------------------------------------------------------- */

  each(doc.querySelectorAll('[data-slider]'), function (slider) {
    var track = slider.querySelector('[data-slider-track]');
    var prev = slider.querySelector('[data-slider-prev]');
    var next = slider.querySelector('[data-slider-next]');
    var dotsBox = slider.querySelector('[data-slider-dots]');
    var slides = track ? track.children : [];
    var dots = [];

    if (!track || slides.length < 2) { return; }

    var behavior = function () { return reducedMotion.matches ? 'auto' : 'smooth'; };
    var step = function () {
      return slides[1].getBoundingClientRect().left - slides[0].getBoundingClientRect().left;
    };
    var maxScroll = function () { return track.scrollWidth - track.clientWidth; };
    var index = function () { return Math.round(track.scrollLeft / step()); };

    function go(direction) {
      var target = track.scrollLeft + direction * step();
      // Wrap around at both ends.
      if (direction > 0 && track.scrollLeft >= maxScroll() - 2) { target = 0; }
      if (direction < 0 && track.scrollLeft <= 2) { target = maxScroll(); }
      track.scrollTo({ left: target, behavior: behavior() });
    }

    if (prev) { prev.addEventListener('click', function () { go(-1); }); }
    if (next) { next.addEventListener('click', function () { go(1); }); }

    if (dotsBox) {
      var label = slider.getAttribute('data-slide-label') || '{n} / {total}';
      each(slides, function (slide, i) {
        var dot = doc.createElement('button');
        dot.type = 'button';
        dot.className = 'slider__dot';
        dot.setAttribute('aria-label', label.replace('{n}', i + 1).replace('{total}', slides.length));
        dot.addEventListener('click', function () {
          track.scrollTo({ left: i * step(), behavior: behavior() });
        });
        dotsBox.appendChild(dot);
        dots.push(dot);
      });

      var frame = 0;
      var update = function () {
        frame = 0;
        var current = index();
        dots.forEach(function (dot, i) {
          if (i === current) { dot.setAttribute('aria-current', 'true'); } else { dot.removeAttribute('aria-current'); }
        });
      };
      track.addEventListener('scroll', function () {
        if (!frame) { frame = window.requestAnimationFrame(update); }
      }, { passive: true });
      update();
    }
  });

  /* 3. Map --------------------------------------------------------------- */

  each(doc.querySelectorAll('[data-map]'), function (map) {
    var button = map.querySelector('[data-map-load]');
    var src = map.getAttribute('data-src');

    if (!button || !src) { return; }

    button.addEventListener('click', function () {
      var frame = doc.createElement('iframe');
      frame.className = 'map__frame';
      frame.src = src;
      frame.title = map.getAttribute('data-title') || '';
      frame.loading = 'lazy';
      frame.referrerPolicy = 'no-referrer';
      map.textContent = '';
      map.appendChild(frame);
      frame.focus();
    });
  });

  /* 4. Language switch keeps the position -------------------------------- */

  // Both languages have the same sections in the same order, but texts of different
  // length. So the position is remembered as "section n, x % through it", not in pixels.
  var POSITION_KEY = 'zb-position';
  // Not the article cards: their number differs per language.
  var blocks = function () { return doc.querySelectorAll('main section, main .project'); };
  var headerOffset = function () { return header ? header.offsetHeight : 0; };

  // A language chosen here wins over the browser's language the next time the
  // homepage opens (cookie read by Functions\Router, name in I18n\Languages::COOKIE).
  doc.addEventListener('click', function (event) {
    var link = event.target.closest('.lang-switch a[hreflang]');
    if (!link) { return; }
    doc.cookie = 'zb_lang=' + encodeURIComponent(link.getAttribute('hreflang')) +
      '; path=/; max-age=31536000; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');
  });

  doc.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-keep-position]');
    if (!link) { return; }

    var line = headerOffset() + 1;
    var index = -1;
    var fraction = 0;
    each(blocks(), function (block, i) {
      var rect = block.getBoundingClientRect();
      // The innermost block that crosses the line below the header wins (later in document order).
      if (rect.top <= line && rect.bottom > line && rect.height > 0) {
        index = i;
        fraction = (line - rect.top) / rect.height;
      }
    });

    try {
      if (index < 0 || window.scrollY < 4) {
        window.sessionStorage.removeItem(POSITION_KEY);
      } else {
        window.sessionStorage.setItem(POSITION_KEY, JSON.stringify({
          path: new URL(link.href).pathname,
          index: index,
          fraction: fraction
        }));
      }
    } catch (error) { /* storage not available: the page opens at the top */ }
  });

  (function () {
    var saved = null;
    try {
      saved = JSON.parse(window.sessionStorage.getItem(POSITION_KEY) || 'null');
      window.sessionStorage.removeItem(POSITION_KEY);
    } catch (error) { saved = null; }

    if (!saved || saved.path !== window.location.pathname || window.location.hash) { return; }

    var moved = false;
    var apply = function () {
      var block = blocks()[saved.index];
      if (!block || moved) { return; }
      var rect = block.getBoundingClientRect();
      var top = window.scrollY + rect.top + saved.fraction * rect.height - headerOffset() - 1;
      doc.documentElement.style.scrollBehavior = 'auto';
      window.scrollTo(0, Math.max(0, Math.round(top)));
      doc.documentElement.style.scrollBehavior = '';
    };

    if ('scrollRestoration' in window.history) { window.history.scrollRestoration = 'manual'; }
    apply();
    // Images and fonts still change heights: correct once more, unless the visitor already scrolled.
    window.setTimeout(function () {
      var mark = function () { moved = true; };
      window.addEventListener('wheel', mark, { once: true, passive: true });
      window.addEventListener('touchmove', mark, { once: true, passive: true });
      window.addEventListener('keydown', mark, { once: true });
    }, 0);
    window.addEventListener('load', apply, { once: true });
  })();

  /* 5. Chat widget ------------------------------------------------------- */

  var chat = window.zbChat;

  if (chat && chat.url && chat.token) {
    var loadChat = function () {
      window.chatwootSettings = { position: chat.position || 'left', locale: chat.locale || 'en', type: 'standard' };
      var script = doc.createElement('script');
      script.src = chat.url + '/packs/js/sdk.js';
      script.async = true;
      script.onload = function () {
        if (window.chatwootSDK) {
          window.chatwootSDK.run({ websiteToken: chat.token, baseUrl: chat.url });
        }
      };
      doc.head.appendChild(script);
    };
    var whenIdle = function () {
      if ('requestIdleCallback' in window) { window.requestIdleCallback(loadChat, { timeout: 4000 }); } else { window.setTimeout(loadChat, 1500); }
    };
    if (doc.readyState === 'complete') { whenIdle(); } else { window.addEventListener('load', whenIdle, { once: true }); }

    // Note beside the chat button, shown once the visitor reaches the team section
    // and the chat button is there. Opening the chat or the cross dismisses it for
    // the rest of the session.
    var hint = doc.querySelector('[data-chat-hint]');
    var hintTrigger = doc.querySelector('[data-chat-hint-trigger]');
    var hintKey = 'zb-chat-hint';
    var hintDone = false;
    try { hintDone = window.sessionStorage.getItem(hintKey) === '1'; } catch (e) { /* storage blocked */ }

    if (hint && hintTrigger && !hintDone && 'IntersectionObserver' in window) {
      var chatReady = false;
      var hintReached = false;

      var showHint = function () {
        if (chatReady && hintReached && !hintDone) { hint.hidden = false; }
      };
      var dismissHint = function () {
        hintDone = true;
        hint.hidden = true;
        try { window.sessionStorage.setItem(hintKey, '1'); } catch (e) { /* storage blocked */ }
      };

      var hintObserver = new IntersectionObserver(function (entries) {
        if (!entries.some(function (entry) { return entry.isIntersecting; })) { return; }
        hintObserver.disconnect();
        hintReached = true;
        showHint();
      }, { rootMargin: '0px 0px -35% 0px' });
      hintObserver.observe(hintTrigger);

      window.addEventListener('chatwoot:ready', function () { chatReady = true; showHint(); });
      window.addEventListener('chatwoot:opened', dismissHint);
      // The chat button of the widget itself (older Chatwoot versions send no "opened" event).
      doc.addEventListener('click', function (event) {
        if (event.target.closest && event.target.closest('.woot-widget-bubble')) { dismissHint(); }
      });
      hint.querySelector('[data-chat-hint-close]').addEventListener('click', dismissHint);
      hint.querySelector('[data-chat-hint-open]').addEventListener('click', function () {
        dismissHint();
        if (window.$chatwoot) { window.$chatwoot.toggle('open'); }
      });
    }
  }

  /* 6. Services on the homepage ------------------------------------------ */

  var disciplines = doc.querySelector('[data-disciplines]');

  if (disciplines) {
    var frames = disciplines.querySelectorAll('[data-discipline-frame]');
    var rows = disciplines.querySelectorAll('[data-discipline]');
    var activeFrame = 0;

    // The active service is the last one whose top has passed the middle of the screen.
    var updateFrame = function () {
      var line = window.innerHeight * 0.55;
      var index = 0;
      each(rows, function (row, i) {
        if (row.getBoundingClientRect().top <= line) { index = i; }
      });
      if (index === activeFrame) { return; }
      activeFrame = index;
      each(frames, function (frame, i) { frame.classList.toggle('is-active', i === index); });
    };

    // Four rectangle reads per scroll event: cheap enough without throttling.
    window.addEventListener('scroll', updateFrame, { passive: true });
    window.addEventListener('resize', updateFrame);
    updateFrame();
  }

  /* 7. Navigation: section in view ---------------------------------------- */

  // Only links that point to a section of this page (the homepage); elsewhere the
  // server marks the current page.
  var spies = [];
  each(doc.querySelectorAll('.primary-nav__link'), function (link) {
    if (!link.hash || link.pathname !== window.location.pathname) { return; }
    var section = doc.getElementById(decodeURIComponent(link.hash.slice(1)));
    if (section) { spies.push({ link: link, section: section }); }
  });

  if (spies.length) {
    var activeSpy = null;

    var updateSpy = function () {
      var top = headerOffset();
      // A line in the upper part of the visible area decides which section is "in view".
      var line = top + (window.innerHeight - top) * 0.3;
      var atEnd = window.innerHeight + window.scrollY >= doc.documentElement.scrollHeight - 2;
      var current = null;

      // The last section whose top has passed the line stays active, also through the
      // sections without a link of their own (services, team): the bold item never gets lost.
      spies.forEach(function (spy) {
        if (spy.section.getBoundingClientRect().top <= line) { current = spy; }
      });
      // At the end of the page a short last section never reaches the line.
      if (atEnd) {
        spies.forEach(function (spy) {
          var rect = spy.section.getBoundingClientRect();
          if (rect.top < window.innerHeight && rect.bottom > top) { current = spy; }
        });
      }

      if (current === activeSpy) { return; }
      activeSpy = current;
      spies.forEach(function (spy) {
        var on = spy === current;
        spy.link.classList.toggle('is-active', on);
        if (on) { spy.link.setAttribute('aria-current', 'location'); } else { spy.link.removeAttribute('aria-current'); }
      });
    };

    var spyFrame = 0;
    var requestSpy = function () {
      if (!spyFrame) {
        spyFrame = window.requestAnimationFrame(function () { spyFrame = 0; updateSpy(); });
      }
    };
    window.addEventListener('scroll', requestSpy, { passive: true });
    window.addEventListener('resize', requestSpy);
    window.addEventListener('load', updateSpy, { once: true });
    updateSpy();
  }
})();
