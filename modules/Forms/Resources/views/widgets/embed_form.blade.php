@if($form)
    <div class="bg-white rounded-3xl p-8 md:p-10 shadow-lg border border-slate-100 max-w-2xl mx-auto">
        <h3 class="text-2xl font-bold text-slate-900 mb-2">{{ $form->name }}</h3>
        @if($form->description)
            <p class="text-slate-500 text-sm mb-6">{{ $form->description }}</p>
        @endif

        @if(session('form_success'))
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 text-emerald-800 text-sm font-semibold border border-emerald-200">
                {{ session('form_success') }}
            </div>
        @endif

        <form action="{{ route('forms.submit', ['formSlug' => $form->slug]) }}" method="POST" class="space-y-4">
            @csrf

            <x-cms-form.fields
                :schema="app(\App\Core\Forms\SchemaFactory::class)->fromModels($form->fields)"
                variant="light"
                layout="stack"
                honeypot="_hp_check"
            />

            <div class="pt-2">
                <button type="submit" class="w-full py-3.5 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all">
                    {{ $form->submit_button_text ?: 'Submit' }} &rarr;
                </button>
            </div>
        </form>
    </div>
@else
    <div class="text-center py-8 text-slate-400">Form not found or inactive.</div>
@endif
