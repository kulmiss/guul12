<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * The exam subjects of the Ethiopian Grade 12 national exam (ESSLCE).
 * Safe to run in production and to run again (it only adds or updates these rows).
 * Units and chapters come from the content import in Phase 2.
 */
class CurriculumSeeder extends Seeder
{
    /**
     * code => [name, stream (null = both), icon, sort]
     *
     * @var array<string, array{0: string, 1: string|null, 2: string, 3: int}>
     */
    public const SUBJECTS = [
        'english' => ['English', null, 'translate', 10],
        'mathematics' => ['Mathematics', null, 'calculate', 20],
        'aptitude' => ['Scholastic Aptitude', null, 'psychology', 30],
        'physics' => ['Physics', Subject::STREAM_NATURAL, 'bolt', 40],
        'chemistry' => ['Chemistry', Subject::STREAM_NATURAL, 'science', 50],
        'biology' => ['Biology', Subject::STREAM_NATURAL, 'eco', 60],
        'geography' => ['Geography', Subject::STREAM_SOCIAL, 'public', 70],
        'history' => ['History', Subject::STREAM_SOCIAL, 'history_edu', 80],
        'economics' => ['Economics', Subject::STREAM_SOCIAL, 'trending_up', 90],
    ];

    public function run(): void
    {
        foreach (self::SUBJECTS as $code => [$name, $stream, $icon, $sort]) {
            Subject::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'stream' => $stream,
                'is_exam_subject' => true,
                'icon' => $icon,
                'sort' => $sort,
                'is_active' => true,
            ]);
        }
    }
}
