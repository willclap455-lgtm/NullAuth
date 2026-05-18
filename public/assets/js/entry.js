(function () {
  'use strict';

  var script = document.currentScript;
  var sequence = [];
  try {
    sequence = JSON.parse(script.getAttribute('data-k') || '[]');
  } catch (error) {
    sequence = [];
  }

  var cursor = 0;
  var last = 0;
  var loaded = false;

  function reset() {
    cursor = 0;
    last = 0;
  }

  function unlock() {
    if (loaded) {
      return;
    }
    loaded = true;

    fetch('/unlock', {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('unlock failed');
        }
        return response.text();
      })
      .then(function (html) {
        document.body.innerHTML = html;
        var first = document.getElementById('identifier');
        if (first) {
          first.focus();
        }
      })
      .catch(function () {
        loaded = false;
        reset();
      });
  }

  window.addEventListener('keydown', function (event) {
    if (sequence.length === 0 || loaded) {
      return;
    }

    var now = Date.now();
    if (last && now - last > 1800) {
      reset();
    }
    last = now;

    if (event.key === sequence[cursor]) {
      cursor += 1;
      if (cursor === sequence.length) {
        unlock();
      }
      return;
    }

    reset();
  });
}());
