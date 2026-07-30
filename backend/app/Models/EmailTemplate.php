<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'body', 'variables', 'is_active'];

    protected $casts = ['variables' => 'array', 'is_active' => 'boolean'];

    public static function render(string $key, array $vars = []): ?array
    {
        $tpl = static::where('key', $key)->where('is_active', true)->first();
        if (!$tpl) return null;

        $vars['year'] = date('Y');
        $body    = $tpl->body;
        $subject = $tpl->subject;
        foreach ($vars as $k => $v) {
            $body    = str_replace('{{'.$k.'}}', $v, $body);
            $subject = str_replace('{{'.$k.'}}', $v, $subject);
        }
        return ['subject' => $subject, 'body' => $body];
    }
}
