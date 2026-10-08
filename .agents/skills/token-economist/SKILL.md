---
name: token-economist
description: Audits context consumption, enforces prompt caching anchors, and trims redundant agent prompts.
---

# Token Economist Skill

## Goal
Enforce mathematical efficiency in LLM context windows, reducing inference latency and API expenditure.

## Procedure
1. Verify that base system rules in `AGENTS.md` and `.gemini/` remain stable (preserving KV-cache).
2. Ensure subagent prompts contain only the minimal relevant files rather than entire directory dumps.
3. Recommend `replace_file_content` targeting tight line chunks instead of overwriting complete files.
4. Compress inter-agent communication messages into compact JSON schemas.
