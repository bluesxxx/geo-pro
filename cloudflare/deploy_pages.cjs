/**
 * 部署 JetSocio Hub（纯静态）到 Cloudflare Pages 项目 jetsocio-hub。
 * 使用 upload-then-deploy 直传：文件以「相对 hub/ 的路径」作为表单字段名上传，站点根直接服务。
 * 读取 D:\GEO\.env 中的 CF_API_TOKEN（通过临时 token 文件）。
 */
const fs = require("fs");
const path = require("path");

const ACCOUNT = "ea6f0d8cb45cda1d71ffde56a11bac71";
const PROJECT = "jetsocio-hub";
const HUB = "D:/GEO/GEOFlow-main/hub";

// token 来源：优先临时文件，回退到 .env
let TOKEN = "";
const TOKEN_FILE = "C:/Users/Blues/AppData/Local/Temp/cf_token";
if (fs.existsSync(TOKEN_FILE)) TOKEN = fs.readFileSync(TOKEN_FILE, "utf8").trim();
if (!TOKEN) {
  const envPath = "D:/GEO/.env";
  if (fs.existsSync(envPath)) {
    const txt = fs.readFileSync(envPath, "utf8");
    const m = txt.match(/CF_API_TOKEN\s*=\s*(\S+)/);
    if (m) TOKEN = m[1].trim();
  }
}
if (!TOKEN) { console.error("CF_API_TOKEN not found"); process.exit(1); }

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".ico": "image/x-icon",
  ".txt": "text/plain; charset=utf-8",
  ".xml": "application/xml",
};

function walk(dir) {
  const out = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) out.push(...walk(full));
    else out.push(full);
  }
  return out;
}

// 跳过 CNAME（Cloudflare Pages 自定义域名走后台配置，不需要此文件）
const files = walk(HUB).filter((f) => path.basename(f).toLowerCase() !== "cname");

const fd = new FormData();
for (const f of files) {
  const rel = path.relative(HUB, f).split(path.sep).join("/");
  const ext = path.extname(f).toLowerCase();
  const type = MIME[ext] || "application/octet-stream";
  fd.append(rel, new Blob([fs.readFileSync(f)], { type }), rel);
}

(async () => {
  const url = `https://api.cloudflare.com/client/v4/accounts/${ACCOUNT}/pages/projects/${PROJECT}/upload-then-deploy`;
  const res = await fetch(url, {
    method: "POST",
    headers: { Authorization: `Bearer ${TOKEN}` },
    body: fd,
  });
  const text = await res.text();
  console.log("HTTP", res.status);
  console.log("uploaded files:", files.length);
  console.log(files.map((f) => "  " + path.relative(HUB, f)).join("\n"));
  console.log("--- response ---");
  console.log(text.slice(0, 1200));
})().catch((e) => { console.error("DEPLOY ERROR:", e); process.exit(1); });
