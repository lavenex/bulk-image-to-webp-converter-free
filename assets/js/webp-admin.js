(function () {
  'use strict';

  const fileInput = document.getElementById('biwebp-files');
  const convertButton = document.getElementById('biwebp-convert');
  const qualityInput = document.getElementById('biwebp-quality');
  const qualityValue = document.getElementById('biwebp-quality-value');
  const message = document.getElementById('biwebp-message');
  const results = document.getElementById('biwebp-results');
  const usageCount = document.getElementById('biwebp-usage-count');
  const remainingText = document.getElementById('biwebp-remaining');
  const mediaButton = document.getElementById('biwebp-media-library');
  const mediaSelection = document.getElementById('biwebp-media-selection');
  const pauseButton = document.getElementById('biwebp-pause');
  const resumeButton = document.getElementById('biwebp-resume');
  const retryButton = document.getElementById('biwebp-retry');
  const cancelButton = document.getElementById('biwebp-cancel');
  const clearButton = document.getElementById('biwebp-clear');
  const queueSummary = document.getElementById('biwebp-queue-summary');
  const impactSummary = document.getElementById('biwebp-impact-summary');
  const impactTime = document.getElementById('biwebp-impact-time');
  const impactMeter = document.getElementById('biwebp-impact-meter');
  const impactBar = document.getElementById('biwebp-impact-bar');
  const compressionProgressText = document.getElementById('biwebp-compression-progress-text');
  const compressionProgressMeter = document.getElementById('biwebp-compression-progress-meter');
  const compressionProgressBar = document.getElementById('biwebp-compression-progress-bar');
  const priceCountry = document.getElementById('biwebp-price-country');
  const monthlyPrice = document.getElementById('biwebp-monthly-price');
  const yearlyPrice = document.getElementById('biwebp-yearly-price');
  const storageNotice = document.getElementById('biwebp-storage-notice');
  const scanMediaButton = document.getElementById('biwebp-scan-media');
  const convertAllMediaButton = document.getElementById('biwebp-convert-all-media');
  const mediaScanSummary = document.getElementById('biwebp-media-scan-summary');
  const mediaSuggestions = document.getElementById('biwebp-media-suggestions');
  const suggestionList = document.getElementById('biwebp-media-suggestion-list');
  const selectAllSuggestions = document.getElementById('biwebp-select-all-suggestions');
  const queueSuggestionsButton = document.getElementById('biwebp-queue-suggestions');

  if (!fileInput || !convertButton || !qualityInput) {
    return;
  }

  let busy = false;
  let queuePaused = true;
  let queueJobs = [];
  let mediaQueue = [];
  let databasePromise;
  let queueStorageAvailable = 'indexedDB' in window;
  let previewUrls = [];
  let suggestedMedia = [];
  let mediaScanBusy = false;

  function showStorageWarning() {
    queueStorageAvailable = false;
    if (!storageNotice) { return; }
    storageNotice.hidden = false;
    storageNotice.textContent = 'Refresh recovery is unavailable in this browser session. Conversion will continue safely while this tab stays open.';
  }

  function localizeDisplayPricing() {
    if (!priceCountry || !monthlyPrice || !yearlyPrice || !biwebpConfig.pricingCatalog) { return; }
    const timezoneCountries = {
      'Asia/Kolkata': 'IN', 'Asia/Calcutta': 'IN', 'Europe/London': 'GB',
      'Australia/Sydney': 'AU', 'America/Toronto': 'CA', 'Asia/Dubai': 'AE', 'Asia/Singapore': 'SG'
    };
    let timezone = '';
    try { timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (error) { timezone = ''; }
    let country = timezoneCountries[timezone] || '';
    if (!country) {
      const locale = navigator.language || '';
      const match = locale.match(/[-_]([A-Z]{2})$/i);
      country = match ? match[1].toUpperCase() : 'IN';
    }
    const euCountries = ['AT', 'BE', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'SI', 'SK'];
    if (euCountries.includes(country)) { country = 'EU'; }
    const pricing = biwebpConfig.pricingCatalog[country] || biwebpConfig.pricingCatalog.IN || biwebpConfig.pricingCatalog.US;
    if (!pricing) { return; }
    priceCountry.textContent = pricing.country_label;
    monthlyPrice.textContent = pricing.monthly;
    yearlyPrice.textContent = pricing.yearly;
  }

  localizeDisplayPricing();

  function setMessage(text, isError) {
    message.textContent = text;
    message.classList.toggle('is-error', Boolean(isError));
  }

  function updateUsage(nextRemaining) {
    // Retained for response compatibility with pre-0.8 queue records.
    void nextRemaining;
  }

  function newJobId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    return 'job_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 18);
  }

  function availableSelectionCount() {
    const reservedCount = queueJobs.filter(function (job) {
      return ['pending', 'processing'].includes(job.status);
    }).length;
    return Math.max(0, Number(biwebpConfig.batchLimit) - reservedCount);
  }

  function jobFilename(job) {
    return job.file ? job.file.name : (job.sourceFilename || 'Media Library image');
  }

  function jobOriginalBytes(job) {
    return Number(job.sourceBytes || (job.file ? job.file.size : 0));
  }

  function jobOutputBytes(job) {
    return Number(job.outputBytes || (job.outputBlob ? job.outputBlob.size : 0) || (job.result && job.result.size ? job.result.size : 0));
  }

  function openQueueDatabase() {
    if (!queueStorageAvailable) {
      return Promise.reject(new Error('Persistent browser storage is unavailable.'));
    }
    if (databasePromise) {
      return databasePromise;
    }
    databasePromise = new Promise(function (resolve, reject) {
      const request = window.indexedDB.open('biwebp_queue', 1);
      request.onupgradeneeded = function () {
        if (!request.result.objectStoreNames.contains('jobs')) {
          request.result.createObjectStore('jobs', { keyPath: 'id' });
        }
      };
      request.onsuccess = function () { resolve(request.result); };
      request.onerror = function () { reject(request.error || new Error('Could not open queue storage.')); };
    }).catch(function (error) {
      showStorageWarning();
      throw error;
    });
    return databasePromise;
  }

  async function queueRequest(mode, action) {
    if (!queueStorageAvailable) {
      return undefined;
    }
    const database = await openQueueDatabase();
    return new Promise(function (resolve, reject) {
      const transaction = database.transaction('jobs', mode);
      const request = action(transaction.objectStore('jobs'));
      request.onsuccess = function () { resolve(request.result); };
      request.onerror = function () { reject(request.error || new Error('Queue storage failed.')); };
    });
  }

  function saveJob(job) {
    return queueRequest('readwrite', function (store) { return store.put(job); });
  }

  function deleteJob(jobId) {
    return queueRequest('readwrite', function (store) { return store.delete(jobId); });
  }

  async function loadJobs() {
    const stored = await queueRequest('readonly', function (store) { return store.getAll(); });
    return Array.isArray(stored) ? stored : [];
  }

  function allowedType(file) {
    const extension = file.name.split('.').pop().toLowerCase();
    return ['png', 'jpg', 'jpeg'].includes(extension) && ['image/png', 'image/jpeg'].includes(file.type);
  }

  async function inspectRasterHeader(file) {
    const bytes = new Uint8Array(await file.arrayBuffer());
    const extension = file.name.split('.').pop().toLowerCase();
    if (extension === 'png') {
      const pngSignature = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
      if (bytes.length < 24 || !pngSignature.every(function (value, index) { return bytes[index] === value; })) {
        throw new Error('The file content does not match a valid PNG image.');
      }
      const pngView = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
      return { width: pngView.getUint32(16, false), height: pngView.getUint32(20, false) };
    }
    if (bytes.length < 4 || bytes[0] !== 0xff || bytes[1] !== 0xd8 || bytes[2] !== 0xff) {
      throw new Error('The file content does not match a valid JPEG image.');
    }
    const sofMarkers = [0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf];
    let offset = 2;
    while (offset + 3 < bytes.length) {
      while (offset < bytes.length && bytes[offset] !== 0xff) { offset += 1; }
      while (offset < bytes.length && bytes[offset] === 0xff) { offset += 1; }
      if (offset >= bytes.length) { break; }
      const marker = bytes[offset];
      offset += 1;
      if (marker === 0xd9 || marker === 0xda) { break; }
      if (marker === 0x01 || (marker >= 0xd0 && marker <= 0xd8)) { continue; }
      if (offset + 1 >= bytes.length) { break; }
      const segmentLength = (bytes[offset] << 8) | bytes[offset + 1];
      if (segmentLength < 2 || offset + segmentLength > bytes.length) {
        throw new Error('The JPEG structure is malformed.');
      }
      if (sofMarkers.includes(marker)) {
        if (segmentLength < 7) { throw new Error('The JPEG dimensions are malformed.'); }
        return { width: (bytes[offset + 5] << 8) | bytes[offset + 6], height: (bytes[offset + 3] << 8) | bytes[offset + 4] };
      }
      offset += segmentLength;
    }
    throw new Error('The JPEG dimensions could not be read safely.');
  }

  function loadImage(file) {
    return new Promise(function (resolve, reject) {
      const url = URL.createObjectURL(file);
      const image = new Image();
      image.onload = function () { URL.revokeObjectURL(url); resolve(image); };
      image.onerror = function () { URL.revokeObjectURL(url); reject(new Error('This image could not be decoded.')); };
      image.src = url;
    });
  }

  function encodeWebP(image, quality) {
    return new Promise(function (resolve, reject) {
      const canvas = document.createElement('canvas');
      canvas.width = image.naturalWidth;
      canvas.height = image.naturalHeight;
      const context = canvas.getContext('2d');
      context.imageSmoothingEnabled = true;
      context.imageSmoothingQuality = 'high';
      context.drawImage(image, 0, 0);
      canvas.toBlob(function (blob) {
        if (!blob || blob.type !== 'image/webp') {
          reject(new Error('This browser cannot create WebP files. Use a current version of Chrome, Edge, Firefox, or Safari.'));
          return;
        }
        resolve(blob);
      }, 'image/webp', quality / 100);
    });
  }

  function humanSize(bytes) {
    if (bytes < 1024) { return bytes + ' B'; }
    if (bytes < 1048576) { return (bytes / 1024).toFixed(1) + ' KB'; }
    return (bytes / 1048576).toFixed(2) + ' MB';
  }

  function updateImpact() {
    const completed = queueJobs.filter(function (job) { return job.status === 'completed' && jobOutputBytes(job) > 0; });
    if (!completed.length) {
      impactTime.textContent = '0.00 sec';
      impactSummary.textContent = 'Complete a conversion to calculate savings.';
      impactMeter.setAttribute('aria-valuenow', '0');
      impactMeter.setAttribute('aria-valuetext', 'No measured reduction yet');
      impactBar.style.width = '0%';
      return;
    }
    const originalBytes = completed.reduce(function (total, job) { return total + jobOriginalBytes(job); }, 0);
    const webpBytes = completed.reduce(function (total, job) { return total + jobOutputBytes(job); }, 0);
    const savedBytes = originalBytes - webpBytes;
    const percent = originalBytes > 0 ? Math.round(savedBytes / originalBytes * 100) : 0;
    const seconds = Math.abs(savedBytes) * 8 / 10000000;
    const progress = savedBytes >= 0 ? Math.max(0, Math.min(100, percent)) : 0;
    impactTime.textContent = savedBytes >= 0 ? seconds.toFixed(2) + ' sec' : '0.00 sec';
    impactMeter.setAttribute('aria-valuenow', String(progress));
    impactMeter.setAttribute('aria-valuetext', savedBytes >= 0 ? progress + ' percent less image data' : 'No image data reduction');
    impactBar.style.width = progress + '%';
    impactSummary.textContent = savedBytes >= 0 ?
      percent + '% less image data · ' + humanSize(originalBytes) + ' → ' + humanSize(webpBytes) :
      humanSize(originalBytes) + ' → ' + humanSize(webpBytes) + ' · ' + Math.abs(percent) + '% more image data · consider lowering quality';
  }

  function releasePreviewUrls() {
    previewUrls.forEach(function (url) { URL.revokeObjectURL(url); });
    previewUrls = [];
  }

  function renderQueue() {
    releasePreviewUrls();
    results.replaceChildren();
    queueJobs.sort(function (a, b) {
      if (a.status === 'completed' && b.status === 'completed') {
        return (b.completedAt || b.createdAt) - (a.completedAt || a.createdAt);
      }
      if (a.status === 'completed') { return -1; }
      if (b.status === 'completed') { return 1; }
      return b.createdAt - a.createdAt;
    }).forEach(function (job) {
      const card = document.createElement('article');
      card.className = 'biwebp-result';
      card.setAttribute('role', 'listitem');
      card.dataset.jobId = job.id;
      const preview = document.createElement('img');
      const previewSource = job.status === 'completed' && job.outputBlob ? job.outputBlob : job.file;
      const previewUrl = previewSource ? URL.createObjectURL(previewSource) :
        (job.status === 'completed' && job.result && job.result.attachmentUrl ? job.result.attachmentUrl : job.sourceUrl);
      if (previewSource) { previewUrls.push(previewUrl); }
      preview.src = previewUrl;
      preview.alt = (job.status === 'completed' ? 'Converted WebP preview for ' : 'Original preview for ') + jobFilename(job);
      const details = document.createElement('div');
      const title = document.createElement('strong');
      title.textContent = jobFilename(job);
      const status = document.createElement('span');
      status.className = 'biwebp-status is-' + (job.status === 'completed' ? 'complete' : job.status === 'failed' ? 'failed' : 'processing');
      const labels = { pending: 'Pending', processing: biwebpConfig.strings.processing, completed: biwebpConfig.strings.complete, failed: biwebpConfig.strings.failed, cancelled: 'Cancelled' };
      status.textContent = labels[job.status] + (job.error ? ': ' + job.error : '');
      const action = document.createElement('div');
      if (job.status === 'completed' && job.result) {
        const originalBytes = jobOriginalBytes(job);
        const outputBytes = jobOutputBytes(job);
        const change = originalBytes > 0 ? Math.round((1 - outputBytes / originalBytes) * 100) : 0;
        const stats = document.createElement('small');
        stats.className = 'biwebp-stats';
        stats.textContent = humanSize(originalBytes) + ' → ' + humanSize(outputBytes) +
          (change >= 0 ? ' · ' + change + '% smaller' : ' · ' + Math.abs(change) + '% larger');
        const mediaLink = document.createElement('a');
        mediaLink.className = 'button';
        mediaLink.href = job.result.editUrl;
        mediaLink.textContent = 'View in Media Library';
        const download = document.createElement('a');
        download.className = 'button';
        download.href = previewUrl;
        download.download = job.result.filename;
        download.textContent = 'Download WebP';
        action.append(stats, mediaLink, download);
      }
      details.append(title, status, action);
      card.append(preview, details);
      results.append(card);
    });
    updateImpact();
    updateControls();
  }

  function updateControls() {
    const counts = { pending: 0, processing: 0, completed: 0, failed: 0, cancelled: 0 };
    queueJobs.forEach(function (job) { counts[job.status] = (counts[job.status] || 0) + 1; });
    queueSummary.textContent = queueJobs.length ?
      counts.pending + ' pending · ' + counts.processing + ' processing · ' + counts.completed + ' complete · ' + counts.failed + ' failed' : 'Queue empty';
    pauseButton.disabled = queuePaused || !busy;
    resumeButton.disabled = !queuePaused || counts.pending === 0;
    retryButton.disabled = counts.failed === 0;
    cancelButton.disabled = counts.pending === 0;
    clearButton.disabled = counts.completed + counts.cancelled === 0;
    convertButton.disabled = busy;
    if (scanMediaButton) { scanMediaButton.disabled = mediaScanBusy; }
    if (convertAllMediaButton) { convertAllMediaButton.disabled = busy || mediaScanBusy; }
    if (queueSuggestionsButton) { queueSuggestionsButton.disabled = busy || mediaScanBusy || suggestedMedia.length === 0; }
    const total = counts.pending + counts.processing + counts.completed + counts.failed;
    const processed = counts.completed + counts.failed;
    const progress = total > 0 ? Math.round(processed / total * 100) : 0;
    compressionProgressText.textContent = processed + ' of ' + total + ' processed · ' + progress + '%';
    compressionProgressMeter.setAttribute('aria-valuenow', String(progress));
    compressionProgressMeter.setAttribute('aria-valuetext', processed + ' of ' + total + ' images processed, ' + progress + ' percent');
    compressionProgressBar.style.width = progress + '%';
  }

  async function validateOnServer(blob, job) {
    const body = new FormData();
    body.append('action', 'biwebp_validate_conversion');
    body.append('nonce', biwebpConfig.nonce);
    body.append('jobKey', job.id);
    body.append('filename', jobFilename(job));
    body.append('sourceAttachmentId', String(job.sourceAttachmentId || 0));
    body.append('quality', String(job.quality));
    body.append('webp', blob, jobFilename(job).replace(/\.[^.]+$/, '') + '.webp');
    const response = await fetch(biwebpConfig.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      const error = new Error(payload.data && payload.data.message ? payload.data.message : 'WebP validation failed.');
      error.code = response.status === 409 ? 'job_in_progress' : 'validation_failed';
      throw error;
    }
    return payload.data;
  }

  async function hydrateJobFile(job) {
    if (job.file) { return job.file; }
    if (!job.sourceUrl) { throw new Error('The Media Library source is unavailable. Run the media scan again.'); }
    const response = await fetch(job.sourceUrl, { credentials: 'same-origin' });
    if (!response.ok) { throw new Error('Could not read ' + jobFilename(job) + ' from the Media Library.'); }
    const blob = await response.blob();
    job.file = new File([blob], job.sourceFilename, { type: job.sourceMime || blob.type });
    job.sourceBytes = job.file.size;
    await saveJob(job);
    return job.file;
  }

  async function convertOne(job) {
    job.status = 'processing';
    job.error = '';
    await saveJob(job);
    renderQueue();
    try {
      await hydrateJobFile(job);
      if (!allowedType(job.file)) { throw new Error('Only PNG and JPG/JPEG files are supported.'); }
      if (job.file.size > Number(biwebpConfig.maxBytes)) { throw new Error('This file is larger than the current ' + biwebpConfig.maxLabel + ' upload limit.'); }
      const header = await inspectRasterHeader(job.file);
      if (header.width < 1 || header.height < 1) { throw new Error('The image dimensions are invalid.'); }
      const image = await loadImage(job.file);
      if (image.naturalWidth < 1 || image.naturalHeight < 1) { throw new Error('The image dimensions are invalid.'); }
      const webpBlob = await encodeWebP(image, Number(job.quality));
      if (webpBlob.size > Number(biwebpConfig.maxBytes)) {
        throw new Error('The generated WebP is larger than the current ' + biwebpConfig.maxLabel + ' upload limit. Lower the quality setting.');
      }
      const validated = await validateOnServer(webpBlob, job);
      job.status = 'completed';
      job.outputBytes = webpBlob.size;
      job.outputBlob = null;
      job.result = validated;
      job.completedAt = Date.now();
      job.sourceFilename = job.file.name;
      job.sourceMime = job.file.type;
      job.sourceBytes = job.file.size;
      job.file = null;
      await saveJob(job);
      updateUsage(validated.remaining);
      renderQueue();
      return true;
    } catch (error) {
      if (error.code === 'job_in_progress') {
        job.status = 'pending';
        job.error = '';
        await saveJob(job);
        renderQueue();
        setMessage('This job is finishing in another tab. Checking again shortly…', false);
        await new Promise(function (resolve) { window.setTimeout(resolve, 1000); });
        return null;
      }
      job.status = 'failed';
      job.error = error.message;
      await saveJob(job);
      renderQueue();
      return false;
    }
  }

  async function processQueue() {
    if (busy) { return; }
    busy = true;
    updateControls();
    let completed = 0;
    while (!queuePaused) {
      const job = queueJobs.find(function (candidate) { return candidate.status === 'pending'; });
      if (!job) { break; }
      if (await convertOne(job)) { completed += 1; }
    }
    busy = false;
    updateControls();
    if (queuePaused && queueJobs.some(function (job) { return job.status === 'pending'; })) {
      setMessage('Queue paused. The current file, if any, finished safely.', false);
    } else {
      setMessage(completed + ' successful conversion(s). Failed files were not counted.', false);
    }
  }

  async function persistAll(jobs) {
    for (const job of jobs) { await saveJob(job); }
  }

  async function scanMediaPage(page) {
    const body = new URLSearchParams();
    body.set('action', 'biwebp_scan_media');
    body.set('nonce', biwebpConfig.nonce);
    body.set('page', String(page));
    const response = await fetch(biwebpConfig.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.data && payload.data.message ? payload.data.message : 'Could not scan the Media Library.');
    }
    return payload.data;
  }

  async function scanAllEligibleMedia() {
    const found = new Map();
    let page = 1;
    let hasMore = true;
    while (hasMore && page <= 400) {
      const data = await scanMediaPage(page);
      (data.items || []).forEach(function (item) { found.set(Number(item.id), item); });
      hasMore = Boolean(data.hasMore);
      if (mediaScanSummary) {
        mediaScanSummary.textContent = 'Scanning Media Library… ' + found.size + ' eligible image(s) found.';
      }
      page += 1;
    }
    if (hasMore) {
      throw new Error('The Media Library scan reached its safety boundary. Convert these images, then scan again.');
    }
    return Array.from(found.values()).sort(function (a, b) { return Number(b.bytes) - Number(a.bytes); });
  }

  function renderMediaSuggestions() {
    if (!mediaSuggestions || !suggestionList || !mediaScanSummary) { return; }
    suggestionList.replaceChildren();
    const displayed = suggestedMedia.slice(0, 100);
    displayed.forEach(function (item) {
      const row = document.createElement('li');
      const label = document.createElement('label');
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = true;
      checkbox.dataset.attachmentId = String(item.id);
      const copy = document.createElement('span');
      const name = document.createElement('strong');
      const reason = document.createElement('small');
      name.textContent = item.filename;
      reason.textContent = item.reason + ' · ' + humanSize(Number(item.bytes));
      copy.append(name, reason);
      label.append(checkbox, copy);
      row.append(label);
      suggestionList.append(row);
    });
    mediaSuggestions.hidden = suggestedMedia.length === 0;
    if (selectAllSuggestions) { selectAllSuggestions.checked = displayed.length > 0; }
    mediaScanSummary.textContent = suggestedMedia.length ?
      suggestedMedia.length + ' eligible image(s) found. Larger files are suggested first.' + (suggestedMedia.length > displayed.length ? ' Showing the first 100 for review; Convert all includes every eligible image.' : '') :
      'No eligible PNG/JPEG originals need conversion. Images already converted by this plugin were skipped.';
    updateControls();
  }

  function makeRemoteJob(item) {
    return {
      id: newJobId(),
      file: null,
      sourceAttachmentId: Number(item.id),
      sourceUrl: item.url,
      sourceFilename: item.filename,
      sourceMime: item.mime,
      sourceBytes: Number(item.bytes),
      quality: Number(qualityInput.value),
      status: 'pending',
      error: '',
      createdAt: Date.now() + Math.random()
    };
  }

  async function queueRemoteAttachments(attachments) {
    const alreadyQueued = new Set(queueJobs.map(function (job) { return Number(job.sourceAttachmentId || 0); }).filter(Boolean));
    const additions = attachments.filter(function (item) { return !alreadyQueued.has(Number(item.id)); }).map(makeRemoteJob);
    if (!additions.length) {
      setMessage('All selected Media Library images are already in this queue.', false);
      return;
    }
    queueJobs = queueJobs.concat(additions);
    try { await persistAll(additions); } catch (error) { showStorageWarning(); }
    queuePaused = false;
    renderQueue();
    setMessage(additions.length + ' Media Library image(s) queued. Processing locally, one image at a time.', false);
    processQueue();
  }

  async function runMediaScan(convertAll) {
    if (mediaScanBusy || busy) { return; }
    mediaScanBusy = true;
    updateControls();
    if (mediaScanSummary) { mediaScanSummary.textContent = 'Scanning Media Library…'; }
    try {
      suggestedMedia = await scanAllEligibleMedia();
      renderMediaSuggestions();
      if (convertAll && suggestedMedia.length) { await queueRemoteAttachments(suggestedMedia); }
    } catch (error) {
      if (mediaScanSummary) { mediaScanSummary.textContent = error.message; }
      setMessage(error.message, true);
    } finally {
      mediaScanBusy = false;
      updateControls();
    }
  }

  if (scanMediaButton) {
    scanMediaButton.addEventListener('click', function () { runMediaScan(false); });
  }
  if (convertAllMediaButton) {
    convertAllMediaButton.addEventListener('click', function () { runMediaScan(true); });
  }
  if (selectAllSuggestions && suggestionList) {
    selectAllSuggestions.addEventListener('change', function () {
      suggestionList.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) { checkbox.checked = selectAllSuggestions.checked; });
    });
  }
  if (queueSuggestionsButton && suggestionList) {
    queueSuggestionsButton.addEventListener('click', function () {
      const selectedIds = new Set(Array.from(suggestionList.querySelectorAll('input[type="checkbox"]:checked')).map(function (checkbox) { return Number(checkbox.dataset.attachmentId); }));
      queueRemoteAttachments(suggestedMedia.filter(function (item) { return selectedIds.has(Number(item.id)); }));
    });
  }

  qualityInput.addEventListener('input', function () {
    qualityValue.textContent = qualityInput.value + '%';
    qualityInput.setAttribute('aria-valuetext', qualityInput.value + ' percent WebP quality');
  });

  fileInput.addEventListener('change', function () {
    const selectedFiles = Array.from(fileInput.files || []);
    const allowedCount = availableSelectionCount();
    if (selectedFiles.length <= allowedCount) { return; }
    if (typeof window.DataTransfer === 'function') {
      const limitedSelection = new window.DataTransfer();
      selectedFiles.slice(0, allowedCount).forEach(function (file) { limitedSelection.items.add(file); });
      fileInput.files = limitedSelection.files;
    } else {
      fileInput.value = '';
    }
    setMessage('This safe batch can accept ' + allowedCount + ' more image(s). Finish or clear the current queue before adding another batch.', true);
  });

  if (mediaButton && window.wp && window.wp.media) {
    mediaButton.addEventListener('click', function () {
      const frame = window.wp.media({ title: 'Choose PNG or JPEG images', button: { text: 'Use selected images' }, library: { type: 'image' }, multiple: true });
      frame.on('select', async function () {
        const eligibleAttachments = frame.state().get('selection').toJSON().filter(function (attachment) {
          return ['image/png', 'image/jpeg'].includes(attachment.mime);
        });
        const allowedCount = availableSelectionCount();
        const attachments = eligibleAttachments.slice(0, allowedCount);
        mediaQueue = [];
        if (eligibleAttachments.length > allowedCount) {
          setMessage('This safe batch can accept ' + allowedCount + ' more image(s). Finish or clear the current queue before adding another batch.', true);
        } else {
          setMessage('Preparing Media Library images…', false);
        }
        for (const attachment of attachments) {
          try {
            const response = await fetch(attachment.url, { credentials: 'same-origin' });
            if (!response.ok) { throw new Error('Could not read ' + attachment.filename + ' from the Media Library.'); }
            const blob = await response.blob();
            const file = new File([blob], attachment.filename, { type: attachment.mime });
            mediaQueue.push({ file: file, sourceAttachmentId: Number(attachment.id) });
          } catch (error) { setMessage(error.message, true); }
        }
        mediaSelection.textContent = mediaQueue.length + ' Media Library image(s) selected';
        if (mediaQueue.length && eligibleAttachments.length <= allowedCount) { setMessage('Media Library images are ready to queue.', false); }
      });
      frame.open();
    });
  }

  convertButton.addEventListener('click', async function () {
    if (busy) { return; }
    const selected = Array.from(fileInput.files || []).map(function (file) { return { file: file, sourceAttachmentId: 0 }; }).concat(mediaQueue);
    if (!selected.length) { setMessage('Choose at least one image.', true); return; }
    const allowedCount = availableSelectionCount();
    if (allowedCount <= 0) { setMessage(biwebpConfig.strings.limit, true); return; }
    if (selected.length > allowedCount) {
      setMessage('Select no more than ' + allowedCount + ' image(s) for the current safe batch.', true);
      return;
    }
    const added = selected.map(function (item) {
      return { id: newJobId(), file: item.file, sourceAttachmentId: item.sourceAttachmentId, quality: Number(qualityInput.value), status: 'pending', error: '', createdAt: Date.now() + Math.random() };
    });
    queueJobs = queueJobs.concat(added);
    try {
      await persistAll(added);
    } catch (error) {
      showStorageWarning();
    }
    fileInput.value = '';
    mediaQueue = [];
    mediaSelection.textContent = '';
    queuePaused = false;
    renderQueue();
    setMessage(queueStorageAvailable ? 'Processing the refresh-safe queue locally.' : 'Processing locally in this tab. Keep this page open until the queue finishes.', false);
    processQueue();
  });

  pauseButton.addEventListener('click', function () {
    queuePaused = true;
    updateControls();
    setMessage('Pause requested. The current file will finish safely.', false);
  });
  resumeButton.addEventListener('click', function () {
    queuePaused = false;
    setMessage('Queue resumed.', false);
    processQueue();
  });
  retryButton.addEventListener('click', async function () {
    const failed = queueJobs.filter(function (job) { return job.status === 'failed'; });
    failed.forEach(function (job) { job.status = 'pending'; job.error = ''; });
    await persistAll(failed);
    queuePaused = false;
    renderQueue();
    processQueue();
  });
  cancelButton.addEventListener('click', async function () {
    const pending = queueJobs.filter(function (job) { return job.status === 'pending'; });
    pending.forEach(function (job) { job.status = 'cancelled'; });
    await persistAll(pending);
    renderQueue();
    setMessage(pending.length + ' pending job(s) cancelled. Completed files remain available.', false);
  });
  clearButton.addEventListener('click', async function () {
    const finished = queueJobs.filter(function (job) { return ['completed', 'cancelled'].includes(job.status); });
    for (const job of finished) { await deleteJob(job.id); }
    queueJobs = queueJobs.filter(function (job) { return !['completed', 'cancelled'].includes(job.status); });
    renderQueue();
    setMessage('Finished queue records cleared from this browser. Media Library files were not deleted.', false);
  });

  (async function restoreQueue() {
    try {
      queueJobs = await loadJobs();
      const interrupted = queueJobs.filter(function (job) { return job.status === 'processing'; });
      interrupted.forEach(function (job) { job.status = 'pending'; job.error = ''; });
      await persistAll(interrupted);
      renderQueue();
      if (queueJobs.some(function (job) { return job.status === 'pending'; })) {
        queuePaused = true;
        updateControls();
        setMessage('A saved queue was restored. Select Resume queue to continue.', false);
      }
    } catch (error) {
      showStorageWarning();
      renderQueue();
      setMessage('Conversion is still available in this tab.', false);
    }
  }());
}());
