'use strict';

const http = require('http');
const { URL } = require('url');

const PORT = Number(process.env.PORT || 8080);
const MAX_STORED = Number(process.env.MAX_STORED || 1000);
const DEFAULT_LIMIT = Number(process.env.DEFAULT_LIMIT || 50);

/** @type {Array<object>} */
const requests = [];

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    req.on('error', reject);
  });
}

function captureRequest(req, body) {
  const entry = {
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
    timestamp: new Date().toISOString(),
    method: req.method,
    path: req.url,
    headers: req.headers,
    body,
  };

  requests.unshift(entry);
  if (requests.length > MAX_STORED) {
    requests.length = MAX_STORED;
  }

  return entry;
}

function sendJson(res, statusCode, payload) {
  const body = JSON.stringify(payload, null, 2);
  res.writeHead(statusCode, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url || '/', `http://${req.headers.host || 'localhost'}`);
  const pathname = url.pathname;

  if (req.method === 'GET' && pathname === '/_requests') {
    const limit = Math.max(1, Math.min(MAX_STORED, Number(url.searchParams.get('limit') || DEFAULT_LIMIT)));
    sendJson(res, 200, {
      count: Math.min(limit, requests.length),
      total: requests.length,
      requests: requests.slice(0, limit),
    });
    return;
  }

  if (req.method === 'DELETE' && pathname === '/_requests') {
    const cleared = requests.length;
    requests.length = 0;
    sendJson(res, 200, { cleared });
    return;
  }

  // Slow endpoint for delivery timeout tests: /_delay?ms=2500
  if (pathname === '/_delay') {
    const delayMs = Math.max(0, Math.min(30_000, Number(url.searchParams.get('ms') || 1000)));
    await new Promise((resolve) => setTimeout(resolve, delayMs));
    const body = await readBody(req);
    const entry = captureRequest(req, body);
    sendJson(res, 200, { ok: true, delayedMs: delayMs, capturedId: entry.id });
    return;
  }

  const body = await readBody(req);
  const entry = captureRequest(req, body);

  const mockStatus = Number(req.headers['x-mock-status']);
  if (Number.isInteger(mockStatus) && mockStatus >= 500 && mockStatus <= 599) {
    sendJson(res, mockStatus, {
      error: 'mock_server_error',
      mockStatus,
      capturedId: entry.id,
    });
    return;
  }

  sendJson(res, 200, {
    ok: true,
    capturedId: entry.id,
    echo: {
      method: req.method,
      path: req.url,
      headers: req.headers,
      body,
    },
  });
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`HTTP receiver listening on http://0.0.0.0:${PORT}`);
});
