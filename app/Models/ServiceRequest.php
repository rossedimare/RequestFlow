<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $description
 * @property RequestStatus $status
 * @property RequestPriority $priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 */
// user_id is intentionally not fillable: it is set from the authenticated user.
#[Fillable(['title', 'description', 'status', 'priority'])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'priority' => RequestPriority::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Restrict the query to requests whose title or description contains the term.
     *
     * @param  Builder<ServiceRequest>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        // Escape LIKE wildcards (and the escape character itself) so the term is matched literally.
        $like = '%'.preg_replace('/[!%_]/', '!$0', $term).'%';

        $query->where(function (Builder $query) use ($like) {
            $query->whereRaw("title LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("description LIKE ? ESCAPE '!'", [$like]);
        });
    }
}
