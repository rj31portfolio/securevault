<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Category extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['allowed_extensions'=>'array','enabled'=>'boolean','is_screenshot'=>'boolean']; }
    public function files() { return $this->hasMany(VaultFile::class); }
}
