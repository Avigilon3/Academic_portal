(function () {
  'use strict';

  var canvas = document.querySelector('[data-certificate-qr]');
  if (!canvas) {
    return;
  }

  var status = document.querySelector('[data-qr-status]');
  var qr = window.PtcQrCode;
  if (!qr || typeof qr.toCanvas !== 'function') {
    if (status) {
      status.textContent = 'Use the verification code below';
    }
    return;
  }

  qr.toCanvas(canvas, canvas.getAttribute('data-verify-url'), {
    width: 168,
    margin: 1,
    errorCorrectionLevel: 'M'
  }, function (error) {
    if (error) {
      console.error('Certificate QR code could not be generated.', error);
      if (status) {
        status.textContent = 'Use the verification code below';
      }
    }
  });
}());
