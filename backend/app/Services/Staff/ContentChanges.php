<?php

namespace App\Services\Staff;

use App\Enums\Access;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\ContentChange;
use App\Models\Mock;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Staff changes to mocks and chapters from Nova. Each one is logged with the old and the new value.
 */
final class ContentChanges
{
    public function unpublishMock(Mock $mock, User $actor): bool
    {
        if ($mock->unpublished_at !== null) {
            return false;
        }
        $mock->forceFill(['unpublished_at' => Carbon::now()])->save();
        AuditLog::change($actor, 'mock.published', $mock, ['shown' => true], ['shown' => false]);

        return true;
    }

    public function publishMock(Mock $mock, User $actor): bool
    {
        if ($mock->unpublished_at === null) {
            return false;
        }
        $mock->forceFill(['unpublished_at' => null])->save();
        AuditLog::change($actor, 'mock.published', $mock, ['shown' => false], ['shown' => true]);

        return true;
    }

    /** Which of the mock's questions students see. The app picks up changes by itself (hash). */
    public function checkMock(Mock $mock): string
    {
        $questions = $mock->questions()->get(['questions.id', 'questions.status']);
        $hidden = $questions->reject(fn (Question $q): bool => $q->status->isVisibleToStudents());
        $total = $questions->count();
        if ($hidden->isEmpty()) {
            return "All {$total} questions are shown to students.";
        }
        $list = $hidden->map(fn (Question $q): string => "#{$q->id} ({$q->status->value})")->implode(', ');

        return ($total - $hidden->count())." of {$total} questions are shown to students. Hidden: {$list}. The app gets changes by itself.";
    }

    public function setVideoAccess(Chapter $chapter, Access $access, User $actor): bool
    {
        $old = $chapter->video_access;
        if ($chapter->video_youtube_id === null || $old === $access) {
            return false;
        }
        $chapter->video_access = $access;
        $chapter->save();
        AuditLog::change($actor, 'chapter.video_access', $chapter, ['video_access' => $old->value], ['video_access' => $access->value]);

        return true;
    }

    /** Phones download the chapter again at their next sync (after fixing a picture, for example). */
    public function resendChapter(Chapter $chapter, User $actor): void
    {
        ContentChange::record('chapter', $chapter->id, $chapter->id, ContentChange::ACTION_UPSERT);
        AuditLog::write($actor, 'chapter.resent', $chapter);
    }
}
