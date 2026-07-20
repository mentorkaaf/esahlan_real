<?php
namespace Database\Seeders;

use App\Models\Gift;
use Illuminate\Database\Seeder;

class GiftSeeder extends Seeder
{
    public function run(): void
    {
        $gifts = [
            ['name' => 'Rose',        'emoji' => '🌹', 'animation' => 'rose',       'coins' => 10,   'sort' => 1],
            ['name' => 'Heart',       'emoji' => '❤️',  'animation' => 'heart',      'coins' => 20,   'sort' => 2],
            ['name' => 'Clap',        'emoji' => '👏',  'animation' => 'confetti',   'coins' => 30,   'sort' => 3],
            ['name' => 'Fire',        'emoji' => '🔥',  'animation' => 'fire',       'coins' => 50,   'sort' => 4],
            ['name' => 'Diamond',     'emoji' => '💎',  'animation' => 'sparkle',    'coins' => 100,  'sort' => 5],
            ['name' => 'Crown',       'emoji' => '👑',  'animation' => 'crown',      'coins' => 200,  'sort' => 6],
            ['name' => 'Rocket',      'emoji' => '🚀',  'animation' => 'rocket',     'coins' => 500,  'sort' => 7],
            ['name' => 'Super Star',  'emoji' => '⭐',  'animation' => 'superstar',  'coins' => 1000, 'sort' => 8],
        ];

        foreach ($gifts as $gift) {
            Gift::firstOrCreate(['name' => $gift['name']], $gift + ['is_active' => true]);
        }
    }
}
