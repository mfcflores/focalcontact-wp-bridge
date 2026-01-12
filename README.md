# FocalContact WP Bridge

Version: 0.2.0

Includes:
- UTM persistence + hidden field injection
- Modular feature toggles
- HighLevel external tracking script: paste script, extract src + tracking id, safe output in footer
- Page-level conditions for tracking injection (public only, include/exclude paths)
- Event bridge: `window.FCWPB.track(type, data)` and `fcwpb:track` CustomEvent -> posts to WP REST `/fcwpb/v1/event`

Notes:
- HighLevel API endpoints in this MVP are placeholders (`contacts`, `events`) and must be mapped to your chosen endpoints.
