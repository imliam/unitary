<?php

declare(strict_types=1);

use Unitary\Cli\Application;
use Unitary\Cli\FileRateCache;
use Unitary\Converter;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\UnitRegistry;

/**
 * Runs the CLI against in-memory streams.
 *
 * @param  list<string>  $arguments
 * @return array{code: int, out: string, err: string}
 */
function cli(array $arguments, string $stdin = '', ?Converter $converter = null, ?Closure $evaluator = null, array $env = []): array
{
    $streams = [];

    foreach (['in', 'out', 'err'] as $name) {
        $stream = fopen('php://memory', 'w+');
        assert($stream !== false);
        $streams[$name] = $stream;
    }

    fwrite($streams['in'], $stdin);
    rewind($streams['in']);

    $converter ??= new Converter(UnitRegistry::default(), new FixedExchangeRates('USD', ['GBP' => '0.8', 'EUR' => '0.9', 'JPY' => '150']));
    $code = (new Application($converter, $env, $streams['in'], $evaluator))->run(['unitary', ...$arguments], $streams['out'], $streams['err']);

    rewind($streams['out']);
    rewind($streams['err']);

    return ['code' => $code, 'out' => (string) stream_get_contents($streams['out']), 'err' => (string) stream_get_contents($streams['err'])];
}

it('converts a quoted expression', function () {
    expect(cli(['£23 in USD']))->toBe(['code' => 0, 'out' => "\$28.75\n", 'err' => '']);
});

it('joins unquoted arguments into one expression', function () {
    expect(cli(['5', 'ft', '11', 'in', 'to', 'cm'])['out'])->toBe("180.34 cm\n")
        ->and(cli(['13\'', '11"', 'in', 'm'])['out'])->toBe("4.2418 m\n");
});

it('treats negative numbers as arguments, not options', function () {
    expect(cli(['-40', '°F', 'to', '°C'])['out'])->toBe("-40 °C\n");
});

it('applies precision and scientific notation', function () {
    expect(cli(['1 mile to km', '--precision=2'])['out'])->toBe("1.61 km\n")
        ->and(cli(['--precision', '1', '1000 mi to m'])['out'])->toBe("1,609,344.0 m\n")
        ->and(cli(['1000 mi to nm', '--scientific'])['out'])->toBe("1.609344e+15 nm\n")
        ->and(cli(['1000 mi to nm', '--scientific', '--precision=3'])['out'])->toBe("1.61e+15 nm\n")
        ->and(cli(['1000000 JPY in GBP', '--precision=0'])['out'])->toBe("£5,333\n");
});

it('prints JSON including the exchange rate used', function () {
    $json = json_decode(cli(['£23 in USD', '--json'])['out'], true);

    expect($json)->toMatchArray([
        'type' => 'quantity',
        'formatted' => '$28.75',
        'value' => '28.75',
        'unit' => 'USD',
        'dimension' => 'currency',
    ])->and($json['exchange_rate'])->toMatchArray(['from' => 'GBP', 'to' => 'USD', 'rate' => '1.25', 'source' => 'fixed rates']);
});

it('prints JSON for non-currency results without a rate', function () {
    expect(json_decode(cli(['5 km to m', '--json'])['out'], true))
        ->toBe(['type' => 'quantity', 'formatted' => '5,000 m', 'value' => '5000', 'unit' => 'm', 'unitName' => 'meter', 'dimension' => 'length'])
        ->and(json_decode(cli(['#ff0000', '--json'])['out'], true)['type'])->toBe('color');
});

it('converts colors and times', function () {
    expect(cli(['#ff0000 to hsl'])['out'])->toBe("hsl(0 100% 50%)\n")
        ->and(cli(['2026-10-01 10:00 Europe/London in America/New_York'])['out'])->toBe("2026-10-01T05:00:00-04:00[America/New_York]\n");
});

it('converts each stdin line when no expression is given', function () {
    $result = cli([], "100 km to m\n\nnonsense here\n#fff to rgb\n");

    expect($result['code'])->toBe(1)
        ->and($result['out'])->toBe("100,000 m\nrgb(255 255 255)\n")
        ->and($result['err'])->toContain('error: line 3: Unable to parse "nonsense here"');
});

