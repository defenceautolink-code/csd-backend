<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CallRecording extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'call_recordings';

    protected $fillable = [
        'user_id',
        'lead_id',
        'follow_up_id',
        'batch_id',
        'recording_date',
        'call_time',
        'phone_number',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_remark',
    ];

    protected $casts = [
        'recording_date' => 'date:Y-m-d',
        'file_size' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = [
        'stream_url',
        'file_url',
    ];

    /**
     * Staff member who uploaded the recording (Sales Executive / Receptionist).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Optional linked lead.
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id')->withTrashed();
    }

    /**
     * Optional linked follow-up interaction.
     */
    public function followUp()
    {
        return $this->belongsTo(LeadFollowUp::class, 'follow_up_id');
    }

    /**
     * Admin or Sales Manager who reviewed the recording.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStreamUrlAttribute(): string
    {
        return "/call-recordings/{$this->id}/stream";
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        return str_starts_with($this->file_path, '/')
            ? $this->file_path
            : '/' . ltrim($this->file_path, '/');
    }
}
