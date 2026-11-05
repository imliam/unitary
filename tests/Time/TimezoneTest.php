<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Time\Timezone;

it('resolves IANA identifiers case-insensitively', function (string $input, string $expected) {
    expect(Timezone::resolve($input)->getName())->toBe($expected);
})->with([
    ['Europe/London', 'Europe/London'],
    ['europe/london', 'Europe/London'],
    ['AMERICA/NEW_YORK', 'America/New_York'],
    ['America/Argentina/Buenos_Aires', 'America/Argentina/Buenos_Aires'],
    ['US/Eastern', 'US/Eastern'],
]);

it('resolves UTC aliases', function (string $input) {
    expect(Timezone::resolve($input)->getName())->toBe('UTC');
})->with(['UTC', 'utc', 'GMT', 'Z', 'UT']);

it('resolves offsets', function (string $input, string $expected) {
    expect(Timezone::resolve($input)->getName())->toBe($expected);
})->with([
    ['+05:30', '+05:30'],
    ['-0800', '-08:00'],
    ['+01', '+01:00'],
    ['UTC+1', '+01:00'],
    ['GMT-5', '-05:00'],
    ['UTC+05:30', '+05:30'],
    ['utc + 9', '+09:00'],
    ['UTC-3:30', '-03:30'],
]);

it('rejects out-of-range or ambiguous offsets', function (string $input) {
    expect(Timezone::tryResolve($input))->toBeNull();
})->with(['+15:00', 'UTC+3:75', '-5', '+1', '23']);

it('resolves abbreviations to DST-aware zones', function (string $input, string $expected) {
    expect(Timezone::resolve($input)->getName())->toBe($expected);
})->with([
    ['EST', 'America/New_York'],
    ['EDT', 'America/New_York'],
    ['est', 'America/New_York'],
    ['CST', 'America/Chicago'],
    ['MST', 'America/Denver'],
    ['PST', 'America/Los_Angeles'],
    ['PDT', 'America/Los_Angeles'],
    ['AKST', 'America/Anchorage'],
    ['HST', 'Pacific/Honolulu'],
    ['BST', 'Europe/London'],
    ['CET', 'Europe/Paris'],
    ['CEST', 'Europe/Paris'],
    ['EET', 'Europe/Athens'],
    ['IST', 'Asia/Kolkata'],
    ['JST', 'Asia/Tokyo'],
    ['KST', 'Asia/Seoul'],
    ['AEST', 'Australia/Sydney'],
    ['AEDT', 'Australia/Sydney'],
    ['NZST', 'Pacific/Auckland'],
    ['SGT', 'Asia/Singapore'],
    ['HKT', 'Asia/Hong_Kong'],
]);

it('only matches word-like abbreviations in upper case', function () {
    expect(Timezone::resolve('CAT')->getName())->toBe('Africa/Maputo')
        ->and(Timezone::tryResolve('cat'))->toBeNull()
        ->and(Timezone::tryResolve('art'))->toBeNull()
        ->and(Timezone::tryResolve('eat'))->toBeNull();
});

it('resolves city names from identifier segments', function (string $input, string $expected) {
    expect(Timezone::resolve($input)->getName())->toBe($expected);
})->with([
    ['Tokyo', 'Asia/Tokyo'],
    ['tokyo', 'Asia/Tokyo'],
    ['new york', 'America/New_York'],
    ['New_York', 'America/New_York'],
    ['Los Angeles', 'America/Los_Angeles'],
    ['Sao Paulo', 'America/Sao_Paulo'],
    ['São Paulo', 'America/Sao_Paulo'],
    ['London', 'Europe/London'],
    ['Paris', 'Europe/Paris'],
    ['Sydney', 'Australia/Sydney'],
    ['Buenos Aires', 'America/Argentina/Buenos_Aires'],
    ['port-au-prince', 'America/Port-au-Prince'],
    ['Hong Kong', 'Asia/Hong_Kong'],
]);

it('resolves curated cities and countries', function (string $input, string $expected) {
    expect(Timezone::resolve($input)->getName())->toBe($expected);
})->with([
    ['San Francisco', 'America/Los_Angeles'],
    ['Seattle', 'America/Los_Angeles'],
    ['Boston', 'America/New_York'],
    ['Washington DC', 'America/New_York'],
    ['Miami', 'America/New_York'],
    ['Dallas', 'America/Chicago'],
    ['Houston', 'America/Chicago'],
    ['Beijing', 'Asia/Shanghai'],
    ['Mumbai', 'Asia/Kolkata'],
    ['Delhi', 'Asia/Kolkata'],
    ['Japan', 'Asia/Tokyo'],
    ['India', 'Asia/Kolkata'],
    ['UK', 'Europe/London'],
    ['Germany', 'Europe/Berlin'],
]);

it('passes DateTimeZone instances through', function () {
    $zone = new DateTimeZone('Asia/Tokyo');

    expect(Timezone::resolve($zone))->toBe($zone);
});

it('throws for unknown zones', function (string $input) {
    expect(fn () => Timezone::resolve($input))->toThrow(ParseException::class);
})->with(['Narnia', '', 'GBP', 'km', '#fff', 'red']);

it('calculates the offset between zones at a given instant', function () {
    $summer = new DateTimeImmutable('2026-07-01 12:00', new DateTimeZone('UTC'));
    $winter = new DateTimeImmutable('2026-01-15 12:00', new DateTimeZone('UTC'));
    // 2026-03-15: US is on DST (from 8 March), UK is not (until 29 March).
    $gap = new DateTimeImmutable('2026-03-15 12:00', new DateTimeZone('UTC'));

    expect(Timezone::offsetBetween('Europe/London', 'America/New_York', $summer))->toBe(-5 * 3600)
        ->and(Timezone::offsetBetween('Europe/London', 'America/New_York', $winter))->toBe(-5 * 3600)
        ->and(Timezone::offsetBetween('Europe/London', 'America/New_York', $gap))->toBe(-4 * 3600)
        ->and(Timezone::offsetBetween('UTC', 'IST', $summer))->toBe(19800)
        ->and(Timezone::offsetBetween('Tokyo', 'UTC', $summer))->toBe(-9 * 3600);
});
