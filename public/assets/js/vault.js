(function () {
  'use strict';

  function toast(message, tone) {
    var container = document.getElementById('toast-container');
    if (!container) {
      return;
    }

    var item = document.createElement('div');
    item.className = 'toast na-toast show text-bg-' + (tone || 'primary');
    item.setAttribute('role', 'status');
    item.innerHTML = '<div class="toast-body"></div>';
    item.querySelector('.toast-body').textContent = message;
    container.appendChild(item);
    window.setTimeout(function () {
      item.remove();
    }, 4000);
  }

  function reveal(entryId, csrf) {
    var body = new URLSearchParams();
    body.set('entry_id', entryId);
    body.set('_csrf', csrf);

    return fetch('/vault/reveal', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json'
      },
      body: body.toString()
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('reveal denied');
      }
      return response.json();
    }).then(function (payload) {
      if (!payload.ok) {
        throw new Error('reveal denied');
      }
      return payload.password;
    });
  }

  document.addEventListener('click', function (event) {
    var revealButton = event.target.closest('.na-reveal');
    var copyButton = event.target.closest('.na-copy');
    var copyTarget = event.target.getAttribute('data-copy-target');

    if (revealButton) {
      reveal(revealButton.dataset.entryId, revealButton.dataset.csrf)
        .then(function (password) {
          var field = document.getElementById('secret-value');
          field.value = password;
          field.type = 'text';
          var modal = new bootstrap.Modal(document.getElementById('secret-modal'));
          modal.show();
          document.getElementById('secret-modal').addEventListener('hidden.bs.modal', function () {
            field.value = '';
            field.type = 'password';
          }, { once: true });
        })
        .catch(function () {
          toast('Secret reveal was denied.', 'danger');
        });
    }

    if (copyButton) {
      reveal(copyButton.dataset.entryId, copyButton.dataset.csrf)
        .then(function (password) {
          return navigator.clipboard.writeText(password);
        })
        .then(function () {
          toast('Password copied. Clear your clipboard when finished.', 'success');
        })
        .catch(function () {
          toast('Copy failed or was denied.', 'danger');
        });
    }

    if (copyTarget) {
      var target = document.getElementById(copyTarget);
      if (target) {
        navigator.clipboard.writeText(target.value).then(function () {
          toast('Copied to clipboard.', 'success');
        });
      }
    }
  });

  document.addEventListener('input', function (event) {
    var outputId = event.target.getAttribute('data-range-output');
    if (!outputId) {
      return;
    }
    var output = document.getElementById(outputId);
    if (output) {
      output.value = event.target.value;
      output.textContent = event.target.value;
    }
  });
}());
