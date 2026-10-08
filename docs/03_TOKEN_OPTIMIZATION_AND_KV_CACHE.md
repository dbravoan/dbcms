# Document 03: Token Economics, KV-Cache Dynamics & Attention Sinks

## The Attention Matrix & Memory Bottleneck
Modern Transformer models (Vaswani et al., 2017) compute self-attention across tokens as:

$$\text{Attention}(Q, K, V) = \text{softmax}\left(\frac{QK^T}{\sqrt{d_k}}\right)V$$

In auto-regressive decoding, Key ($K$) and Value ($V$) tensors are cached across sequential turns (KV-Cache). The inference cost of generating new tokens has two components:
1. **Time-to-First-Token (TTFT) / Prefill**: Processing the entire prompt context.
2. **Time-Per-Output-Token (TPOT) / Decoding**: Generating subsequent tokens against the KV cache.

## Prompt Caching Mechanics
When prefix tokens match an existing cached prefix in cloud infrastructure (Google Gemini, Anthropic Claude, OpenAI), the prefill cost drops dramatically:
- **Cached Input Cost**: $\approx 10\% - 20\%$ of uncached token rate.
- **Prefill Latency**: Decreases by up to $85\%$.

```
Prompt Layout:
+-------------------------------------------------------------+
| STATIC PREFIX (Cached, 0.1x Cost)                           |
| - Root AGENTS.md                                            |
| - Architectural Rules (.agents/rules/*.md)                  |
| - DTO & Service Contracts Interfaces                        |
+-------------------------------------------------------------+
| DYNAMIC SUFFIX (Uncached, 1.0x Cost)                        |
| - Current User Request                                      |
| - Target File Diff (replace_file_content)                   |
| - Pest Error Output                                         |
+-------------------------------------------------------------+
```

## Token Optimization Best Practices in this Scaffold
1. **Rule Atomicity**: Each rule in `.agents/rules/` is bounded to $< 250$ tokens.
2. **Progressive Skill Mounting**: Skills (`.agents/skills/`) are discovered by name and mounted on-demand rather than being statically dumped into the system prompt.
3. **Contiguous Code Replacements**: Agents use surgical block replacement (`replace_file_content`) instead of full file rewriting (`write_to_file` on large files), reducing output tokens by over $90\%$.
4. **Subagent Context Isolation**: Subagents run with clean, focused context windows, eliminating accumulated conversation noise.
