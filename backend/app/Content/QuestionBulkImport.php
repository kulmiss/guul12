<?php

namespace App\Content;

use App\Enums\QuestionStatus;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Nova "Bulk import": questions written in the content-file format (YAML) become Draft questions in
 * one chapter. Same rules as the content files; all or nothing. They still need the second solve and
 * Beta before students see them (quality gate).
 */
final class QuestionBulkImport
{
    public const MAX_QUESTIONS = 200;

    /** @return array{created: list<int>, errors: list<string>} */
    public function import(string $yaml, Chapter $chapter, User $actor): array
    {
        try {
            $data = Yaml::parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $e) {
            return ['created' => [], 'errors' => ['The file is not valid YAML: '.$e->getMessage()]];
        }
        $items = is_array($data) && array_key_exists('questions', $data) ? $data['questions'] : $data;
        if (! is_array($items) || $items === [] || ! array_is_list($items)) {
            return ['created' => [], 'errors' => ['Write a list of questions (or "questions:" with a list), like in the content files.']];
        }
        if (count($items) > self::MAX_QUESTIONS) {
            return ['created' => [], 'errors' => ['At most '.self::MAX_QUESTIONS.' questions per file.']];
        }

        $checked = (new ContentValidator(array_values(array_map(fn (mixed $code): string => (string) $code, Subject::query()->pluck('code')->all()))))->importableQuestions($items);
        if ($checked['errors'] !== []) {
            return ['created' => [], 'errors' => $checked['errors']];
        }

        $created = DB::transaction(function () use ($checked, $chapter, $actor): array {
            $ids = [];
            foreach ($checked['questions'] as $def) {
                $question = new Question([
                    'chapter_id' => $chapter->id,
                    'type' => $def->type,
                    'stem' => $def->stem,
                    'media_path' => $def->media,
                    'difficulty' => $def->difficulty,
                    'answer_key' => $def->answerKey,
                    'explanation' => $def->explanation,
                    'source' => $def->source,
                    'tags' => $def->tags === [] ? null : $def->tags,
                ]);
                $question->status = QuestionStatus::Draft;
                $question->version = 1;
                $question->save();
                foreach ($def->options as $i => $option) {
                    $question->options()->create(['key' => $option->key, 'side' => $option->side, 'body' => $option->body, 'sort' => $i + 1]);
                }
                $ids[] = $question->id;
            }
            AuditLog::write($actor, 'question.imported', $chapter, ['chapter_id' => $chapter->id, 'questions' => count($ids)]);

            return $ids;
        });

        return ['created' => $created, 'errors' => []];
    }
}
