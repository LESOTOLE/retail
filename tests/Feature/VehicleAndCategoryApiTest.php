<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleAndCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_and_filter_vehicles(): void
    {
        Vehicle::create([
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'year_start' => 2022,
            'year_end' => null,
        ]);

        Vehicle::create([
            'brand' => 'Yamaha',
            'model' => 'NMAX 155',
            'year_start' => 2020,
            'year_end' => null,
        ]);

        $allResponse = $this->getJson('/api/v1/vehicles');
        $allResponse->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $filteredResponse = $this->getJson('/api/v1/vehicles?brand=Honda');
        $filteredResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.brand', 'Honda');

        $searchResponse = $this->getJson('/api/v1/vehicles?search=NMAX');
        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.model', 'NMAX 155');
    }

    public function test_can_list_categories(): void
    {
        Category::create(['name' => 'Oli & Cairan', 'slug' => 'oli-cairan']);
        Category::create(['name' => 'Busi', 'slug' => 'busi']);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);
    }
}
