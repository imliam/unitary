# Unitary roadmap

> **Status (2026-10-01):** all 15 items are built and tested. MCP was dropped in favor of the CLI. Clothing sizes and JP ring sizes were skipped because there's no published standard mapping.

The 15 ideas from the brainstorm, in build order. Most of them depend on the
core engine (phase 1), so that comes first. The self-contained modules are
built alongside it.

The primary audience is Laravel/PHP app developers. The headline feature is
static unit checking via PHPStan.

## Phase 1: core engine (sequential, everything else builds on it)

| # | Feature | Shape |
|---|---|---|
| 1 | **Dimensional algebra** | Each `Dimension` maps to an exponent vector over the SI base dimensions (plus currency, information and angle). A `CompoundUnit` is built from units raised to powers. `$a->multipliedBy($b)` / `dividedBy()` on quantities give derived quantities (`km ÷ h → km/h`). The parser reads `kg·m/s²`, `N m`, `W/m²`. Results simplify to named derived units where one exists (`J/s → W`). |
| 2 | **Rates** | Falls out of #1: currency ÷ time, count ÷ time. Handles `$120/hr × 42 hr`, `$20/day + $300/week in /month`, `€30/day in €/month`. |
| 3 | **Breakdown and humanize** | `->breakdown(['ft', 'in'])` gives `6 ft 0 in`, and `->breakdown()` on a duration gives `1 d 3 h 46 min 40 s`. `->humanize()` picks a sensible unit (`20 µm`, `1.2 GB`). `->toMetric()` and `->toImperial()` switch unit system. |
| 4 | **Uncertainty and significant figures** | `5.0 ± 0.1 m`, propagated linearly through arithmetic. Significant figures come from the input text (`2.5 kg` has 2) and are respected when formatting. |

## Phase 2: new kinds of unit

| # | Feature | Shape |
|---|---|---|
| 8 | **Logarithmic units** | A new kind of unit definition alongside linear, affine and reciprocal. Covers dB (power and field), dBm, dBW, nepers, bels, octaves, pH and stellar magnitude. |
| 7 | **Lookup and formula scales** | Shoe sizes (UK/US/EU/Mondopoint, by formula), ring sizes, ISO 216 paper sizes, AWG wire gauge, oven gas marks, Beaufort, and clothing sizes where a standard exists. |
| 9 | **Coordinates** | Parse and format decimal degrees, DMS, DDM, UTM, MGRS, geohash and Open Location Code. Distances use Vincenty or haversine (`distance('London', 'Paris')` takes coordinates, not names). |
| 6 | **Substance-aware conversion** | `1 cup of flour in g` uses ingredient densities, `1 gal of gasoline in kWh` uses energy densities, and `1 gal of gasoline in kg CO2` uses emission factors. Bundled tables cite their sources. |
| 14 | **Novelty units** | Bananas, football pitches, double-decker buses, Olympic pools, blue whales and so on. Also a `BigMacIndex` exchange rate provider using The Economist's open data. |

## Phase 3: data

| # | Feature | Shape |
|---|---|---|
| 5 | **Historical FX and inflation** | `->to('USD', at: $date)` through a `HistoricalExchangeRateProvider` (Frankfurter and ECB history). Inflation adjustment covers `£100 in 1970 in today's money` and `->adjustForInflation(from: 1970, to: 2026)`, using official CPI series (BLS, ONS). Bundled CPI data cites its source and comes with an update script. |

## Phase 4: input languages

| # | Feature | Shape |
|---|---|---|
| 15 | **Number words** | `two and a half miles`, `a dozen eggs`, `twenty-three quid`, `1.2 million`, Roman numerals. English first. |
| 13 | **Calculator language** | `Unitary::evaluate('(5 ft + 30 cm) × 2 in m')`, percentages (`of`, `on`, `off`, `as %`, `is what % of`), and multi-line sheets with variables (`rate = $120/hr`, then `42 hr × rate`). |

## Phase 5: integrations

| # | Feature | Shape |
|---|---|---|
| 11 | **Laravel** | In-package and auto-discovered. Includes a `QuantityCast` (stores the base-unit value so columns sort, plus the original unit), validation rules (`quantity:mass,max:30kg`), a Blade directive and config for the default exchange rate provider. |
| 12 | **CLI** | `vendor/bin/unitary "£23 in USD"`, `--json` output for scripts and agents, `unitary units length` to list units, and `unitary eval` for calculator sheets. |
| 10 | **PHPStan extension** | `Quantity<Length>` generics. String literals are parsed during analysis (`convert('5 kg')->to('m')` is an error), and type-level unit maths gives `Quantity<Length> ÷ Quantity<Time> = Quantity<Speed>`. Ships `extension.neon` for phpstan/extension-installer. |

## Defaults chosen (change any of these)

- Laravel, PHPStan and the CLI all ship in this package. Optional dependencies go in `suggest`, so the core stays dependency-free apart from brick/math.
- Bundled datasets (CPI, densities, emission factors) must cite an official source and come with a script to refresh them. Nothing is made up.
- Size scales follow published standards and formulas, not individual retailers' tables.
- Novelty units are registered by default, but rank below real units when text is ambiguous.
