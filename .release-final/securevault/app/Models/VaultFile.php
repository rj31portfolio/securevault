<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
class VaultFile extends Model {
    use HasUuids, SoftDeletes;
    protected $table = 'files'; protected $guarded = []; protected $hidden = ['disk_path','sha256'];
    public function user() { return $this->belongsTo(User::class)->withTrashed(); }
    public function category() { return $this->belongsTo(Category::class); }
    public function getKindAttribute(): string { return str_starts_with($this->mime,'image/') ? 'image' : (str_starts_with($this->mime,'video/') ? 'video' : 'document'); }
    public function getFormattedSizeAttribute(): string { return number_format($this->size/1048576,2).' MB'; }
}
