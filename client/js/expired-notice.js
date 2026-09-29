// Expired login page notice (restruct/silverstripe-login-branding).
//
// A Security page (login, lost password, ...) that stays open longer than the session lives
// carries a SecurityID the session no longer knows. Submitting it then fails with "Your session
// has expired. Please re-submit the form." and the credentials have to be typed again. This
// script asks the server whether the page's token is still valid and, once it is not, shows a
// notice above the form asking the user to refresh first.
//
// A countdown would not work: Session.timeout defaults to 0, and the session then ends whenever
// PHP's session garbage collection removes it, which the page cannot know. So the server is
// asked instead - when the tab becomes visible, regains focus or is restored from the back/forward
// cache (the moment a stale page is about to be used), and optionally periodically while it is
// visible. The periodic check is off unless configured: each check resumes the session and so
// keeps it alive, see SecurityBrandingExtension::$expired_notice_interval.
//
// Plain JS (ES5 syntax plus fetch and URLSearchParams) without a build step: the login-forms
// theme runs no JS framework that owns these forms, and a module without a build pipeline should
// not grow one for a few dozen lines.
(function () {
  'use strict';

  // Settings come from a <meta> tag the server adds (SecurityBrandingExtension::onAfterInit()),
  // so endpoint, token name, interval and the translated texts are not hardcoded here.
  var meta = document.querySelector('meta[name="login-branding-expired-notice"]');
  if (!meta || !window.fetch) {
    return;
  }

  var endpoint = meta.getAttribute('data-endpoint');
  var tokenName = meta.getAttribute('data-token-name') || 'SecurityID';
  var intervalSeconds = parseInt(meta.getAttribute('data-interval'), 10) || 0;
  var message = meta.getAttribute('data-message') || '';
  var linkText = meta.getAttribute('data-link-text') || '';

  // Focus and visibilitychange usually fire together; one request is enough.
  var MIN_GAP_MS = 2000;

  var shown = false;
  var inFlight = false;
  var lastCheck = 0;
  var timer = null;

  // The first security-token input inside a form on the page, or null. Looked up on every check
  // rather than once, so a form added after load is found too - and a page without one (the MFA
  // steps, "password sent") simply never asks the server anything.
  function findTokenInput() {
    var inputs = document.querySelectorAll('form input[name="' + tokenName + '"]');
    for (var i = 0; i < inputs.length; i++) {
      if (inputs[i].value && inputs[i].form) {
        return inputs[i];
      }
    }
    return null;
  }

  function check() {
    if (shown || inFlight || document.visibilityState === 'hidden') {
      return;
    }
    var now = Date.now();
    if (now - lastCheck < MIN_GAP_MS) {
      return;
    }

    var input = findTokenInput();
    if (!input || !endpoint) {
      return;
    }
    var form = input.form;

    lastCheck = now;
    inFlight = true;

    var body = new URLSearchParams();
    body.append(tokenName, input.value);

    // POST, so the token never ends up in a URL, and no cache replays an old answer.
    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (data) {
        // Only an explicit "not valid" shows the notice. Anything else (an error page, a proxy
        // response, a disabled endpoint) is not evidence that the page expired.
        if (data && data.valid === false) {
          showNotice(form);
        }
      })
      .catch(function () {
        // Fail quietly: offline or a network hiccup says nothing about the token, and the next
        // focus or interval tick will ask again.
      })
      .then(function () {
        inFlight = false;
      });
  }

  function showNotice(form) {
    if (shown) {
      return;
    }
    shown = true;
    stop();

    var notice = document.createElement('div');
    notice.className = 'login-branding-expired-notice';
    // role="alert" makes screen readers announce it when it appears, since it shows up without
    // any action by the user.
    notice.setAttribute('role', 'alert');

    // Bootstrap Icons "exclamation-triangle", the same icon family as the module's default logo.
    notice.innerHTML = '<svg class="login-branding-expired-notice__icon" xmlns="http://www.w3.org/2000/svg"'
      + ' width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">'
      + '<path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857'
      + ' 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2'
      + ' 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0'
      + ' 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/>'
      + '<path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552'
      + ' 0 0 1-1.1 0z"/></svg>';

    // Texts go in as text nodes, never as HTML: they are translations, not markup.
    var text = document.createElement('p');
    text.className = 'login-branding-expired-notice__text';
    text.appendChild(document.createTextNode(message + ' '));

    var link = document.createElement('a');
    // A real href, so the link also works as a link (middle click, keyboard, no-JS copy);
    // the click handler reloads instead, because assigning the same URL does not reload a page
    // whose URL has a #fragment.
    link.href = window.location.href;
    link.className = 'login-branding-expired-notice__link';
    link.appendChild(document.createTextNode(linkText));
    link.addEventListener('click', function (event) {
      event.preventDefault();
      window.location.reload();
    });
    text.appendChild(link);
    notice.appendChild(text);

    form.parentNode.insertBefore(notice, form);
  }

  function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
      check();
    }
  }

  function onPageShow(event) {
    // A page restored from the back/forward cache is exactly the "old page" case.
    if (event.persisted) {
      check();
    }
  }

  function stop() {
    if (timer) {
      window.clearInterval(timer);
      timer = null;
    }
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('focus', check);
    window.removeEventListener('pageshow', onPageShow);
  }

  document.addEventListener('visibilitychange', onVisibilityChange);
  window.addEventListener('focus', check);
  window.addEventListener('pageshow', onPageShow);
  if (intervalSeconds > 0) {
    // check() itself skips hidden tabs, so a background tab costs no requests.
    timer = window.setInterval(check, intervalSeconds * 1000);
  }
})();
