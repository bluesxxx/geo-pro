/**
 * 部署 JetSocio Hub（纯静态）到 Cloudflare Pages 项目 jetsocio-hub。
 * Cloudflare Pages Direct Upload 三步流程：
 *   1) upsert-hashes —— 上报文件哈希，返回缺失清单
 *   2) upload        —— 上传缺失的文件内容（base64，字段名=hash）
 *   3) deployments   —— 提交 manifest（路径→hash）创建部署
 *
 * 环境变量：
 *   CF_API_TOKEN  Cloudflare API Token（必填；也可写入脚本旁 .env-geo.local）
 *   HUB_DIR       hub 目录（默认 ./hub）
 */
const fs = require("fs");
const path = require("path");
const crypto = require("crypto");

const ACCOUNT = "ea6f0d8cb45cda1d71ffde56a11bac71";
const PROJECT = "jetsocio-hub";
const HUB = process.env.HUB_DIR ? path.resolve(process.env.HUB_DIR) : path.resolve(__dirname, "..", "hub");
const API = `https://api.cloudflare.com/client/v4/accounts/${ACCOUNT}`;

// token 来源：环境变量 > 脚本旁的 .env-geo.local
let TOKEN = process.env.CF_API_TOKEN || "";
if (!TOKEN) {
  const envPath = path.join(__dirname, ".env-geo.local");
  if (fs.existsSync(envPath)) {
    const m = fs.readFileSync(envPath, "utf8").match(/CF_API_TOKEN\s*=\s*(\S+)/);
    if (m) TOKEN = m[1].trim();
  }
}
if (!TOKEN) { console.error("CF_API_TOKEN not found"); process.exit(1); }

function walk(dir) {
  const out = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) out.push(...walk(full));
    else out.push(full);
  }
  return out;
}

const files = walk(HUB).filter((f) => {
  const base = path.basename(f).toLowerCase();
  return base !== "cname" && base !== ".env-geo.local";
});

(async () => {
  // 1) upsert-hashes
  const manifest = {};
  const hashes = [];
  for (const f of files) {
    const rel = path.relative(HUB, f).split(path.sep).join("/");
    const h = crypto.createHash("sha256").update(fs.readFileSync(f)).digest("hex");
    manifest[rel] = h;
    hashes.push(h);
  }

  const upsert = await fetch(`${API}/pages/assets/upsert-hashes`, {
    method: "POST",
    headers: { Authorization: `Bearer ${TOKEN}`, "Content-Type": "application/json" },
    body: JSON.stringify(hashes),
  });
  const upsertJson = await upsert.json();
  if (!upsert.ok || upsertJson.success !== true) {
    console.error("upsert-hashes failed:", upsert.status, JSON.stringify(upsertJson).slice(0, 500));
    process.exit(1);
  }
  const missing = upsertJson.result || [];
  console.log(`files: ${files.length}, need upload: ${missing.length}`);

  // 2) upload missing files
  if (missing.length > 0) {
    const fd = new FormData();
    for (const f of files) {
      const rel = path.relative(HUB, f).split(path.sep).join("/");
      const h = manifest[rel];
      if (!missing.includes(h)) continue;
      fd.append(h, fs.readFileSync(f).toString("base64"), h);
    }
    const upload = await fetch(`${API}/pages/assets/upload`, {
      method: "POST",
      headers: { Authorization: `Bearer ${TOKEN}` },
      body: fd,
    });
    const uploadText = await upload.text();
    if (!upload.ok) {
      console.error("upload failed:", upload.status, uploadText.slice(0, 800));
      process.exit(1);
    }
    console.log("uploaded:", missing.length);
  }

  // 3) create deployment
  const depFd = new FormData();
  depFd.append("manifest", JSON.stringify(manifest));
  const dep = await fetch(`${API}/pages/projects/${PROJECT}/deployments`, {
    method: "POST",
    headers: { Authorization: `Bearer ${TOKEN}` },
    body: depFd,
  });
  const depJson = await dep.json();
  if (!dep.ok || depJson.success !== true) {
    console.error("deployment failed:", dep.status, JSON.stringify(depJson).slice(0, 800));
    process.exit(1);
  }
  console.log("deployed:", depJson.result.url || "(ok)");
  console.log("aliases:", JSON.stringify(depJson.result.aliases ?? []));
})().catch((e) => { console.error("DEPLOY ERROR:", e); process.exit(1); });
