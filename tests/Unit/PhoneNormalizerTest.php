<?php

namespace Tests\Unit;

use App\Domain\Leads\Support\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function numbers(): array
    {
        return [
            'already E.164' => ['+21623173698', '+21623173698'],
            'local 8 digits' => ['56351445', '+21656351445'],
            'spaces and dots' => ['+216 23.173.698', '+21623173698'],
            '00 prefix' => ['0021623173698', '+21623173698'],
            'country code without +' => ['21623173698', '+21623173698'],
            'empty' => ['  ', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalizes_tunisian_numbers(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, (new PhoneNormalizer)->normalize($input));
    }
}
