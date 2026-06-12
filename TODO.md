# TODO

## Toward a fuller ACP SDK

- [x] Add typed Client API wrappers for stable ACP v1 methods:
  `session/new`, `session/load`, `session/resume`, `session/close`,
  `session/list`, `session/delete`, `session/prompt`, `session/cancel`,
  `authenticate`, `logout`, `session/set_config_option`, and
  `session/set_mode`.
- [ ] Add event and notification handling for `session/update` messages,
  including prompt progress, tool calls, plans, usage updates, session metadata,
  config updates, and command updates.
- [ ] Add client-side callback handling for ACP requests from agents, including
  filesystem, terminal, and permission request methods.
- [ ] Introduce PHP value objects or DTOs for common ACP schema structures while
  keeping array escape hatches for custom agent extensions.
- [ ] Expand compatibility tests against real ACP agents beyond the current Kimi
  smoke test.
- [ ] Add optional HTTP/WebSocket transports after those ACP transports are
  stable enough for this library's support target.
