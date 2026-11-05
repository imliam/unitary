<?php

declare(strict_types=1);

namespace Unitary\Time;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use JsonSerializable;
use Stringable;
use Unitary\Exceptions\ParseException;

/**
 * An instant in time viewed from a particular timezone.
 */
final readonly class ZonedTime implements JsonSerializable, Stringable
{
    /**
     * Inputs must contain at least one of these to be treated as a time, so things
     * like "23 GBP" or "5 km" are never mistaken for one.
     */
    private const TIME_LIKE = '/
        \d{1,2}:\d{2}
        | \b\d{1,2}\s*(?:am|pm|a\.m\.|p\.m\.)(?![a-z])
        | \d{4}-\d{2}-\d{2}
        | \b\d{1,2}\/\d{1,2}\/\d{2,4}\b
        | ^@\d+$
        | \b(?:now|today|tomorrow|yesterday|noon|midday|midnight)\b
        | \b(?:mon|tue|tues|wed|thu|thur|thurs|fri|sat|sun)(?:day|nesday|sday|rsday|urday)?\b
        | \b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s+\d{1,2}\b
        | \b\d{1,2}(?:st|nd|rd|th)?\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)
    /xi';

    private function __construct(private DateTimeImmutable $dateTime)
    {
    }

    /**
     * Strings are read as wall-clock time in $zone (unless they carry their own offset).
     * DateTime objects keep their instant and are moved into $zone.
     *
     * @throws ParseException
     */
    public static function of(DateTimeInterface|string $time, DateTimeZone|string|null $zone = null): self
    {
        $zone = $zone === null ? null : Timezone::resolve($zone);

        if ($time instanceof DateTimeInterface) {
            $dateTime = DateTimeImmutable::createFromInterface($time);

            return new self($zone === null ? $dateTime : $dateTime->setTimezone($zone));
        }

        if ($zone === null) {
            return self::parse($time);
        }

        return self::create($time, $zone) ?? throw ParseException::cannotParse($time, 'time');
    }

    /**
     * Parses freeform input such as "10am EST", "2026-10-01 10:00 Europe/London",
     * "tomorrow 9am New York", "14:00 UTC+2" or just "Tokyo" (now, in Tokyo).
     *
     * @throws ParseException
     */
    public static function parse(string $input): self
    {
        return self::tryParse($input) ?? throw ParseException::cannotParse($input, 'time');
    }

    public static function tryParse(string $input): ?self
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        $zone = Timezone::tryResolve($input);

        if ($zone !== null) {
            return new self(new DateTimeImmutable('now', $zone));
        }

        $tokens = preg_split('/\s+/', $input) ?: [];
        $count = count($tokens);

        // Longest zone first, so "9am New York" picks "New York" rather than "York".
        for ($length = $count - 1; $length >= 1; $length--) {
            $candidates = [
                [implode(' ', array_slice($tokens, $count - $length)), implode(' ', array_slice($tokens, 0, $count - $length))],
                [implode(' ', array_slice($tokens, 0, $length)), implode(' ', array_slice($tokens, $length))],
            ];

            foreach ($candidates as [$zoneText, $timeText]) {
                $zone = Timezone::tryResolve($zoneText);

                if ($zone === null) {
                    continue;
                }

                $timeText = (string) preg_replace('/^(?:in|at)\s+|\s+(?:in|at)$/i', '', $timeText);
                $time = self::create($timeText, $zone);

                if ($time !== null) {
                    return $time;
                }
            }
        }

        return self::create($input, new DateTimeZone(date_default_timezone_get()), moveToZone: false);
    }

    /**
     * @throws ParseException
     */
    public function to(DateTimeZone|string $zone): self
    {
        return new self($this->dateTime->setTimezone(Timezone::resolve($zone)));
    }

    public function dateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function zone(): DateTimeZone
    {
        return $this->dateTime->getTimezone();
    }

    public function timestamp(): int
    {
        return $this->dateTime->getTimestamp();
    }

    /**
     * The UTC offset in effect at this instant, e.g. "+01:00".
     */
    public function offset(): string
    {
        return $this->dateTime->format('P');
    }

    /**
     * The zone abbreviation in effect at this instant, e.g. "BST". Zones without an
     * abbreviation return their offset.
     */
    public function abbreviation(): string
    {
        return $this->dateTime->format('T');
    }

    public function format(string $format = 'Y-m-d H:i:s T'): string
    {
        return $this->dateTime->format($format);
    }

    /**
     * ISO 8601 with the zone name appended, e.g. "2026-10-01T10:00:00+01:00[Europe/London]".
     */
    public function toString(): string
    {
        $iso = $this->dateTime->format(DateTimeInterface::ATOM);
        $name = $this->zone()->getName();

        return $name === $this->offset() ? $iso : "{$iso}[{$name}]";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{datetime: string, timezone: string, offset: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'datetime' => $this->dateTime->format(DateTimeInterface::ATOM),
            'timezone' => $this->zone()->getName(),
            'offset' => $this->offset(),
        ];
    }

    /**
     * Reads $time as wall-clock time in $zone. If $time carries its own offset, the result
     * is moved into $zone when $moveToZone is set, otherwise it keeps that offset.
     */
    private static function create(string $time, DateTimeZone $zone, bool $moveToZone = true): ?self
    {
        if (preg_match(self::TIME_LIKE, $time) !== 1) {
            return null;
        }

        try {
            $dateTime = new DateTimeImmutable($time, $zone);
        } catch (Exception) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return new self($moveToZone ? $dateTime->setTimezone($zone) : $dateTime);
    }
}
