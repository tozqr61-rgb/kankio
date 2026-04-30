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

## 2026-04-30T20:58:39+03:00

- Context:
  - Converted chat room navigation from full-page reloads to an in-page room bootstrap flow so LiveKit voice runtime can survive switching the viewed room.
  - Added `/api/chat/{roomId}/bootstrap` for loading room metadata, recent messages, and archived counts without replacing the browser document.
- Tech Debt & Resolutions:
  - Split Echo setup from room channel subscription management with explicit leave/join helpers for chat, music, and voice channels.
  - Moved polling, archived-message loading, and seen tracking to the active `_roomId` instead of the initial page `ROOM_ID`.
- Observability/Security Impact:
  - Bootstrap uses the existing room access check and returns 403 JSON for inaccessible rooms.
  - Echo init failures now emit a console warning while preserving polling fallback behavior.
- Architectural Foresight:
  - Viewed room state now changes independently from `_voiceRoomId`, preserving active voice connections while allowing message panels to follow browser history.
  - Voice participant rendering is scoped to the viewed voice room so navigating away does not present the active call's participant list as the new room's state.
