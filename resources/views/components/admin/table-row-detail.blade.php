@props([
    'colspan' => 100,
])

<tr x-show="expanded === $el.previousElementSibling"
    data-row-detail
    x-cloak
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-1"
    class="md:hidden bg-[#101010] border-b border-gray-800 font-mono">
    <td colspan="{{ $colspan }}" class="p-3 sm:p-4">
        <div class="bg-[#161616] border border-gray-800 rounded-xl p-3.5 shadow-inner space-y-2 divide-y divide-gray-800/60">
            {{ $slot }}
        </div>
    </td>
</tr>
