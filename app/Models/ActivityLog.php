<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'title',
        'module',
        'action',
        'method',
        'url',
        'ip_address',
        'user_agent',
        'description',
    ];

    /**
     * Relationship: The user who performed this activity
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Helper to easily record an activity log anywhere in the backend
     *
     * @param array $data
     * @return ActivityLog|null
     */
    public static function record(array $data)
    {
        try {
            return self::create([
                'user_id' => $data['user_id'] ?? (auth('sanctum')->id() ?? auth()->id()),
                'title' => $data['title'] ?? 'System Activity',
                'module' => $data['module'] ?? 'General',
                'action' => $data['action'] ?? 'other',
                'method' => $data['method'] ?? request()->method(),
                'url' => $data['url'] ?? request()->fullUrl(),
                'ip_address' => $data['ip_address'] ?? request()->ip(),
                'user_agent' => $data['user_agent'] ?? substr((string) request()->userAgent(), 0, 500),
                'description' => $data['description'] ?? null,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ActivityLog recording failed: ' . $e->getMessage());
            return null;
        }
    }
}
