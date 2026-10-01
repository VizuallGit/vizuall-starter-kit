{{--
    Omdirigeringer — en tabel og en 404-log.

    Bevidst bar: almindelige felter og almindelige form-posts, ingen JS. Siden
    rendres som et HTML-fragment ind i Statamics `utilities/Show`, så den arver
    kontrolpanelets egen CSS. Klasserne nedenfor er Statamics egne (card, btn,
    input-text) — vil I have den til at se anderledes ud, er det her I gør det.
--}}
<div class="card p-0 overflow-hidden">
    <form method="POST" action="{{ cp_route('utilities.redirects.save') }}">
        @csrf

        <table class="data-table">
            <thead>
                <tr>
                    <th>Fra</th>
                    <th>Til</th>
                    <th>Type</th>
                    <th>Til&nbsp;/&nbsp;fra</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    <tr>
                        <td>
                            <input type="text" name="rows[{{ $i }}][from]" value="{{ $row['from'] }}" class="input-text" />
                        </td>
                        <td>
                            <input type="text" name="rows[{{ $i }}][to]" value="{{ $row['to'] }}" class="input-text" />
                        </td>
                        <td>
                            <select name="rows[{{ $i }}][status]" class="input-text">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($row['status'] === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="hidden" name="rows[{{ $i }}][enabled]" value="0" />
                            <input type="checkbox" name="rows[{{ $i }}][enabled]" value="1" @checked($row['enabled']) />
                        </td>
                    </tr>
                @endforeach

                {{-- Tomme linjer i bunden: udfyld en, gem, og der kommer nye. --}}
                @foreach (range(count($rows), count($rows) + 2) as $i)
                    <tr>
                        <td><input type="text" name="rows[{{ $i }}][from]" value="" placeholder="/gammel-side" class="input-text" /></td>
                        <td><input type="text" name="rows[{{ $i }}][to]" value="" placeholder="/ny-side" class="input-text" /></td>
                        <td>
                            <select name="rows[{{ $i }}][status]" class="input-text">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($status === 301)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="hidden" name="rows[{{ $i }}][enabled]" value="0" />
                            <input type="checkbox" name="rows[{{ $i }}][enabled]" value="1" checked />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="p-4">
            <button type="submit" class="btn-primary">Gem</button>
            <p class="text-2xs text-gray-600 mt-2">
                En linje forsvinder ved at tømme den og gemme. Både Fra og Til må ende på
                <code>/*</code> — så følger resten af adressen med over.
                Rækkefølgen er prioriteten: den første der passer, vinder.
            </p>
        </div>
    </form>
</div>

<h2 class="mt-8 mb-2">Adresser der gav 404</h2>

@if (empty($missing))
    <div class="card p-4 text-gray-600">Ingen endnu. Her lander de adresser folk beder om, og ikke får.</div>
@else
    <div class="card p-0 overflow-hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Adresse</th>
                    <th>Gange</th>
                    <th>Sidst</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($missing as $miss)
                    <tr>
                        <td><code>{{ $miss['path'] }}</code></td>
                        <td>{{ $miss['hits'] }}</td>
                        <td>{{ $miss['last_seen'] ? \Illuminate\Support\Carbon::parse($miss['last_seen'])->diffForHumans() : '' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <form method="POST" action="{{ cp_route('utilities.redirects.promote') }}" class="inline">
                                @csrf
                                <input type="hidden" name="path" value="{{ $miss['path'] }}" />
                                <button type="submit" class="btn-xs">Lav en omdirigering</button>
                            </form>
                            <form method="POST" action="{{ cp_route('utilities.redirects.forget') }}" class="inline">
                                @csrf
                                <input type="hidden" name="path" value="{{ $miss['path'] }}" />
                                <button type="submit" class="btn-xs">Glem</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="p-4">
            <form method="POST" action="{{ cp_route('utilities.redirects.forget') }}">
                @csrf
                <input type="hidden" name="all" value="1" />
                <button type="submit" class="btn">Tøm listen</button>
            </form>
        </div>
    </div>
@endif
