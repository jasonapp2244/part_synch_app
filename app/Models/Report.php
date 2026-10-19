<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Report extends Model
{
    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'details',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /** Reasons the mobile app offers in its report sheet. */
    public const REASONS = [
        'spam',
        'harassment',
        'hate_speech',
        'sexual_content',
        'violence',
        'scam_or_fraud',
        'counterfeit',
        'other',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Resolve the reported record so the admin panel can show what it is.
     * Returns null when the target has since been deleted.
     */
    public function subject()
    {
        return match ($this->reportable_type) {
            'user' => User::withTrashed()->find($this->reportable_id),
            'product' => Product::find($this->reportable_id),
            'message' => ChMessage::find($this->reportable_id),
            'review' => Review::find($this->reportable_id),
            default => null,
        };
    }

    /**
     * One-line description of the reported record for the admin list and
     * its exports.
     */
    public function subjectLabel(): string
    {
        $subject = $this->subject();

        if (! $subject) {
            return 'Deleted ' . $this->reportable_type;
        }

        return match ($this->reportable_type) {
            'user' => trim($subject->first_name . ' ' . $subject->last_name) ?: $subject->email,
            'product' => $subject->name ?? 'Product #' . $this->reportable_id,
            'review' => Str::limit($subject->review_text ?? '', 80),
            'message' => Str::limit($subject->body ?? '(attachment)', 80),
            default => '—',
        };
    }
}
