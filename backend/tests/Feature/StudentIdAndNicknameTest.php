<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NicknameFilter;
use App\Services\StudentIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentIdAndNicknameTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_ids_have_the_right_shape(): void
    {
        $generator = new StudentIdGenerator;

        for ($i = 0; $i < 500; $i++) {
            $id = $generator->candidate();
            $this->assertMatchesRegularExpression(StudentIdGenerator::PATTERN, $id);
            $this->assertDoesNotMatchRegularExpression('/[01OIL]/', substr($id, 3));
        }
    }

    public function test_generator_skips_ids_already_taken(): void
    {
        $generator = new class extends StudentIdGenerator
        {
            /** @var list<string> */
            public array $queue = ['ST-AAAAA', 'ST-AAAAA', 'ST-BBBBB'];

            public function candidate(): string
            {
                return (string) array_shift($this->queue);
            }
        };

        User::factory()->create(['student_id' => 'ST-AAAAA']);

        $this->assertSame('ST-BBBBB', $generator->generate());
    }

    public function test_generator_gives_up_after_many_collisions(): void
    {
        $generator = new class extends StudentIdGenerator
        {
            public function candidate(): string
            {
                return 'ST-AAAAA';
            }
        };
        User::factory()->create(['student_id' => 'ST-AAAAA']);

        $this->expectException(\RuntimeException::class);
        $generator->generate();
    }

    public function test_helpers_can_type_student_ids_loosely(): void
    {
        $this->assertSame('ST-7K3Q9', StudentIdGenerator::normalise('st 7k3q9'));
        $this->assertSame('ST-7K3Q9', StudentIdGenerator::normalise('7k3q9'));
        $this->assertSame('ST-7K3Q9', StudentIdGenerator::normalise('ST-7K3Q9 '));
        $this->assertTrue(StudentIdGenerator::isValid('ST-7K3Q9'));
        $this->assertFalse(StudentIdGenerator::isValid('ST-7K3Q0'));
        $this->assertFalse(StudentIdGenerator::isValid('ST-7K3Q'));
    }

    public function test_nickname_filter(): void
    {
        $filter = new NicknameFilter;

        $this->assertTrue($filter->isAllowed('Hodan'));
        $this->assertTrue($filter->isAllowed('Abdi 2027'));
        $this->assertFalse($filter->isAllowed('sh1t head'));
        $this->assertFalse($filter->isAllowed('B.I.T.C.H'));
        $this->assertFalse($filter->isAllowed('Admin'));
        $this->assertFalse($filter->isAllowed('KULMISS official'));
    }

    public function test_extra_blocked_words_from_config(): void
    {
        config(['nickname_blocklist.words' => ['badword']]);

        $this->assertFalse((new NicknameFilter)->isAllowed('my b4dword'));
    }
}