it('writes JSON lines for stdin with --json', function () {
    $lines = explode("\n", trim(cli(['--json'], "1 km to m\n2 km to m\n")['out']));

    expect($lines)->toHaveCount(2)
        ->and(json_decode($lines[1], true)['value'])->toBe('2000');
});

it('suggests units for typos', function () {
    $result = cli(['5 kilometrs to mi']);

    expect($result['code'])->toBe(1)
        ->and($result['out'])->toBe('')
        ->and($result['err'])->toContain('error: Unable to parse "5 kilometrs to mi"')
        ->and($result['err'])->toContain('did you mean "kilometers"');
});

it('explains incompatible units', function () {
    expect(cli(['5 kg to m'])['err'])->toBe("error: Cannot convert kg (mass) to m (length)\n");
});

it('explains a missing exchange rate provider', function () {
    expect(cli(['£5 in USD'], converter: new Converter())['err'])->toContain('pass --rates=ecb');
});

it('rejects unknown options and providers', function () {
    expect(cli(['--bogus'])['err'])->toContain('Unknown option --bogus')
        ->and(cli(['--rates=bank-of-mum', '1 km to m'])['err'])->toContain('Unknown --rates provider "bank-of-mum"')
        ->and(cli(['--precision=two', '1 km'])['code'])->toBe(1);
});

it('requires an app id for Open Exchange Rates', function () {
    $app = new Application(null, [], null);
    $out = fopen('php://memory', 'w+');
    $err = fopen('php://memory', 'w+');
    assert($out !== false && $err !== false);

    expect($app->run(['unitary', '--rates=openexchangerates', '£5 in USD'], $out, $err))->toBe(1);

    rewind($err);
    expect(stream_get_contents($err))->toContain('OPENEXCHANGERATES_APP_ID');
});

it('lists units for a dimension', function () {
    $out = cli(['units', 'temperature'])['out'];

    expect($out)->toStartWith("Temperature (8)\n")
        ->and($out)->toContain('°C   celsius')
        ->and($out)->toContain('degrees celsius')
        ->and(cli(['units', 'Digital Storage'])['out'])->toStartWith('Digital storage')
        ->and(cli(['units', 'nonsense'])['err'])->toContain('Unknown dimension "nonsense"');
});

it('lists units as JSON', function () {
    $json = json_decode(cli(['units', 'length', '--json'])['out'], true);

    expect($json[0]['dimension'])->toBe('length')
        ->and($json[0]['units'][0])->toMatchArray(['symbol' => 'm', 'name' => 'meter']);
});

it('lists dimensions', function () {
    expect(cli(['dimensions'])['out'])->toMatch('/^length\s+\d+ units$/m')->not->toMatch('/^derived/m');
});

it('evaluates a sheet with aligned results', function () {
    $result = cli(['eval', '-'], "# Trip\n100 km to mi\n£20 in USD\n\n5 ft 11 in in cm\nwhat\n");

    expect($result['code'])->toBe(1)
        ->and($result['out'])->toBe(implode("\n", [
            '# Trip',
            '100 km to mi       62.137119223733396962 mi',
            '£20 in USD         $25.00',
            '',
            '5 ft 11 in in cm   180.34 cm',
            'what               ⚠ Unable to parse "what" as a quantity, color, time, coordinate or scale',
        ])."\n");
});

it('evaluates a sheet from a file through a custom evaluator', function () {
    $file = tempnam(sys_get_temp_dir(), 'unitary');
    file_put_contents($file, "1 km\n2 km\n");

    $result = cli(['eval', $file, '--json'], evaluator: fn (string $line) => Unitary\Quantity::parse($line)->multipliedBy(10));
    unlink($file);

    expect(array_column(array_column(json_decode($result['out'], true), 'result'), 'value'))->toBe(['10', '20']);
});

it('reports unreadable sheet files', function () {
    expect(cli(['eval', '/no/such/file'])['err'])->toContain('Cannot read /no/such/file');
});

it('shows help and version', function () {
    expect(cli(['--help'])['out'])->toContain('Usage:')
        ->and(cli(['help'])['code'])->toBe(0)
        ->and(cli(['-V'])['out'])->toStartWith('unitary ');
});

