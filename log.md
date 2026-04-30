# log.md

## 2026-04-30T16:24:04+03:00

- Context:
  - Hardened LiveKit voice into a stateful realtime flow: JSON-safe join errors, reconnect handling, speaking/deafen/mute sync, voice presence, connection quality, moderation actions, and room-level voice settings.
  - Converted chat updates to Reverb private-channel events for message create/delete, typing, read receipts, and event-driven unread count updates with polling as a fallback.
  - Added request IDs, JSON logs, lightweight app metrics, admin metrics exposure, CORS/Reverb origin allowlists, Tauri CSP/permission tightening, and protected `/baglantikal` write/upload routes.
- Tech Debt & Resolutions:
  - Installed and locked the LiveKit server SDK dependency required by `VoiceController`.
  - Added the missing `terser` dev dependency so the existing Vite production minifier can run reproducibly.
  - Removed secret-bearing helper scripts and blanked LiveKit sample credentials in `.env.example`; real exposed credentials still need rotation and git-history purging outside this patch.
- Observability/Security Impact:
  - Voice joins, join failures, reconnects, moderation actions, concurrent voice users, and external-service failures now emit structured logs/metrics.
  - Private broadcast channels now enforce room access, reply targets are constrained to the same room, and `/broadcasting/auth` is no longer accidentally proxied to Reverb in Nginx examples.
- Architectural Foresight:
  - Voice state is now server-authoritative in `voice_sessions`, with client LiveKit events treated as telemetry/state patches.
  - Polling remains as an explicit degraded-mode fallback while the primary product path uses Reverb events.
