<?php

declare(strict_types=1);

use Unitary\Quantity;
use Unitary\Units\Length;
use Unitary\Units\Mass;
use Unitary\Units\Time;

use function PHPStan\dumpType;
use function Unitary\convert;

dumpType(convert('5 kg'));
dumpType(convert('5 ft 11 in'));
dumpType(convert('£23 in USD'));
dumpType(convert(23, 'gbp'));
dumpType(convert('#ff0000'));
dumpType(convert('10:00', 'Europe/London'));
dumpType(Quantity::of(5, Length::Meter)->to('ft'));
dumpType(Quantity::of(90, 'km')->dividedBy(Quantity::of(3, Time::Hour)));
dumpType(Quantity::of(3, 'm')->multipliedBy(Quantity::of(4, 'ft')));
dumpType(Quantity::of(3, 'm')->power(3));
dumpType(Quantity::of(100, 'km/h')->simplify());
dumpType(Quantity::parse('2 lb 3 oz'));

convert('5 florbs');
Quantity::of(5, 'blorps');
convert('5 kg to m');

$length = Quantity::of(5, Length::Meter);
$mass = Quantity::of(5, Mass::Kilogram);
$length->plus($mass);
$length->to('kg');
$length->to('parsecs');
$length->isGreaterThan(convert('3 s'));
$length->plus(convert('3 ft'));
$length->to(Length::Mile);
$length->to('florbs');

dumpType(Quantity::of(30, 'degC')->minus(Quantity::of(10, 'degC')));
dumpType(Quantity::of(5, 'delta C')->plus(Quantity::of(20, 'degF')));
dumpType(Quantity::of(20, 'delta C')->to('degF'));
dumpType(Quantity::of(20, 'degC')->plus(Quantity::of(5, 'degF')));