it('never colors output for non-terminals', function () {
    expect(cli(['1 km to m'])['out'])->not->toContain("\033[");
});

it('caches rate tables on disk', function () {
    $directory = sys_get_temp_dir().'/unitary-test-'.uniqid();
    $cache = new FileRateCache($directory);

    $cache->set('ecb', ['base' => 'EUR'], 60);
    $cache->set('stale', ['base' => 'EUR'], -1);

    expect($cache->get('ecb'))->toBe(['base' => 'EUR'])
        ->and($cache->get('stale'))->toBeNull()
        ->and($cache->get('missing'))->toBeNull()
        ->and((new FileRateCache($directory))->get('ecb'))->toBe(['base' => 'EUR']);

    array_map('unlink', glob($directory.'/*') ?: []);
    rmdir($directory);
});

it('groups digits by default, with --plain for raw output and --locale for other conventions', function () {
    expect(cli(['1000000000 km/s'])['out'])->toBe("1,000,000,000 km/s\n")
        ->and(cli(['1000000000 km/s', '--plain'])['out'])->toBe("1000000000 km/s\n")
        ->and(cli(['1234567.891 m', '--locale=de'])['out'])->toBe("1.234.567,891 m\n")
        ->and(cli(['1234567.891 m'], env: ['LANG' => 'de_DE.UTF-8'])['out'])->toBe("1.234.567,891 m\n")
        ->and(cli(['1234567.891 m'], env: ['LANG' => 'C'])['out'])->toBe("1,234,567.891 m\n");
});

it('checks whether input is understood', function () {
    expect(cli(['check', '5 ft 11 in']))->toMatchArray(['code' => 0, 'out' => "yes\n"])
        ->and(cli(['check', '5 florbs']))->toMatchArray(['code' => 1, 'out' => "no\n"])
        ->and(cli(['check', '-q', 'asdf']))->toMatchArray(['code' => 1, 'out' => ''])
        ->and(cli(['check', '--json', '£23 in USD'])['out'])->toBe('{"input":"£23 in USD","understood":true}'."\n");
});

it('picks units for a region with --usage', function () {
    expect(cli(['80', 'km', '--usage=road', '--region=GB', '--precision=0'])['out'])->toBe("50 mi\n")
        ->and(cli(['80 km', '--usage', 'road'], env: ['LANG' => 'sv_SE.UTF-8'])['out'])->toBe("8 scand mil\n")
        ->and(cli(['180 cm', '--usage=person-height', '--region=US'])['out'])->toBe("5 ft 10.87 in\n")
        ->and(cli(['80 km', '--usage=road'])['out'])->toBe("80 km\n")
        ->and(cli(['1013 hPa', '--usage=barometric', '--region=GB'])['out'])->toBe("1,013 mbar\n");
});

it('describes a mixed-unit result as JSON', function () {
    $json = json_decode(cli(['180 cm', '--usage=person-height', '--region=US', '--json'])['out'], true);

    expect($json['type'])->toBe('breakdown')
        ->and($json['formatted'])->toBe('5 ft 10.87 in')
        ->and(array_column($json['parts'], 'unit'))->toBe(['ft', 'in']);
});

it('lists usages', function () {
    expect(cli(['usages', 'length'])['out'])->toBe("length  default, focal-length, person, person-height, rainfall, road, snowfall, vehicle, visiblty\n")
        ->and(cli(['usages'])['out'])->toContain('temperature  ')->toContain('blood-glucose');
});

it('writes units narrow, short or long with --display', function () {
    expect(cli(['2 in', '--display=long', '--locale=fr_FR'])['out'])->toBe("2\u{00A0}pouces\n")
        ->and(cli(['2 in', '--display', 'narrow'])['out'])->toBe("2″\n")
        ->and(cli(['180 cm', '--usage=person-height', '--region=US', '--display=long', '--precision=0'])['out'])->toBe("5 feet 11 inches\n")
        ->and(cli(['2 in', '--display=wide']))->toMatchArray(['code' => 1, 'out' => ''])
        ->and(cli(['2 in', '--display=wide'])['err'])->toContain('--display must be narrow, short or long');
});

