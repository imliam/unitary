<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Geo\Coordinate;
use Unitary\Geo\CoordinateFormat;
use Unitary\Geo\Distance;
use Unitary\Geo\DistanceMethod;
use Unitary\Units\Angle;
use Unitary\Units\Length;

it('parses coordinates in every common notation', function (string $input, float $latitude, float $longitude) {
    $coordinate = Coordinate::parse($input);

    expect($coordinate->latitude)->toEqualWithDelta($latitude, 0.00002)
        ->and($coordinate->longitude)->toEqualWithDelta($longitude, 0.00002);
})->with([
    ['51.5072, -0.1276', 51.5072, -0.1276],
    ['51.5072 -0.1276', 51.5072, -0.1276],
    ['51.5072;-0.1276', 51.5072, -0.1276],
    ['51.5072°N 0.1276°W', 51.5072, -0.1276],
    ['51.5072° N, 0.1276° W', 51.5072, -0.1276],
    ['N51.5072 W0.1276', 51.5072, -0.1276],
    ['0.1276°W 51.5072°N', 51.5072, -0.1276],
    ['51°30′26″N 0°7′39″W', 51.507222, -0.1275],
    ["51°30'26\"N 0°7'39\"W", 51.507222, -0.1275],
    ["51°30'26''N, 0°7'39''W", 51.507222, -0.1275],
    ['51° 30′ 26″ N, 0° 7′ 39″ W', 51.507222, -0.1275],
    ['51d 30m 26s N, 0d 7m 39s W', 51.507222, -0.1275],
    ['51 deg 30 min 26 sec N 0 deg 7 min 39 sec W', 51.507222, -0.1275],
    ['-51°30′26″, -0°7′39″', -51.507222, -0.1275],
    ['N 51 30 26 W 0 7 39', 51.507222, -0.1275],
    ['51 30 26 N 0 7 39 W', 51.507222, -0.1275],
    ['51°30.432′N 0°07.656′W', 51.5072, -0.1276],
    ['-33.8688, 151.2093', -33.8688, 151.2093],
    ['33°52′08″S 151°12′33″E', -33.868889, 151.209167],
    ['−33.8688, 151.2093', -33.8688, 151.2093],
]);

it('refuses text that is not a coordinate', function (string $input) {
    expect(Coordinate::tryParse($input))->toBeNull();
})->with([
    '5 ft 11 in', '£23', '23 GBP', '10:00', '#fff', 'red', "13' 11\"", '2 d 3 s', '1h 30m', '5 kmh',
    '51.5 0', '51 30 26 0 7 39', '91.0, 0.0', '0.0, 181.0', '51°N 0°N', '51°61′N 0°W', 'gcpvj0duq', '',
]);

it('formats as DMS, DDM and decimal', function () {
    $london = Coordinate::of(51.5072, -0.1276);

    expect($london->toDms())->toBe('51°30′26″N 0°07′39″W')
        ->and($london->toDms(2))->toBe('51°30′25.92″N 0°07′39.36″W')
        ->and($london->toDdm())->toBe('51°30.432′N 0°07.656′W')
        ->and($london->toDecimal())->toBe('51.5072, -0.1276')
        ->and($london->toDecimal(2))->toBe('51.51, -0.13')
        ->and(Coordinate::of(-0.00000001, 0)->toDecimal())->toBe('0, 0')
        ->and(Coordinate::of(10.99999999, 0)->toDms())->toBe('11°00′00″N 0°00′00″E');
});

it('converts to UTM using the published reference points', function () {
    // The equator at 0° lies 3° west of zone 31's central meridian.
    expect(Coordinate::of(0, 0)->toUtm())->toBe('31N 166021 0')
        ->and(Coordinate::of(0, 3)->toUtm())->toBe('31N 500000 0')
        ->and(Coordinate::of(-33.8688, 151.2093)->toUtm())->toStartWith('56H ');
});

it('applies the Norway and Svalbard UTM zone exceptions', function () {
    expect(Coordinate::of(60, 5)->toUtm())->toStartWith('32V ')
        ->and(Coordinate::of(78, 15)->toUtm())->toStartWith('33X ')
        ->and(Coordinate::of(78, 8)->toUtm())->toStartWith('31X ');
});

it('round-trips UTM and MGRS', function (float $latitude, float $longitude) {
    $coordinate = Coordinate::of($latitude, $longitude);
    $fromUtm = Coordinate::parse($coordinate->toUtm());
    $fromMgrs = Coordinate::parse($coordinate->toMgrs());

    expect($fromUtm->distanceTo($coordinate)->toFloat())->toBeLessThan(1.0)
        ->and($fromMgrs->distanceTo($coordinate)->toFloat())->toBeLessThan(1.5);
})->with([
    [51.5072, -0.1276],
    [-33.8688, 151.2093],
    [40.7484, -73.9857],
    [-54.8019, -68.3030],
    [64.1466, -21.9426],
    [0.0001, 0.0001],
    [-0.0001, 179.9],
    [83.9, 30.0],
    [-79.9, -120.0],
]);

it('formats MGRS like the NGA reference', function () {
    expect(Coordinate::of(0, 0)->toMgrs())->toBe('31N AA 66021 00000')
        ->and(Coordinate::of(0, 0)->toMgrs(2))->toBe('31N AA 66 00')
        ->and(Coordinate::of(0, 0)->toMgrs(0))->toBe('31N AA')
        ->and(Coordinate::parse('30UXC9931610164')->toMgrs())->toBe('30U XC 99316 10164')
        ->and(Coordinate::parse('30U XC 99316 10164')->toUtm())->toBe('30U 699316 5710164');
});

