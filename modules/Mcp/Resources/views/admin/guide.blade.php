<div class="space-y-6 pt-4 font-mono text-xs">
    {{-- Token Generado Recientemente --}}
    @if(session('mcp_token_generated'))
        <div class="p-5 rounded-xl bg-[#00FF99]/10 border-2 border-[#00FF99] space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-white font-bold flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-key text-[#00FF99]"></i> ¡Personal Access Token Generado con Éxito!
                </span>
                <span class="text-[10px] bg-[#00FF99]/20 text-[#00FF99] px-2 py-0.5 rounded font-bold">PASSPORT OAUTH2</span>
            </div>
            <p class="text-gray-300 text-xs">
                Copia este token ahora. Por motivos de seguridad criptográfica con Laravel Passport, no se volverá a mostrar completo en pantalla.
            </p>
            <div class="relative flex items-center">
                <input type="text" readonly id="generatedMcpToken" value="{{ session('mcp_token_generated')['token'] }}" class="w-full bg-[#1A1A1A] border border-[#00FF99]/50 rounded-lg p-3 pr-24 text-white font-mono text-xs focus:outline-none select-all">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('generatedMcpToken').value); this.innerText='¡Copiado!';" class="absolute right-2 px-3 py-1.5 bg-[#00FF99] text-black font-bold rounded hover:bg-[#00cc7a] transition-all text-xs">
                    Copiar
                </button>
            </div>
        </div>
    @endif

    {{-- Encabezado y 1-Click Action --}}
    <div class="p-6 rounded-xl bg-[#1A1A1A] border border-gray-800 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-[#00FF99]"></i> Autenticación Exclusiva con Laravel Passport
                </h3>
                <p class="text-gray-400 text-xs mt-1">
                    {!! __('admin.mcp.oauth_scope_desc') !!}
                </p>
            </div>
            <div class="shrink-0 flex items-center gap-3">
                <form action="{{ route('admin.modules.mcp.generate-token') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-[#00FF99] hover:bg-[#00cc7a] text-black font-bold rounded-lg text-xs transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-bolt"></i> 1-Clic: Emitir Token Passport
                    </button>
                </form>
                <a href="{{ route('admin.tokens.index') }}" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-lg text-xs transition-colors">
                    Ver todos los tokens &rarr;
                </a>
            </div>
        </div>
    </div>

    {{-- Guía de Agentes Externos --}}
    <div class="p-6 rounded-xl bg-[#1A1A1A] border border-gray-800 space-y-5">
        <div>
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-terminal text-[#00FF99]"></i> Guía de Conexión para Agentes Externos
            </h3>
            <p class="text-gray-400 text-xs mt-1">
                {!! __('admin.mcp.agents_desc') !!}
            </p>
            <p class="text-gray-400 text-[11px]">{!! __('admin.mcp.transport_hint', ['endpoint' => url('/mcp/cms'), 'legacy' => url('/api/mcp'), 'sse' => url('/api/mcp/sse')]) !!}</p>
        </div>

        {{-- Tabs de Clientes --}}
        <div x-data="{ activeTab: 'opencode' }" class="space-y-4">
            <div class="flex items-center gap-2 border-b border-gray-800 pb-2">
                <button type="button" @click="activeTab = 'opencode'" :class="activeTab === 'opencode' ? 'text-[#00FF99] border-b-2 border-[#00FF99] font-bold' : 'text-gray-400 hover:text-white'" class="px-3 py-1.5 transition-colors text-xs flex items-center gap-1.5">
                    <span class="text-xs">⚡</span> OpenCode (Recomendado)
                </button>
                <button type="button" @click="activeTab = 'cursor'" :class="activeTab === 'cursor' ? 'text-[#00FF99] border-b-2 border-[#00FF99] font-bold' : 'text-gray-400 hover:text-white'" class="px-3 py-1.5 transition-colors text-xs flex items-center gap-1.5">
                    <span>🖱️</span> Cursor IDE
                </button>
                <button type="button" @click="activeTab = 'claude'" :class="activeTab === 'claude' ? 'text-[#00FF99] border-b-2 border-[#00FF99] font-bold' : 'text-gray-400 hover:text-white'" class="px-3 py-1.5 transition-colors text-xs flex items-center gap-1.5">
                    <span>🤖</span> Claude Desktop
                </button>
                <button type="button" @click="activeTab = 'antigravity'" :class="activeTab === 'antigravity' ? 'text-[#00FF99] border-b-2 border-[#00FF99] font-bold' : 'text-gray-400 hover:text-white'" class="px-3 py-1.5 transition-colors text-xs flex items-center gap-1.5">
                    <span>🪐</span> {{ config('cms.name') }} CLI
                </button>
            </div>

            {{-- Tab 1: OpenCode --}}
            <div x-show="activeTab === 'opencode'" class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-300 font-bold">{!! __('admin.mcp.file_opencode') !!}</span>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('opencodeConfig').innerText); this.innerText='¡Copiado!';" class="text-xs text-[#00FF99] hover:underline font-bold">
                        Copiar Configuración
                    </button>
                </div>
                <pre id="opencodeConfig" class="p-4 rounded-lg bg-black/70 border border-gray-800 text-gray-300 overflow-x-auto select-all leading-relaxed">{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "dbCMS": {
      "type": "remote",
      "url": "{{ url('/mcp/cms') }}",
      "enabled": true,
      "oauth": false,
      "headers": {
        "Authorization": "Bearer TU_PASSPORT_TOKEN_AQUI"
      }
    }
  }
}</pre>
                <p class="text-gray-400 text-[11px]">
                    {!! __('admin.mcp.token_hint') !!}
                    <a href="https://opencode.ai/docs/mcp-servers/" target="_blank" rel="noopener noreferrer" class="text-[#00FF99] underline">OpenCode: documentación MCP</a>
                </p>
            </div>

            {{-- Tab 2: Cursor --}}
            <div x-show="activeTab === 'cursor'" class="space-y-3" style="display: none;">
                <div class="flex items-center justify-between">
                    <span class="text-gray-300 font-bold">{!! __('admin.mcp.file_cursor') !!}</span>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('cursorConfig').innerText); this.innerText='¡Copiado!';" class="text-xs text-[#00FF99] hover:underline font-bold">
                        Copiar Configuración
                    </button>
                </div>
                <pre id="cursorConfig" class="p-4 rounded-lg bg-black/70 border border-gray-800 text-gray-300 overflow-x-auto select-all leading-relaxed">{
  "mcpServers": {
    "dbCMS": {
      "url": "{{ url('/mcp/cms') }}",
      "headers": {
        "Authorization": "Bearer TU_PASSPORT_TOKEN_AQUI"
      }
    }
  }
}</pre>
            </div>

            {{-- Tab 3: Claude Desktop --}}
            <div x-show="activeTab === 'claude'" class="space-y-3" style="display: none;">
                <div class="flex items-center justify-between">
                    <span class="text-gray-300 font-bold">{!! __('admin.mcp.file_claude') !!}</span>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('claudeConfig').innerText); this.innerText='¡Copiado!';" class="text-xs text-[#00FF99] hover:underline font-bold">
                        Copiar Configuración
                    </button>
                </div>
                <p class="text-gray-400 text-[11px]">{!! __('admin.mcp.bridge_hint') !!} <a href="https://github.com/punkpeye/mcp-remote" target="_blank" rel="noopener noreferrer" class="text-[#00FF99] underline">mcp-remote: documentación</a></p>
                <pre id="claudeConfig" class="p-4 rounded-lg bg-black/70 border border-gray-800 text-gray-300 overflow-x-auto select-all leading-relaxed">{
  "mcpServers": {
    "dbCMS": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "{{ url('/mcp/cms') }}",
        "--header",
        "Authorization:${AUTH_HEADER}"
      ],
      "env": {
        "AUTH_HEADER": "Bearer TU_PASSPORT_TOKEN_AQUI"
      }
    }
  }
}</pre>
            </div>

            {{-- Tab 4: generic CLI --}}
            <div x-show="activeTab === 'antigravity'" class="space-y-3" style="display: none;">
                <div class="flex items-center justify-between">
                    <span class="text-gray-300 font-bold">{!! __('admin.mcp.config_gemini', ['app' => config('cms.name')]) !!}</span>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('antigravityConfig').innerText); this.innerText='¡Copiado!';" class="text-xs text-[#00FF99] hover:underline font-bold">
                        Copiar Configuración
                    </button>
                </div>
                <pre id="antigravityConfig" class="p-4 rounded-lg bg-black/70 border border-gray-800 text-gray-300 overflow-x-auto select-all leading-relaxed">{
  "name": "dbCMS",
  "url": "{{ url('/mcp/cms') }}",
  "headers": {
    "Authorization": "Bearer TU_PASSPORT_TOKEN_AQUI"
  }
}</pre>
            </div>
        </div>
    </div>
</div>
