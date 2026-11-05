<?php

declare(strict_types=1);

namespace Unitary\Scales;

enum ShoeFit: string
{
    case Men = 'men';
    case Women = 'women';
    case Children = 'children';

    public static function tryFromName(string $name): ?self
    {
        return match (strtolower(str_replace(['’', "'"], '', trim($name)))) {
            'men', 'mens', 'man', 'male', 'gents', 'mans' => self::Men,
            'women', 'womens', 'woman', 'female', 'ladies', 'lady', 'womans' => self::Women,
            'kids', 'kid', 'child', 'children', 'childrens', 'youth', 'junior', 'infant' => self::Children,
            default => null,
        };
    }
}
