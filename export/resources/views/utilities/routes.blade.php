{{--
    Ruter — en side der kun er en adresse og en skabelon.

    Samme bare opsætning som omdirigeringerne: almindelige felter, almindelig
    form-post, ingen JS. Klasserne er Statamics egne.
--}}
<div class="card p-0 overflow-hidden">
    <form method="POST" action="{{ cp_route('utilities.routes.save') }}">
        @csrf

        @foreach ($rows as $i => $row)
            @php($problem = $store->problem($row))
            <div class="p-4 border-b">
                <div class="flex gap-4 flex-wrap items-end">
                    <label class="flex-1 min-w-[12rem]">
                        <span class="block text-2xs text-gray-600 mb-1">Adresse</span>
                        <input type="text" name="rows[{{ $i }}][uri]" value="{{ $row['uri'] }}" class="input-text" />
                    </label>

                    <label class="flex-1 min-w-[12rem]">
                        <span class="block text-2xs text-gray-600 mb-1">Skabelon</span>
                        <select name="rows[{{ $i }}][view]" class="input-text">
                            @foreach ($views as $view)
                                <option value="{{ $view }}" @selected($row['view'] === $view)>{{ $view }}</option>
                            @endforeach
                            @unless (in_array($row['view'], $views, true))
                                <option value="{{ $row['view'] }}" selected>{{ $row['view'] }} (findes ikke)</option>
                            @endunless
                        </select>
                    </label>

                    <label class="flex-1 min-w-[12rem]">
                        <span class="block text-2xs text-gray-600 mb-1">Titel</span>
                        <input type="text" name="rows[{{ $i }}][title]" value="{{ $row['title'] }}" class="input-text" />
                    </label>

                    <label class="whitespace-nowrap">
                        <span class="block text-2xs text-gray-600 mb-1">Til&nbsp;/&nbsp;fra</span>
                        <input type="hidden" name="rows[{{ $i }}][enabled]" value="0" />
                        <input type="checkbox" name="rows[{{ $i }}][enabled]" value="1" @checked($row['enabled']) />
                    </label>

                    <label class="whitespace-nowrap">
                        <span class="block text-2xs text-gray-600 mb-1">I sitemap</span>
                        <input type="hidden" name="rows[{{ $i }}][sitemap]" value="0" />
                        <input type="checkbox" name="rows[{{ $i }}][sitemap]" value="1" @checked($row['sitemap']) />
                    </label>
                </div>

                <label class="block mt-3">
                    <span class="block text-2xs text-gray-600 mb-1">Flere variabler — én <code>nøgle: værdi</code> pr. linje</span>
                    <textarea name="rows[{{ $i }}][data]" rows="2" class="input-text font-mono text-2xs">{{ $store->dataAsLines($row) }}</textarea>
                </label>

                @if ($problem)
                    <p class="text-2xs text-red-500 mt-2">{{ $problem }} Linjen er gemt, men den svarer ikke på noget.</p>
                @elseif ($row['enabled'])
                    <p class="text-2xs text-gray-600 mt-2">Svarer på <code>{{ $row['uri'] }}</code></p>
                @endif
            </div>
        @endforeach

        {{-- En tom linje i bunden: udfyld den, gem, og der kommer en ny. --}}
        @php($next = count($rows))
        <div class="p-4 border-b bg-gray-100">
            <div class="flex gap-4 flex-wrap items-end">
                <label class="flex-1 min-w-[12rem]">
                    <span class="block text-2xs text-gray-600 mb-1">Adresse</span>
                    <input type="text" name="rows[{{ $next }}][uri]" value="" placeholder="/soeg" class="input-text" />
                </label>

                <label class="flex-1 min-w-[12rem]">
                    <span class="block text-2xs text-gray-600 mb-1">Skabelon</span>
                    <select name="rows[{{ $next }}][view]" class="input-text">
                        <option value="">Vælg …</option>
                        @foreach ($views as $view)
                            <option value="{{ $view }}">{{ $view }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex-1 min-w-[12rem]">
                    <span class="block text-2xs text-gray-600 mb-1">Titel</span>
                    <input type="text" name="rows[{{ $next }}][title]" value="" placeholder="Søg" class="input-text" />
                </label>

                <label class="whitespace-nowrap">
                    <span class="block text-2xs text-gray-600 mb-1">Til&nbsp;/&nbsp;fra</span>
                    <input type="hidden" name="rows[{{ $next }}][enabled]" value="0" />
                    <input type="checkbox" name="rows[{{ $next }}][enabled]" value="1" checked />
                </label>

                <label class="whitespace-nowrap">
                    <span class="block text-2xs text-gray-600 mb-1">I sitemap</span>
                    <input type="hidden" name="rows[{{ $next }}][sitemap]" value="0" />
                    <input type="checkbox" name="rows[{{ $next }}][sitemap]" value="1" />
                </label>
            </div>

            <label class="block mt-3">
                <span class="block text-2xs text-gray-600 mb-1">Flere variabler — én <code>nøgle: værdi</code> pr. linje</span>
                <textarea name="rows[{{ $next }}][data]" rows="2" class="input-text font-mono text-2xs" placeholder="intro: Find det du leder efter"></textarea>
            </label>
        </div>

        <div class="p-4">
            <button type="submit" class="btn-primary">Gem</button>
            <p class="text-2xs text-gray-600 mt-2">
                En rute er en adresse og en skabelon — ingen side bagved, intet at redigere.
                Titel og variabler kan læses i skabelonen som <code>@{{ title }}</code> og så videre.
                En linje forsvinder ved at tømme adressen og gemme.
            </p>
            <p class="text-2xs text-gray-600 mt-2">
                Skal siden kunne redigeres, er det ikke en rute — så opret en almindelig side i stedet.
            </p>
        </div>
    </form>
</div>
