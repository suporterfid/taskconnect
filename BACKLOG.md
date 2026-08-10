# Roadmap

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*

---

## TaskConnect Product Roadmap & Backlog

This document outlines upcoming features, architectural refinements, and planned enhancements for TaskConnect within the IROH ecosystem.

---

## Short-Term Enhancements (v1.4.x)

- [ ] **Enhanced Metric Exporters**: Provide OpenTelemetry / Prometheus metric format adapters for external dashboard integration.
- [ ] **Advanced Webhook Signature Presets**: Built-in header signature presets for popular third-party services (GitHub, Stripe, Slack, Shopify).
- [ ] **Granular Workspace Roles**: Expand operator SPA role permissions (Viewer, Operator, Admin, Auditor).

---

## Mid-Term Features (v2.0.0)

- [ ] **Dynamic Webhook Rate Limiting**: Per-endpoint adaptive throttling based on target server HTTP `429` / `503` response headers.
- [ ] **Multi-Region Failover Simulation**: Secondary database sync helper for zero-downtime shared hosting migrations.
- [ ] **Interactive Pipeline Designer**: Drag-and-drop visual DAG pipeline builder in the Vue 3 SPA operator panel.

---

## Long-Term Vision

- [ ] **Unified IROH Dashboard Suite**: Cross-app telemetry integration connecting TaskConnect execution metrics with `suporterfid/statusconnect` and `suporterfid/tallymark`.