it('encodes geohashes', function () {
    // Wikipedia's worked example.
    expect(Coordinate::of(57.64911, 10.40744)->toGeohash(11))->toBe('u4pruydqqvj');

    $decoded = Coordinate::fromGeohash('u4pruydqqvj');

    expect($decoded->latitude)->toEqualWithDelta(57.64911, 0.000001)
        ->and($decoded->longitude)->toEqualWithDelta(10.40744, 0.000001)
        ->and((string) $decoded)->toBe('u4pruydqq')
        ->and(Coordinate::parse('geohash:u4pruydqqvj')->toGeohash(11))->toBe('u4pruydqqvj');
});

it('encodes plus codes from the Open Location Code test data', function (float $latitude, float $longitude, string $code) {
    expect(Coordinate::of($latitude, $longitude)->toPlusCode())->toBe($code);

    $decoded = Coordinate::fromPlusCode($code);

    expect($decoded->toPlusCode())->toBe($code)
        ->and(Coordinate::parse($code)->toPlusCode())->toBe($code);
})->with([
    [20.3700625, 2.7821875, '7FG49QCJ+2V'],
    [47.0000625, 8.0000625, '8FVC2222+22'],
    [-41.2730625, 174.7859375, '4VCPPQGP+Q9'],
]);

it('supports short padded and long grid plus codes', function () {
    $zurich = Coordinate::of(47.0000625, 8.0000625);

    expect($zurich->toPlusCode(4))->toBe('8FVC0000+')
        ->and($zurich->toPlusCode(8))->toBe('8FVC2222+')
        ->and(strlen($zurich->toPlusCode(15)))->toBe(16)
        ->and(Coordinate::fromPlusCode($zurich->toPlusCode(15))->distanceTo($zurich)->toFloat())->toBeLessThan(0.01);
});

it('rejects invalid plus codes and geohashes', function (Closure $parse) {
    expect($parse)->toThrow(ParseException::class);
})->with([
    fn () => Coordinate::fromPlusCode('ZZZZZZZZ+ZZ'),
    fn () => Coordinate::fromPlusCode('8FVC0000+22'),
    fn () => Coordinate::fromGeohash('abc!'),
    fn () => Coordinate::of(91, 0),
]);

it('measures geodesic distance with Vincenty', function () {
    // Vincenty (1975) / Geoscience Australia: Flinders Peak to Buninyong.
    $flindersPeak = Coordinate::parse('37°57′03.72030″S 144°25′29.52440″E');
    $buninyong = Coordinate::parse('37°39′10.15610″S 143°55′35.38390″E');

    $distance = $flindersPeak->distanceTo($buninyong);
    $bearing = $flindersPeak->bearingTo($buninyong);

    expect($distance->unit())->toBe(Length::Meter)
        ->and($distance->toFloat())->toEqualWithDelta(54972.271, 0.001)
        ->and($bearing->unit())->toBe(Angle::Degree)
        ->and($bearing->toFloat())->toEqualWithDelta(306 + 52 / 60 + 5.37 / 3600, 0.00001);
});

it('falls back to haversine for nearly antipodal points', function () {
    $distance = Coordinate::of(0, 0)->distanceTo(Coordinate::of(0.5, 179.7));

    expect($distance->toFloat())->toBeGreaterThan(19900000)->toBeLessThan(20100000);
});

it('measures zero distance between identical points', function () {
    expect(Coordinate::of(10, 10)->distanceTo(Coordinate::of(10, 10))->isZero())->toBeTrue();
});

it('offers a spherical approximation', function () {
    $london = Coordinate::of(51.5072, -0.1276);
    $paris = Coordinate::of(48.8566, 2.3522);

    $vincenty = $london->distanceTo($paris)->toFloat();
    $haversine = $london->distanceTo($paris, DistanceMethod::Haversine)->toFloat();

    expect(abs($vincenty - $haversine) / $vincenty)->toBeLessThan(0.005);
});

it('parses distances between two coordinates', function (string $input) {
    $expected = Coordinate::of(51.5072, -0.1276)->distanceTo(Coordinate::of(48.8566, 2.3522));

    expect(Distance::tryParse($input)?->isEqualTo($expected))->toBeTrue();
})->with([
    'distance from 51.5072,-0.1276 to 48.8566,2.3522',
    'the distance between 51.5072, -0.1276 and 48.8566, 2.3522',
    '51.5072,-0.1276 to 48.8566,2.3522',
    '51.5072°N 0.1276°W → 48.8566°N 2.3522°E',
    'distance 51.5072 -0.1276 -> 48.8566 2.3522',
]);

it('does not parse distances from non-coordinates', function (string $input) {
    expect(Distance::tryParse($input))->toBeNull();
})->with(['5 km to mi', '£23 to USD', 'distance from here to there', '51.5072,-0.1276']);

it('switches display format and resolves format names', function () {
    $london = Coordinate::of(51.5072, -0.1276);

    expect((string) $london)->toBe('51.5072, -0.1276')
        ->and((string) $london->in('degrees minutes seconds'))->toBe('51°30′26″N 0°07′39″W')
        ->and($london->in(CoordinateFormat::Utm)->format)->toBe(CoordinateFormat::Utm)
        ->and($london->to('plus code'))->toBe($london->toPlusCode())
        ->and($london->to('geo hash'))->toBe($london->toGeohash())
        ->and(CoordinateFormat::fromName('OLC'))->toBe(CoordinateFormat::PlusCode)
        ->and(CoordinateFormat::fromName('dd'))->toBe(CoordinateFormat::Decimal)
        ->and(CoordinateFormat::tryFromName('nonsense'))->toBeNull()
        ->and(json_encode($london))->toBe('{"latitude":51.5072,"longitude":-0.1276}');
});
