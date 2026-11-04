<style>
    :root { color-scheme: light dark; --ink: #1f2328; --muted: #57606a; --bg: #f6f7f9; --card: #fff; --line: #e3e5e8; --ok: #1a7f37; }
    @media (prefers-color-scheme: dark) { :root { --ink: #e6edf3; --muted: #9da7b3; --bg: #0d1117; --card: #161b22; --line: #30363d; --ok: #3fb950; } }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--bg); color: var(--ink); font: 16px/1.55 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
    main { width: min(520px, 100%); padding: 32px 28px; border: 1px solid var(--line); border-radius: 16px; background: var(--card); text-align: center; }
    h1 { margin: 0 0 10px; font-size: 26px; line-height: 1.2; }
    p { margin: 0 0 12px; color: var(--muted); }
    .mark { display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; margin: 0 0 14px; border-radius: 50%; background: color-mix(in srgb, var(--ok) 14%, transparent); color: var(--ok); font-size: 26px; font-weight: 700; }
    dl { margin: 18px 0; text-align: left; }
    dl div { display: flex; justify-content: space-between; gap: 16px; padding: 10px 0; border-top: 1px solid var(--line); }
    dt { color: var(--muted); } dd { margin: 0; font-weight: 600; text-align: right; }
    .small { font-size: 14px; }
</style>
