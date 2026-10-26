<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'detail',
        'ip_address',
    ];

    protected $appends = [
        'formatted_time',
    ];

    /**
     * Relationship: The user who performed this activity
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Timestamp formatted in Indian Standard Time (IST)
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at ? $this->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i:s A') : '-';
    }

    /**
     * Extract clean IPv4 address from current request
     */
    public static function getClientIpv4(?Request $request = null): string
    {
        $req = $request ?? request();
        $ip = $req->ip() ?? $req->server('REMOTE_ADDR') ?? '127.0.0.1';

        // Localhost IPv6 to IPv4
        if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
            return '127.0.0.1';
        }

        // IPv4-mapped IPv6 (::ffff:192.168.1.1)
        if (str_starts_with($ip, '::ffff:')) {
            return substr($ip, 7);
        }

        // Check if multiple IPs are present in forwarded headers
        if (str_contains($ip, ',')) {
            $parts = explode(',', $ip);
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (filter_var($trimmed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $trimmed;
                }
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }

        return '127.0.0.1';
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
            $userId = $data['user_id'] ?? (auth('sanctum')->id() ?? auth()->id() ?? request()->user('sanctum')?->id);

            return self::create([
                'user_id' => $userId,
                'module' => $data['module'] ?? 'General',
                'action' => strtolower($data['action'] ?? 'other'),
                'detail' => $data['detail'] ?? ($data['description'] ?? null),
                'ip_address' => $data['ip_address'] ?? self::getClientIpv4(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ActivityLog recording failed: ' . $e->getMessage());
            return null;
        }
    }
}
