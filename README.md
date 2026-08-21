# GEO PRO

> Languages: [简体中文](README.md) | [English](docs/readme/README_en.md) | [日本語](docs/readme/README_ja.md) | [Español](docs/readme/README_es.md) | [Русский](docs/readme/README_ru.md) | [Português (BR)](docs/readme/README_pt_BR.md)

GEO PRO 是一套面向 **GEO（生成式引擎优化）** 的自托管智能内容工程与多站点分发系统，基于 Apache-2.0 开源项目 [GEOFlow](https://github.com/yaojingang/GEOFlow) 深度品牌化的发行版。它把知识库、素材库、提示词、AI 生成任务、审核发布、数据分析、目标站点包、WordPress REST 渠道、通用 HTTP API 渠道和远端静态页面分发串联为一条可持续运营的工作链路，帮助团队把可信资料沉淀为可管理、可发布、可追踪、可同步到多端的 GEO 内容资产。

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/Database-PostgreSQL-336791)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-blue)](https://docs.docker.com/compose/)
[![License](https://img.shields.io/badge/License-Apache--2.0-blue.svg)](LICENSE)

GEO PRO 以 [Apache License 2.0](LICENSE) 开源发布。本 fork 保留上游许可证文本（见 `LICENSE` 与 `NOTICE`），并默认**关闭上游遥测与更新检查**，不向任何外部端点上报数据。

> GEO PRO 是 [JetSocio](https://jetsocio.com) 产品矩阵的一员。JetSocio Hub 聚合四个产品：GEO PRO（本仓库）、salesbuddy（自托管 WhatsApp CRM）、PackMeta（3D 装箱优化）、DocuConsist（外贸单证一致核验）。

---

## ✨ 快速开始（Docker 一键部署）

```bash
git clone https://github.com/bluesxxx/geo-pro.git
cd geo-pro
cp .env.prod.example .env.prod   # 按需修改 APP_URL / 数据库 / 管理员账号
docker compose -f docker-compose.prod.yml up -d
```

访问 `http://<host>:18080`。更多部署方式见 [docs/deployment/DEPLOYMENT.md](docs/deployment/DEPLOYMENT.md) 与 [deploy-scripts](deploy-scripts/)。

> 首次空库部署会自动迁移并导入 50 篇参考内容；已有数据升级不会覆盖站点设置与文章。

## 🔌 环境变量要点

| 变量 | 默认 | 说明 |
|------|------|------|
| `SITE_NAME` / `SITE_FULL_NAME` | `GEO PRO` | 站点展示名 |
| `GEOFLOW_TELEMETRY_ENABLED` | `false` | 匿名统计（fork 默认关闭） |
| `GEOFLOW_UPDATE_CHECK_ENABLED` | `false` | 上游更新检查（fork 默认关闭） |
| `GEOFLOW_UPDATE_METADATA_URL` | 空 | 如自建更新源在此配置 |
| `ADMIN_BASE_PATH` | `geo_admin` | 后台入口路径 |

## 📦 核心能力

| 特性 | 说明 |
|------|------|
| 🤖 多模型内容生成 | 兼容 OpenAI 风格接口与 Gemini 原生接口，chat / embedding / Provider URL 自动适配、失败重试与调用统计 |
| 🧠 知识库与 RAG | 结构化规则切片 + 可选 LLM 语义规划；embedding 向量索引，文章生成时召回资料 |
| 🗂 素材与提示词体系 | 标题库、关键词库、图片库、作者库、提示词集中管理 |
| 📦 任务自动化 | 任务创建、草稿池、审核、发布节奏、队列执行、失败重试、发布范围控制 |
| 📋 审核与文章管理 | 草稿、审核、发布、回收站、作者、分类、SEO 字段统一管理 |
| 🧭 GEO 体检钩子 | 内置真后端网站 GEO 体检：抓取 URL → 提取 H1/JSON-LD/FAQ 特征 → 规则评分（可配 Key 升级 LLM 深评），结果页带「本地部署完整版」CTA |
| 🌍 多端分发 | WordPress REST / 通用 HTTP API / 远端静态页面 / Agent 目标站点包 |

## 🔒 合规与隐私

- **许可**：Apache-2.0。fork 保留 `LICENSE` 原文；`NOTICE` 已改写为 GEO PRO / JetSocio 版权并注明基于 GEOFlow 修改。
- **无电话回家**：遥测、更新检查默认关闭，百度统计等上游默认注入已移除。
- **安全出站**：外部 HTTP 统一走白名单网关（`GEOFLOW_OUTBOUND_PRIVATE_TARGETS`），私网目标默认全部拒绝。

## 📚 文档

- 部署：[docs/deployment/DEPLOYMENT.md](docs/deployment/DEPLOYMENT.md)
- 主题与前端契约：[docs](docs/)
- 参考脚本：[deploy-scripts](deploy-scripts/)

## 🤝 致谢

本项目基于 [GEOFlow](https://github.com/yaojingang/GEOFlow)（Copyright 2026 Yao Jingang，Apache-2.0）修改而来，感谢上游作者的杰出工作。
