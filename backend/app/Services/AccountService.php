<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Bookmark;
use App\Models\Device;
use App\Models\PremiumGrant;
use App\Models\User;
use App\Services\Auth\GoogleIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous accounts (one per install) and optional Google sign-in.
 */
class AccountService
{
    public function __construct(private readonly StudentIdGenerator $ids) {}

    /**
     * Finds the account for this install, or creates a new anonymous one.
     *
     * @return array{0: User, 1: bool} the user and whether it was just created
     */
    public function registerDevice(string $installId, string $platform, ?string $appVersion): array
    {
        $hash = Device::hashInstallId($installId);
        $now = Carbon::now();

        return DB::transaction(function () use ($hash, $platform, $appVersion, $now): array {
            $device = Device::query()->with('user')->where('device_hash', $hash)->lockForUpdate()->first();

            if ($device !== null) {
                $device->forceFill(['last_seen_at' => $now, 'app_version' => $appVersion, 'platform' => $platform])->save();

                return [$device->user, false];
            }

            $user = new User;
            $user->student_id = $this->ids->generate();
            $user->role = UserRole::Student;
            $user->last_active_at = $now;
            $user->save();

            Device::query()->create([
                'user_id' => $user->id,
                'device_hash' => $hash,
                'platform' => $platform,
                'app_version' => $appVersion,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            return [$user, true];
        });
    }

    /**
     * Links Google to the current account, or moves this phone's progress into the account
     * that already uses this Google identity (sign-in on a new phone).
     *
     * @throws AccountConflict when the current account is already linked to a different Google identity
     */
    public function signInWithGoogle(User $current, GoogleIdentity $identity): User
    {
        if ($current->isStaff()) {
            throw new AccountConflict('Staff accounts cannot use Google sign-in in the app.');
        }

        if ($current->google_sub !== null && $current->google_sub !== $identity->sub) {
            throw new AccountConflict('This phone is signed in with another Google account.');
        }

        return DB::transaction(function () use ($current, $identity): User {
            $existing = User::query()->where('google_sub', $identity->sub)->lockForUpdate()->first();

            if ($existing === null || $existing->id === $current->id) {
                $current->google_sub = $identity->sub;
                if ($identity->emailVerified && $identity->email !== null && $current->email === null
                    && ! User::query()->where('email', $identity->email)->exists()) {
                    $current->email = $identity->email;
                }
                $current->save();

                AuditLog::write($current, 'account.google_linked', $current);

                return $current;
            }

            if ($existing->isStaff()) {
                throw new AccountConflict('This Google account belongs to a staff member.');
            }

            $this->mergeInto($current, $existing);

            return $existing;
        });
    }

    /**
     * Moves progress from an anonymous account into the signed-in account, then deletes the anonymous one.
     */
    private function mergeInto(User $from, User $into): void
    {
        $knownAttempts = Attempt::query()->where('user_id', $into->id)->pluck('client_uuid')->all();

        Attempt::query()->where('user_id', $from->id)
            ->whereNotIn('client_uuid', $knownAttempts)
            ->update(['user_id' => $into->id]);

        $knownBookmarks = Bookmark::query()->where('user_id', $into->id)->pluck('question_id')->all();

        Bookmark::query()->where('user_id', $from->id)
            ->whereNotIn('question_id', $knownBookmarks)
            ->update(['user_id' => $into->id]);

        Device::query()->where('user_id', $from->id)->update(['user_id' => $into->id]);

        // Premium paid on this phone's student ID must not be lost.
        PremiumGrant::query()->where('user_id', $from->id)->update(['user_id' => $into->id]);

        AuditLog::write($into, 'account.merged', $into, ['from_student_id' => $from->student_id]);

        $from->tokens()->delete();
        $from->delete();
    }
}
