<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\StudentIdGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fn (): string => app(StudentIdGenerator::class)->generate(),
            'role' => UserRole::Student,
            'grade' => 12,
            'stream' => 'natural',
            'is_retaker' => false,
        ];
    }

    public function staff(UserRole $role): static
    {
        return $this->state(fn (): array => [
            'role' => $role,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ]);
    }
}
