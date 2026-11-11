<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One sensitive store back-office action (admin_audit_logs): who (the store
 * user's id, with their name and email as they were), what (`action`), which
 * fields it touched and how (`changed_fields`: field => `set`, `changed` or
 * `removed`), when, and from which IP and browser. Never a value or a secret.
 * Rows are written once and never updated (.ai/rules/admin-env-overrides.md,
 * "Security").
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $user_name
 * @property string|null $user_email
 * @property string $action
 * @property array<string, string> $changed_fields
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 * @property-read StoreUser|null $user
 */
#[Table('admin_audit_logs')]
#[Fillable(['user_id', 'user_name', 'user_email', 'action', 'changed_fields', 'ip_address', 'user_agent'])]
class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    /** A save of the SMTP settings page that changed at least one field. */
    public const string SMTP_SETTINGS_UPDATED = 'smtp_settings.updated';

    /** A wrong current password on the SMTP settings page, counted by its limiter. */
    public const string SMTP_SETTINGS_PASSWORD_FAILED = 'smtp_settings.password_failed';

    /** The kinds of change `changed_fields` records. */
    public const array CHANGE_KINDS = ['set', 'changed', 'removed'];

    /** Rows are written once and never updated. */
    public const UPDATED_AT = null;

    /**
     * Writes one row for `$actor`. `$changedFields` holds field names and
     * change kinds only, never values.
     *
     * @param  array<string, string>  $changedFields  field => set|changed|removed
     */
    public static function record(StoreUser $actor, string $action, array $changedFields, Request $request): self
    {
        $userAgent = $request->userAgent();

        return self::query()->create([
            'user_id' => $actor->getKey(),
            'user_name' => $actor->name === null ? null : Str::limit((string) $actor->name, 255, ''),
            'user_email' => $actor->email === null ? null : Str::limit((string) $actor->email, 255, ''),
            'action' => $action,
            'changed_fields' => $changedFields,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
        ]);
    }

    /**
     * The newest rows of these actions, newest first, with the user they name.
     *
     * @param  list<string>  $actions
     * @return Collection<int, self>
     */
    public static function recent(array $actions, int $limit = 10): Collection
    {
        return self::query()
            ->select(['id', 'user_id', 'user_name', 'user_email', 'action', 'changed_fields', 'ip_address', 'created_at'])
            ->forActions($actions)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The store user the row names; null once the account is gone (there is
     * no foreign key: the row outlives it, with the name and email snapshot).
     *
     * @return BelongsTo<StoreUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'user_id');
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<string>  $actions
     */
    #[Scope]
    protected function forActions(Builder $query, array $actions): void
    {
        $query->whereIn($query->qualifyColumn('action'), $actions);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
