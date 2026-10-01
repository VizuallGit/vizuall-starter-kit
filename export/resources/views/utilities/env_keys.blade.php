{{--
    Nøgler — de få .env-værdier kontrolpanelet må sætte.

    Værdien vises aldrig. Skærmen får kun at vide om en nøgle er sat.
--}}
@unless ($writable)
    <div class="card p-4 mb-4 text-red-500">
        <code>.env</code> kan ikke skrives af webserveren, så nøglerne kan kun sættes i Ploi.
    </div>
@endunless

<div class="card p-0 overflow-hidden">
    @foreach ($rows as $row)
        <div class="p-4 border-b">
            <form method="POST" action="{{ cp_route('utilities.keys.save') }}">
                @csrf
                <input type="hidden" name="key" value="{{ $row['key'] }}" />

                <div class="flex gap-4 flex-wrap items-end">
                    <label class="flex-1 min-w-[18rem]">
                        <span class="block mb-1">
                            {{ $row['label'] }}
                            @if ($row['set'])
                                <span class="text-2xs text-green-600">&middot; sat</span>
                            @else
                                <span class="text-2xs text-gray-600">&middot; ikke sat</span>
                            @endif
                        </span>
                        <input type="password" name="value" value="" autocomplete="new-password"
                               placeholder="{{ $row['set'] ? 'Indsæt en ny for at erstatte' : 'Indsæt nøglen' }}"
                               class="input-text" @disabled(! $writable) />
                        <span class="block text-2xs text-gray-600 mt-1">{{ $row['hint'] }} Gemmes i <code>.env</code>, som ikke ligger i git.</span>
                    </label>

                    <button type="submit" class="btn-primary" @disabled(! $writable)>Gem</button>
                </div>
            </form>
        </div>
    @endforeach
</div>

<p class="text-2xs text-gray-600 mt-2">
    Et tomt felt gemmer ingenting. Vil du fjerne en nøgle, så skriv et mellemrum og gem.
    Miljøet (<code>APP_ENV</code>) kan bevidst ikke sættes herfra — den afgør også om fejl vises
    med adgangskoder i, og den sættes én gang i Ploi.
</p>
