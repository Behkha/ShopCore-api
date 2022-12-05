<?php

use App\Models\Attribute;
use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call(LocationSeeder::class);
        DB::table('payment_methods')
            ->insert(['name' => 'internet', 'is_enabled' => true]);
        DB::table('payment_methods')
            ->insert(['name' => 'wallet', 'is_enabled' => false]);
//        if (env('APP_ENV') === 'local') {
            Attribute::create(['name' => 'Color']);
            Attribute::create(['name' => 'Size']);
            Attribute::create(['name' => 'Quality']);
            Attribute::create(['name' => 'Type']);
            Attribute::create(['name' => 'Mass']);
            factory(App\Models\Category::class, 20)
                ->create()
                ->each(function ($category) {
                    $category->attributes()->attach(Attribute::inRandomOrder()->take(3)->pluck('id'));
                });
            Brand::create(['title' => 'Brand-1']);
            Brand::create(['title' => 'Brand-2']);
            Brand::create(['title' => 'Brand-3']);
            $this->call(DeliveryMethodSeeder::class);
//        }
    }
}
