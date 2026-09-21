#!/usr/bin/env node
/**
 * PROXY TRUNG GIAN CHO CLAUDE-GATEWAY (agentshop247)
 * ==================================================
 *
 * LÝ DO TỒN TẠI
 * -------------
 * Gateway upstream `https://api.agentshop247.com/api/claude`:
 *   - CHẤP NHẬN request KHÔNG có trường `model` (tự chọn model mặc định)
 *   - TỪ CHỐI mọi request CÓ trường `model`, kể cả id Claude chuẩn:
 *       HTTP 400 {"error":{"message":"Invalid model: <x>.
 *                 Supported models: , , , , , , "}}
 *     (danh sách model id gateway trả về RỖNG)
 *
 * DSH/pi-ai luôn gửi trường `model`, nên không thể nói chuyện trực tiếp.
 * Proxy này gỡ bỏ trường `model` trước khi chuyển tiếp lên gateway.
 *
 * API KEY
 * -------
 * Tự đọc từ file credentials của DSH (không cần biến môi trường):
 *   %APPDATA%\dsh-desktop\harness\.credentials.yaml
 *   mục `refs` -> CLAUDE_OPUS_4_8_API_KEY
 * Có thể ghi đè bằng biến môi trường GATEWAY_KEY.
 *
 * CÁCH DÙNG
 * ---------
 *   node scratch/claude-proxy.mjs
 *   (hoặc chạy scripts/start-claude-proxy.ps1 để tự khởi động nền)
 */

import http from 'node:http';
import https from 'node:https';
import { readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

const UPSTREAM = process.env.UPSTREAM || 'https://api.agentshop247.com/api/claude';
const PORT = Number(process.env.PORT || 8791);
const CRED_REF = process.env.CRED_REF || 'CLAUDE_OPUS_4_8_API_KEY';

/** Đọc key gateway từ file credentials của DSH. */
function readKeyFromCredentials() {
  const candidates = [
    join(process.env.APPDATA || join(homedir(), 'AppData', 'Roaming'), 'dsh-desktop', 'harness', '.credentials.yaml'),
    join(homedir(), '.dsh', '.credentials.yaml'),
  ];
  for (const file of candidates) {
    try {
      const text = readFileSync(file, 'utf8');
      // Dòng dạng: "  CLAUDE_OPUS_4_8_API_KEY: as_xxx"
      const re = new RegExp(`^\\s*${CRED_REF}\\s*:\\s*(\\S+)\\s*$`, 'm');
      const m = text.match(re);
      if (m && m[1]) return { key: m[1], source: file };
    } catch {
      /* thử file kế tiếp */
    }
  }
  return null;
}

let KEY = process.env.GATEWAY_KEY || '';
let keySource = KEY ? 'biến môi trường GATEWAY_KEY' : '';

if (!KEY) {
  const found = readKeyFromCredentials();
  if (found) {
    KEY = found.key;
    keySource = found.source;
  }
}

if (!KEY) {
  console.error(
    `Không tìm thấy API key.\n` +
      `  - Đã thử đọc "${CRED_REF}" từ file .credentials.yaml của DSH\n` +
      `  - Hoặc đặt biến môi trường GATEWAY_KEY`,
  );
  process.exit(1);
}

const upstreamUrl = new URL(UPSTREAM);
const basePath = upstreamUrl.pathname.replace(/\/+$/, '');

const server = http.createServer((req, res) => {
  const chunks = [];
  req.on('data', (c) => chunks.push(c));

  req.on('end', () => {
    let body = Buffer.concat(chunks);
    const headers = { ...req.headers };

    // Header của client không dùng để xác thực upstream.
    delete headers.host;
    delete headers['content-length'];
    delete headers['authorization'];
    headers['x-api-key'] = KEY;
    headers['anthropic-version'] = headers['anthropic-version'] || '2023-06-01';

    // ---- MẤU CHỐT: gỡ trường `model` khỏi body JSON ----
    const ctype = String(req.headers['content-type'] || '');
    if (body.length > 0 && ctype.includes('json')) {
      try {
        const parsed = JSON.parse(body.toString('utf8'));
        if (parsed && typeof parsed === 'object' && 'model' in parsed) {
          delete parsed.model;
        }
        body = Buffer.from(JSON.stringify(parsed), 'utf8');
      } catch {
        /* body không phải JSON hợp lệ — giữ nguyên */
      }
    }
    headers['content-length'] = String(body.length);

    const up = https.request(
      {
        hostname: upstreamUrl.hostname,
        port: 443,
        path: basePath + req.url,
        method: req.method,
        headers,
      },
      (upRes) => {
        res.writeHead(upRes.statusCode || 502, upRes.headers);
        upRes.pipe(res);
      },
    );

    up.on('error', (e) => {
      res.writeHead(502, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ error: { message: String(e) } }));
    });

    up.end(body);
  });
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`[claude-proxy] nghe tại http://127.0.0.1:${PORT}`);
  console.log(`[claude-proxy] upstream : ${UPSTREAM}`);
  console.log(`[claude-proxy] key đọc từ: ${keySource}`);
  console.log(`[claude-proxy] trạng thái : ĐANG CHẠY — gỡ trường "model" trước khi chuyển tiếp`);
});
