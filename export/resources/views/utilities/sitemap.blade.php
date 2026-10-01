{{--
    Sitemap — hvad søgemaskinerne får at vide, og hvad der holdes ude.

    Ingen knapper: sitemappen bygges på hver hentning, så der er intet at
    opdatere. Skærmen er til at kontrollere, ikke til at redigere.
--}}
<div class="card p-4 mb-4">
    <p class="text-2xs text-gray-600">
        Sitemappen bygges hver gang den hentes, så den er aldrig forældet.
        <a href="{{ $xml }}" target="_blank" rel="noopener"><code>{{ $xml }}</code></a>
        &middot;
        <a href="{{ $robots }}" target="_blank" rel="noopener"><code>{{ $robots }}</code></a>
    </p>
</div>

<div class="card p-4 mb-4">
    <form method="POST" action="{{ cp_route('utilities.sitemap.save') }}">
        @csrf
        <p class="text-2xs text-gray-600 mb-1">Må søgemaskiner finde sitet?</p>

        <label class="block mb-1">
            <input type="radio" name="search_engines" value="auto" @checked($search_engines === 'auto') />
            Følg miljøet — ja i produktion, nej alle andre steder
            <span class="text-gray-600">(sitet kører som <code>{{ $environment }}</code> lige nu)</span>
        </label>
        <label class="block mb-1">
            <input type="radio" name="search_engines" value="on" @checked($search_engines === 'on') />
            Ja, altid
        </label>
        <label class="block mb-3">
            <input type="radio" name="search_engines" value="off" @checked($search_engines === 'off') />
            Nej, aldrig — sitet er live, men skal ikke findes endnu
        </label>

        <p class="text-2xs mb-4 {{ $indexable ? 'text-gray-600' : 'text-red-500' }}">
            @if ($indexable)
                Lige nu: sitet må findes. Enkelte sider kan stadig holdes ude på deres SEO-fane.
            @else
                Lige nu: <strong>hele sitet er holdt ude.</strong> Hver side får <code>noindex</code>, og robots.txt lukker helt af.
            @endif
        </p>

        <p class="text-2xs text-gray-600 mb-2">Hvilke samlinger må komme i sitemappen?</p>

        @foreach ($collections as $handle => $label)
            <label class="inline-block mr-4 mb-1">
                <input type="checkbox" name="exclude[]" value="{{ $handle }}" @checked(in_array($handle, $excluded_collections, true)) />
                Hold <strong>{{ $label }}</strong> ude
            </label>
        @endforeach

        <div class="mt-3"><button type="submit" class="btn">Gem</button></div>
    </form>
</div>

<h2 class="mb-2">Med i sitemappen <span class="text-gray-600">({{ count($included) }})</span></h2>

@if (empty($included))
    <div class="card p-4 text-gray-600">Ingen adresser endnu.</div>
@else
    <div class="card p-0 overflow-hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Adresse</th>
                    <th>Titel</th>
                    <th>Hvorfra</th>
                    <th>Sidst ændret</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($included as $row)
                    <tr>
                        <td><a href="{{ $row['url'] }}" target="_blank" rel="noopener"><code>{{ $row['url'] }}</code></a></td>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-gray-600">{{ $row['source'] }}</td>
                        <td class="text-gray-600">
                            {{ $row['lastmod'] ? \Illuminate\Support\Carbon::parse($row['lastmod'])->diffForHumans() : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2 class="mt-8 mb-2">Holdt ude <span class="text-gray-600">({{ count($excluded) }})</span></h2>

@if (empty($excluded))
    <div class="card p-4 text-gray-600">Ingenting holdes ude.</div>
@else
    <div class="card p-0 overflow-hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Adresse</th>
                    <th>Titel</th>
                    <th>Hvorfra</th>
                    <th>Hvorfor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($excluded as $row)
                    <tr>
                        <td><code>{{ $row['url'] }}</code></td>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-gray-600">{{ $row['source'] }}</td>
                        <td>{{ $row['reason'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-2xs text-gray-600 mt-2">
        En side holdes ude ved at sætte «Skjul for søgemaskiner» på dens SEO-fane.
        En rute kommer med, når dens sitemap-flueben er sat.
    </p>
@endif
