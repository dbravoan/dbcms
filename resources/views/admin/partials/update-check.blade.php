    @auth
        @if(auth()->user()->hasPermission('manage:updates'))
            <script nonce="{{ $cspNonce ?? '' }}">
                window.addEventListener('load', () => {
                    fetch(@json(route('admin.updates.check')), {method: 'POST', headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: '{}', credentials: 'same-origin'}).catch(() => {});
                }, {once: true});
            </script>
        @endif
    @endauth
