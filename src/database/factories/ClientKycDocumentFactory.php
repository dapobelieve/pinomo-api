<?php

namespace Database\Factories;

use App\Models\ClientKycDocument;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientKycDocumentFactory extends Factory
{
    protected $model = ClientKycDocument::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'document_type' => $this->faker->randomElement(['identity', 'address', 'income']),
            'file_path' => $this->faker->filePath(),
            'status' => 'pending',
            'comments' => null
        ];
    }
}