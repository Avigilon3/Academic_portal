(function () {
  'use strict';

  var button = document.querySelector('[data-download-certificate]');
  var paper = document.querySelector('.certificate-paper');
  var status = document.querySelector('[data-certificate-download-status]');
  if (!button || !paper) {
    return;
  }

  button.addEventListener('click', function () {
    var render = window.PtcHtml2Canvas;
    if (typeof render !== 'function') {
      showError('Image download is unavailable. Use Print and save as PDF instead.');
      return;
    }

    button.disabled = true;
    button.textContent = 'Preparing image...';
    if (status) {
      status.hidden = true;
    }

    Promise.resolve().then(function () {
      return render(paper, {
        scale: 3,
        backgroundColor: '#fffef9',
        useCORS: false,
        logging: false
      });
    }).then(function (canvas) {
      return new Promise(function (resolve, reject) {
        canvas.toBlob(function (blob) {
          if (blob) {
            resolve(blob);
          } else {
            reject(new Error('The certificate image could not be created.'));
          }
        }, 'image/png');
      });
    }).then(function (blob) {
      var url = URL.createObjectURL(blob);
      var link = document.createElement('a');
      link.href = url;
      link.download = 'ptc-e-certificate.png';
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(function () {
        URL.revokeObjectURL(url);
      }, 1000);
    }).catch(function (error) {
      console.error('Certificate image download failed.', error);
      showError('Image download failed. Use Print and save as PDF instead.');
    }).then(function () {
      button.disabled = false;
      button.textContent = 'Download PNG';
    });
  });

  function showError(message) {
    if (status) {
      status.textContent = message;
      status.hidden = false;
    }
  }
}());
