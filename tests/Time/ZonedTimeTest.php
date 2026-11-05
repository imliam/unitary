<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Time\ZonedTime;

it('creates from a string and zone', function () {
    $time = ZonedTime::of('2026-10-01 10:00', 'Europe/London');

    expect($time->format('Y-m-d H:i'))->toBe('2026-10-01 10:00')
        ->and($time->zone()->getName())->toBe('Europe/London')
        ->and($time->offset())->toBe('+01:00')
        ->and($time->abbreviation())->toBe('BST');
});

it('keeps the instant of DateTime objects and moves them into the zone', function () {
    $utc = new DateTime('2026-10-01 09:00', new DateTimeZone('UTC'));

    $time = ZonedTime::of($utc, 'Asia/Tokyo');

    expect($time->format('H:i'))->toBe('18:00')
        ->and($time->timestamp())->toBe($utc->getTimestamp())
        ->and(ZonedTime::of($utc)->zone()->getName())->toBe('UTC');
});

it('converts between zones', function () {
    $time = ZonedTime::of('2026-10-01 10:00', 'Europe/London')->to('America/New_York');

    expect($time->format('Y-m-d H:i T'))->toBe('2026-10-01 05:00 EDT');
});

it('converts across the date line', function () {
    expect(ZonedTime::of('2026-10-01 22:00', 'UTC')->to('Pacific/Auckland')->format('Y-m-d H:i'))
        ->toBe('2026-10-02 11:00');
});

it('handles the London DST switch on 2026-03-29', function () {
    $before = ZonedTime::of('2026-03-29 00:30', 'UTC')->to('London');
    $after = ZonedTime::of('2026-03-29 01:30', 'UTC')->to('London');

    expect($before->format('H:i T'))->toBe('00:30 GMT')
        ->and($after->format('H:i T'))->toBe('02:30 BST');
});

it('handles the US DST switch on 2026-03-08', function () {
    $before = ZonedTime::of('2026-03-08 01:30', 'EST')->to('UTC');
    $after = ZonedTime::of('2026-03-08 03:30', 'EST')->to('UTC');

    expect($before->format('H:i'))->toBe('06:30')
        ->and($after->format('H:i'))->toBe('07:30');
});

it('applies DST to abbreviation-based zones', function () {
    // "EST" in summer still means New York time, i.e. EDT (-04:00).
    expect(ZonedTime::of('2026-07-01 10:00', 'EST')->offset())->toBe('-04:00')
        ->and(ZonedTime::of('2026-01-01 10:00', 'EST')->offset())->toBe('-05:00');
});

it('parses freeform input', function (string $input, string $expected) {
    expect(ZonedTime::parse($input)->format('Y-m-d H:i e'))->toBe($expected);
})->with([
    ['2026-10-01 10:00 Europe/London', '2026-10-01 10:00 Europe/London'],
    ['Europe/London 2026-10-01 10:00', '2026-10-01 10:00 Europe/London'],
    ['2026-10-01 14:00 UTC+2', '2026-10-01 14:00 +02:00'],
    ['2026-10-01 9:30 pm BST', '2026-10-01 21:30 Europe/London'],
    ['2026-10-01 10am PST', '2026-10-01 10:00 America/Los_Angeles'],
    ['2026-10-01 10:00 New York', '2026-10-01 10:00 America/New_York'],
    ['2026-10-01 10:00 in Tokyo', '2026-10-01 10:00 Asia/Tokyo'],
    ['1 Oct 2026 10:00 Sao Paulo', '2026-10-01 10:00 America/Sao_Paulo'],
    ['2026-10-01T10:00:00+01:00', '2026-10-01 10:00 +01:00'],
    ['2026-10-01T10:00:00+01:00 Tokyo', '2026-10-01 18:00 Asia/Tokyo'],
]);

it('parses relative times in a zone', function () {
    $tokyo = new DateTimeZone('Asia/Tokyo');
    $tomorrow = (new DateTimeImmutable('tomorrow 9am', $tokyo))->format('Y-m-d H:i');

    expect(ZonedTime::parse('tomorrow 9am Tokyo')->format('Y-m-d H:i'))->toBe($tomorrow)
        ->and(ZonedTime::parse('tomorrow 9am New York')->zone()->getName())->toBe('America/New_York')
        ->and(ZonedTime::parse('10am EST')->format('H:i e'))->toBe('10:00 America/New_York');
});

it('treats a bare zone as now in that zone', function () {
    $time = ZonedTime::parse('Tokyo');

    expect($time->zone()->getName())->toBe('Asia/Tokyo')
        ->and(abs($time->timestamp() - time()))->toBeLessThan(5)
        ->and(ZonedTime::parse('now Tokyo')->zone()->getName())->toBe('Asia/Tokyo');
});

it('refuses inputs that are not times', function (string $input) {
    expect(ZonedTime::tryParse($input))->toBeNull()
        ->and(fn () => ZonedTime::parse($input))->toThrow(ParseException::class);
})->with(['23', '23 GBP', '5 km', '#fff', 'red', '10 EST', '13\' 11"', '£23 in USD', '2026-02-30 10:00 UTC', '']);

it('throws for unknown target zones', function () {
    expect(fn () => ZonedTime::of('2026-10-01 10:00', 'UTC')->to('Narnia'))->toThrow(ParseException::class)
        ->and(fn () => ZonedTime::of('2026-10-01 10:00', 'Narnia'))->toThrow(ParseException::class);
});

it('formats as ISO 8601 with the zone name', function () {
    expect(ZonedTime::of('2026-10-01 10:00', 'Europe/London')->toString())
        ->toBe('2026-10-01T10:00:00+01:00[Europe/London]')
        ->and((string) ZonedTime::of('2026-10-01 10:00', 'UTC'))->toBe('2026-10-01T10:00:00+00:00[UTC]')
        ->and((string) ZonedTime::of('2026-10-01 10:00', '+05:30'))->toBe('2026-10-01T10:00:00+05:30');
});

it('serialises to JSON', function () {
    expect(json_encode(ZonedTime::of('2026-10-01 10:00', 'Europe/London')))
        ->toBe('{"datetime":"2026-10-01T10:00:00+01:00","timezone":"Europe\/London","offset":"+01:00"}');
});

it('accepts a custom format', function () {
    expect(ZonedTime::of('2026-10-01 10:00', 'JST')->format('D H:i T'))->toBe('Thu 10:00 JST')
        ->and(ZonedTime::of('2026-10-01 10:00', 'JST')->format())->toBe('2026-10-01 10:00:00 JST');
});
