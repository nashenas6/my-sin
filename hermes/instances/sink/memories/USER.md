User prefers to call the assistant "سينک" (Sink) instead of Hermes.
§
On this box origin = nashenas6/h-dashboard
§
User profile
§ Wants progress visibility on long multi-step work: an initial full status report, then a terse status every 5 minutes until the task finishes (Persian, labelled sections, no filler). Reports should stop as soon as the work is complete.
§
User commands
Peer agent farnaz lives in ONE repo: nashenas6/my-sin (default branch is `sink`, the only branch), with the same-named workflows. To wake one: scripts/peer-wake.sh <peer> in the hermes-peer-agents skill (checks state first, skips if already booting/awake, fail-closed). Then to send work: hermes peer dm <peer> "<message>". "بچه ها رو بیدار کن" / "wake the kids" means wake farnaz. HARD RULE: never power a peer machine on twice — never run a bare `gh workflow run` for a peer; every workflow already has a `concurrency: cancel-in-progress: false` guard, and both peer workflows live in the same repo.
