<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notice> */
class NoticeFactory extends Factory
{
    protected $model = Notice::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'content' => fake()->paragraph(),
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'active_status' => true,
        ];
    }
}
