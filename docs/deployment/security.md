# Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong unique `APP_KEY` backed up offline
- [ ] Document root is `public/` only
- [ ] HTTPS enabled for the control panel
- [ ] Platform admin password is unique and rotated
- [ ] Outbound HTTP (`OUTBOUND_ALLOW_HTTP`) remains disabled unless intentionally required
- [ ] TLS verification remains enabled by default for endpoints
- [ ] API keys scoped tightly and revoked when unused
- [ ] Mail credentials stored only in `.env`
- [ ] File permissions prevent web write access to application source
