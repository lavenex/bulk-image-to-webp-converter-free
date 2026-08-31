(function () {
  'use strict';

  const diagnostics = document.getElementById('biwebp-diagnostics');
  const webpStatus = document.getElementById('biwebp-browser-webp');
  const storageStatus = document.getElementById('biwebp-browser-storage');
  const downloadButton = document.getElementById('biwebp-download-diagnostics');
  if (!diagnostics || !webpStatus || !storageStatus) { return; }

  let webpSupported = false;
  try {
    const canvas = document.createElement('canvas');
    canvas.width = 1;
    canvas.height = 1;
    webpSupported = canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
  } catch (error) { webpSupported = false; }

  const storageSupported = 'indexedDB' in window;
  webpStatus.textContent = webpSupported ? 'Ready' : 'Not available in this browser';
  webpStatus.classList.toggle('is-ready', webpSupported);
  webpStatus.classList.toggle('is-warning', !webpSupported);
  storageStatus.textContent = storageSupported ? 'Available' : 'Unavailable — keep the converter tab open';
  storageStatus.classList.toggle('is-ready', storageSupported);
  storageStatus.classList.toggle('is-warning', !storageSupported);

  if (downloadButton) {
    downloadButton.addEventListener('click', function () {
      const lines = [
        'Bulk Image to WebP Converter support report',
        'Plugin version: ' + diagnostics.dataset.pluginVersion,
        'Generated: ' + new Date().toISOString(),
        'Browser: ' + navigator.userAgent
      ];
      diagnostics.querySelectorAll('[data-diagnostic]').forEach(function (item) {
        lines.push(item.dataset.diagnostic + ': ' + item.textContent.trim());
      });
      lines.push('Privacy: no image contents, filenames, license keys, or customer personal data are included.');
      const url = URL.createObjectURL(new Blob([lines.join('\n') + '\n'], { type: 'text/plain' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = 'webp-converter-support-report.txt';
      link.click();
      window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });
  }
}());
