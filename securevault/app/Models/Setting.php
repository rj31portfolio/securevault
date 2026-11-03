<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
class Setting extends Model {
    protected $primaryKey = 'key'; public $incrementing = false; protected $keyType = 'string'; protected $guarded = [];
    public static function read(string $key, mixed $default = null): mixed {
        $row = static::find($key); if (!$row) return $default;
        return json_decode($row->encrypted ? Crypt::decryptString($row->value) : $row->value,true,512,JSON_THROW_ON_ERROR);
    }
    public static function put(string $key, mixed $value, bool $secret = false): void {
        $encoded = json_encode($value,JSON_THROW_ON_ERROR);
        static::updateOrCreate(['key'=>$key],['value'=>$secret ? Crypt::encryptString($encoded) : $encoded,'encrypted'=>$secret]);
    }
}
