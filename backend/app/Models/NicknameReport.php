<?php

namespace App\Models;

use App\Enums\NicknameReportReason;
use App\Enums\NicknameReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student reported another student's nickname (DECISIONS #33).
 *
 * @property int $id
 * @property int $reporter_id
 * @property int $reported_user_id
 * @property string $nickname
 * @property NicknameReportReason $reason
 * @property string|null $note
 * @property NicknameReportStatus $status
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property-read User $reporter
 * @property-read User $reportedUser
 * @property-read User|null $resolver
 */
class NicknameReport extends Model
{
    protected $fillable = ['reporter_id', 'reported_user_id', 'nickname', 'reason', 'note', 'status', 'resolved_by', 'resolved_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => NicknameReportReason::class,
            'status' => NicknameReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
